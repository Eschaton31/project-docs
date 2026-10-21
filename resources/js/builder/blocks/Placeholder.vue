<script setup>
defineProps({
  text: String,
})
</script>

<template>
  <div class="text-justify text-[10pt] italic text-gray-400">
    [<slot>{{ text || 'to be written' }}</slot
    >]
  </div>
</template>
