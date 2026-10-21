<script setup>
import mermaid from 'mermaid'

import { inject, nextTick, onMounted, ref, useTemplateRef, watch } from 'vue'

const props = defineProps({
  code: { type: String, required: true },
})

const track = inject('renderTracker', null)

const chart = useTemplateRef('chart')
const renderKey = ref(0)

onMounted(() => {
  mermaid.initialize({
    startOnLoad: false,
    theme: 'base',
    themeVariables: {
      fontFamily: 'Inter Variable Text',
      primaryColor: '#d3d1d2',
      primaryTextColor: '#262626',
      primaryBorderColor: '#d3d1d2',
      lineColor: '#333333',
      secondaryColor: '#00ffcc',
      tertiaryColor: '#fff0f0',
    },
  })
  render()
})

watch(
  () => props.code,
  () => render()
)

function render() {
  renderKey.value++ // 🔥 force DOM reset

  const done = nextTick().then(() => {
    if (!chart.value) return

    return mermaid.render(`m-${Date.now()}`, props.code).then(({ svg }) => {
      chart.value.innerHTML = svg
    })
  })

  // lets the print view wait for the diagram before paginating
  track?.(done)
}
</script>

<template>
  <div :key="renderKey" ref="chart"></div>
</template>
