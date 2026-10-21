<script setup>
import { sizeAttrs } from '@/composables/sizing.js'

defineProps({
  src: String,
  description: String,
  // CSS length ('15rem') or Tailwind height class ('h-[15rem]')
  height: String,
})
</script>

<template>
  <div class="my-4 flex flex-col items-center justify-center space-y-1">
    <img class="mx-auto w-auto object-contain" :src v-bind="sizeAttrs(height ?? 'h-36', 'h-', 'height')" alt="" />
    <div class="image-caption text-[10pt] italic">
      <slot name="description">{{ description }}</slot>
    </div>
  </div>
</template>
