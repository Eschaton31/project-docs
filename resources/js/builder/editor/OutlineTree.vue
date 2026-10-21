<script setup>
import { nextTick, provide, reactive, useTemplateRef, watch } from 'vue'

import { useEditorStore } from '@/stores/editor.js'

import OutlineNode from './OutlineNode.vue'

const store = useEditorStore()
const list = useTemplateRef('list')

provide('outlineDrag', reactive({ id: null }))

// keep the selected row visible when the selection comes from the canvas
watch(
  () => store.selectedId,
  async (id) => {
    await nextTick()
    list.value?.querySelector(`[data-outline-id="${id}"]`)?.scrollIntoView({ block: 'nearest' })
  }
)
</script>

<template>
  <ul ref="list" class="py-2">
    <OutlineNode v-for="node in store.roots" :key="node.id" :node />
  </ul>
</template>
