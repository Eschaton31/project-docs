<script setup>
defineProps({
  title: String,
  id: String,
})
</script>

<template>
  <div class="new-page flex w-full flex-col">
    <div :id class="primary-topic flex shrink-0 text-[15pt] font-bold text-bene-teal">
      <slot name="title">{{ title }}</slot>
    </div>
    <div class="mt-4 grow space-y-3 text-[10pt]">
      <slot />
    </div>
  </div>
</template>

<style scoped></style>
