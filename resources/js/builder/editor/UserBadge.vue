<script setup>
import { computed } from 'vue'

/** Coloured initials for a person (presence list, lock markers). */
const props = defineProps({
  name: { type: String, default: '?' },
  color: { type: String, default: null },
  size: { type: String, default: 'md' },
})

const initials = computed(() =>
  props.name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('')
)
</script>

<template>
  <span
    :class="[
      'inline-flex shrink-0 select-none items-center justify-center rounded-full font-semibold text-white',
      size === 'sm' ? 'h-4 w-4 text-[8px]' : 'h-6 w-6 text-[10px]',
    ]"
    :style="{ backgroundColor: color || '#64748b' }"
    :title="name">
    {{ initials }}
  </span>
</template>
