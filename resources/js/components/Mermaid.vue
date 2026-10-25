<script setup>
import mermaid from 'mermaid'

import { inject, nextTick, onMounted, ref, useTemplateRef, watch } from 'vue'

const props = defineProps({
  code: { type: String, required: true },
})

const track = inject('renderTracker', null)

const chart = useTemplateRef('chart')
const renderKey = ref(0)

// matches the rest of the document: Inter at body size, bene-teal accents, slate lines
const TEAL = '#0b6374'
const TEAL_TINT = '#dae8ea'
const TEXT = '#424242'

const config = {
  startOnLoad: false,
  theme: 'base',
  themeVariables: {
    fontFamily: 'InterVariable, ui-sans-serif, system-ui, sans-serif',
    fontSize: '13px',
    primaryColor: TEAL_TINT,
    primaryTextColor: TEXT,
    primaryBorderColor: TEAL,
    secondaryColor: '#f1f5f9',
    tertiaryColor: '#f8fafc',
    lineColor: '#64748b',
    textColor: TEXT,
    titleColor: TEAL,

    // gantt: sections alternate between a teal wash and white so each group reads as its own band
    sectionBkgColor: '#eef5f6',
    sectionBkgColor2: '#eef5f6',
    altSectionBkgColor: '#ffffff',
    gridColor: '#e2e8f0',
    taskBkgColor: TEAL_TINT,
    taskBorderColor: TEAL,
    taskTextColor: TEXT,
    taskTextLightColor: TEXT,
    taskTextDarkColor: TEXT,
    taskTextOutsideColor: TEXT,
    activeTaskBkgColor: '#b3d0d6',
    activeTaskBorderColor: TEAL,
    doneTaskBkgColor: '#e2e8f0',
    doneTaskBorderColor: '#94a3b8',
    critBkgColor: '#ffe4e6',
    critBorderColor: '#e11d48',
    todayLineColor: TEAL,
  },
  themeCSS: `
    .titleText { font-size: 15px; font-weight: 600; fill: ${TEAL}; }
    .task { stroke-width: 1px; }
    .grid .tick line { stroke: #dbe5e8; }
    .grid .tick text { font-size: 11px; fill: #64748b; }
    .today { stroke-width: 1.5px; stroke-dasharray: 4 3; }
    .section0, .section1, .section2, .section3 { opacity: 1; }
    .sectionTitle { font-weight: 600; }
    .section-divider { stroke: #b3d0d6; stroke-width: 1px; }
  `,
  gantt: {
    fontSize: 12,
    sectionFontSize: 12,
    barHeight: 24,
    barGap: 6,
    topPadding: 50,
    // start the grid lines where the first section row starts (topPadding - 2), so no stubs poke
    // out between the title and the chart
    gridLineStartPadding: 48,
    leftPadding: 130,
    rightPadding: 24,
    axisFormat: '%b %d',
  },
}

// mermaid draws section titles at a fixed x of 10 and never wraps them, so long ones run under
// the bars; keep this much clear space between a title and the plot
const LABEL_X = 10
const LABEL_GAP = 12

onMounted(render)

watch(
  () => props.code,
  () => render()
)

function render() {
  renderKey.value++ // 🔥 force DOM reset

  // titles are measured in Inter, so wait for it to load or the wrapping uses fallback metrics
  const done = Promise.all([nextTick(), document.fonts?.ready]).then(() => {
    if (!chart.value) return

    const { code, leftPadding } = wrapSections(props.code)

    // gantt charts size themselves to the temporary render container (the full page body) and then
    // get scaled down into the column, shrinking the text; lay them out at the column's real width
    const width = chart.value.clientWidth || undefined
    mermaid.initialize({ ...config, gantt: { ...config.gantt, leftPadding, useWidth: width } })

    return mermaid.render(`m-${Date.now()}`, code).then(({ svg }) => {
      chart.value.innerHTML = svg
      divideSections(chart.value)
      tidyGantt(chart.value)
    })
  })

  // lets the print view wait for the diagram before paginating
  track?.(done)
}

// break gantt section titles onto several lines (mermaid honours <br>) so each fits in the left
// gutter; a single word wider than the gutter widens the gutter instead of being split
function wrapSections(code) {
  const { leftPadding, sectionFontSize } = config.gantt
  if (!/^\s*gantt\b/m.test(code)) return { code, leftPadding }

  const ctx = document.createElement('canvas').getContext('2d')
  ctx.font = `600 ${sectionFontSize}px ${config.themeVariables.fontFamily}`
  const measure = (text) => ctx.measureText(text).width

  const room = leftPadding - LABEL_X - LABEL_GAP
  let widest = 0

  const wrapped = code.replace(/^(\s*section\s+)(.+)$/gm, (_, head, title) => {
    const lines = title.split(/<br\s*\/?>/i).flatMap((part) => wrap(part, room, measure))
    widest = Math.max(widest, ...lines.map(measure))
    return head + lines.join('<br>')
  })

  return { code: wrapped, leftPadding: Math.max(leftPadding, Math.ceil(widest) + LABEL_X + LABEL_GAP) }
}

function wrap(text, room, measure) {
  const lines = []
  for (const word of text.split(/\s+/).filter(Boolean)) {
    const joined = lines.length ? `${lines.at(-1)} ${word}` : word
    if (lines.length && measure(joined) <= room) lines[lines.length - 1] = joined
    else lines.push(word)
  }
  return lines
}

// mermaid paints the grid before the opaque section rows, hiding it, and runs the today line from
// the title down through the axis labels; lift the grid above the rows (still under the bars) and
// clip the today line to the rows
function tidyGantt(el) {
  const rows = [...el.querySelectorAll('rect.section')]
  if (!rows.length) return

  const rowGroup = rows[0].parentNode
  el.querySelectorAll('g.grid').forEach((grid) => rowGroup.after(grid))

  const tops = rows.map((row) => Number(row.getAttribute('y')))
  const bottoms = rows.map((row, i) => tops[i] + Number(row.getAttribute('height')))
  el.querySelectorAll('line.today').forEach((line) => {
    line.setAttribute('y1', Math.min(...tops))
    line.setAttribute('y2', Math.max(...bottoms))
  })
}

// gantt draws one background row per task, classed by its section; rule a line wherever the
// section changes so neighbouring groups don't run together. rows are drawn in start-date order,
// not top to bottom, so sort them by position first
function divideSections(el) {
  const rows = [...el.querySelectorAll('rect.section')].sort(
    (a, b) => Number(a.getAttribute('y')) - Number(b.getAttribute('y'))
  )

  rows.forEach((row, i) => {
    if (i === 0 || row.getAttribute('class') === rows[i - 1].getAttribute('class')) return

    const x = Number(row.getAttribute('x'))
    const y = row.getAttribute('y')
    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line')
    line.setAttribute('class', 'section-divider')
    line.setAttribute('x1', x)
    line.setAttribute('x2', x + Number(row.getAttribute('width')))
    line.setAttribute('y1', y)
    line.setAttribute('y2', y)
    row.parentNode.append(line) // after every row, so later-drawn rows can't paint over it
  })
}
</script>

<template>
  <div :key="renderKey" ref="chart" class="my-4 flex justify-center"></div>
</template>
