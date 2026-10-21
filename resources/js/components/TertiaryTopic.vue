<script setup>
defineProps({
  title: String,
  start: String,
  id: String,
})
</script>

<template>
  <div class="space-y-2">
    <div :id class="tertiary-topic text-[12pt] font-bold italic text-bene-gray">
      {{ title }}
    </div>
    <div class="space-y-3 text-bene-gray">
      <slot />
    </div>
  </div>
</template>

<style scoped></style>
