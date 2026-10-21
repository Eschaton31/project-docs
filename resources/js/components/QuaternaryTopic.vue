<script setup>
defineProps({
  title: String,
  start: String,
  id: String,
})
</script>

<template>
  <div class="space-y-1">
    <div :id class="quaternary-topic text-[10pt] font-bold text-bene-gray">
      {{ title }}
    </div>
    <div class="space-y-3 text-bene-gray">
      <slot />
    </div>
  </div>
</template>

<style scoped></style>
