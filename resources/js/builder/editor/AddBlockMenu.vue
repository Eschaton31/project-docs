<script setup>
import { computed } from 'vue'

import { registry } from '../registry.js'

/** Dropdown of block types (grouped by category) that may go at a given place. */
const props = defineProps({
  types: { type: Array, required: true },
  label: { type: String, required: true },
})
const emit = defineEmits(['add'])

const groups = computed(() => {
  const byCategory = {}
  for (const type of props.types) {
    const { category, label } = registry[type]
    ;(byCategory[category] ??= []).push({ type, label })
  }

  return byCategory
})

function onChange(event) {
  const type = event.target.value
  event.target.value = ''
  if (type) emit('add', type)
}
</script>

<template>
  <select
    :disabled="!types.length"
    class="rounded border border-slate-300 bg-white px-2 py-1 text-xs disabled:opacity-40"
    @change="onChange">
    <option value="">{{ label }}</option>
    <optgroup v-for="(items, category) in groups" :key="category" :label="category">
      <option v-for="item in items" :key="item.type" :value="item.type">{{ item.label }}</option>
    </optgroup>
  </select>
</template>
