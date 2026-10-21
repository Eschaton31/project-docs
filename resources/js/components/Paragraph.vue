<script setup>
defineProps({
  // tab in the first line; text blocks (RichText, display: contents) flow straight into this box, so
  // only the paragraph's first line is indented, and other blocks inside (lists, …) are reset
  indent: Boolean,
})
</script>

<template>
  <div
    :class="[
      'space-y-1 text-justify text-[10pt] text-bene-gray',
      { 'indent-[0.5in] [&>:not(.contents)]:indent-0': indent },
    ]">
    <slot />
  </div>
</template>

<style scoped></style>
