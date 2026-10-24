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
    .task { stroke-width: 1px; }
    .grid .tick text { font-size: 11px; fill: #64748b; }
    .today { stroke-dasharray: 4 3; }
    .section0, .section1, .section2, .section3 { opacity: 1; }
    .sectionTitle { font-weight: 600; }
    .section-divider { stroke: #b3d0d6; stroke-width: 1px; }
  `,
  gantt: {
    fontSize: 12,
    sectionFontSize: 12,
    barHeight: 24,
    barGap: 6,
    leftPadding: 130,
    rightPadding: 24,
    axisFormat: '%b %d',
  },
}

onMounted(render)

watch(
  () => props.code,
  () => render()
)

function render() {
  renderKey.value++ // 🔥 force DOM reset

  const done = nextTick().then(() => {
    if (!chart.value) return

    // gantt charts size themselves to the temporary render container (the full page body) and then
    // get scaled down into the column, shrinking the text; lay them out at the column's real width
    const width = chart.value.clientWidth || undefined
    mermaid.initialize({ ...config, gantt: { ...config.gantt, useWidth: width } })

    return mermaid.render(`m-${Date.now()}`, props.code).then(({ svg }) => {
      chart.value.innerHTML = svg
      divideSections(chart.value)
    })
  })

  // lets the print view wait for the diagram before paginating
  track?.(done)
}

// gantt draws one background row per task, classed by its section; rule a line wherever the
// section changes so neighbouring groups don't run together
function divideSections(el) {
  const rows = [...el.querySelectorAll('rect.section')]

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
    row.after(line)
  })
}
</script>

<template>
  <div :key="renderKey" ref="chart" class="my-4 flex justify-center"></div>
</template>
