<script setup>
defineProps({
  html: String,
})
</script>

<template>
  <!-- display: contents keeps the HTML laid out as if it were written straight into the parent's slot,
       so spacing utilities on the parent (space-y-*) behave exactly as on the hand-built pages -->
  <div class="contents" v-html="html" />
</template>
