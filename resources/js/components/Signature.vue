<script setup>
defineProps({
  // e.g. "Prepared by:", shown above the signing space
  label: String,
  name: String,
  // job title under the name; left out when empty
  designation: String,
  align: { type: String, default: 'left' },
})

const ALIGN = { left: 'mr-auto', center: 'mx-auto', right: 'ml-auto' }
</script>

<template>
  <div class="my-6 flex break-inside-avoid-page text-[10pt] text-bene-gray">
    <div :class="['w-64 max-w-full', ALIGN[align] ?? ALIGN.left]">
      <div v-if="label" class="signature-label">{{ label }}</div>
      <div class="h-12 border-b border-solid border-bene-gray" />
      <div class="signature-name pt-1 text-center font-bold">
        <slot name="name">{{ name }}</slot>
      </div>
      <div v-if="designation" class="text-center italic">{{ designation }}</div>
    </div>
  </div>
</template>
