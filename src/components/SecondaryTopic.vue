<script setup>
defineProps({
  title: String,
  start: String,
  id: String,
})
</script>

<template>
  <ol :start class="list-outside list-[upper-roman] space-y-3 pl-6" role="list">
    <li :id class="secondary-topic pl-4 text-[13.5pt] font-bold text-bene-teal underline">
      {{ title }}
    </li>
    <li class="list-none space-y-3 pl-4 text-bene-gray">
      <slot />
    </li>
  </ol>
</template>

<style scoped></style>
