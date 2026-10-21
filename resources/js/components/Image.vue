<script setup>
defineProps({
  src: String,
  description: String,
  height: String,
})
</script>

<template>
  <div class="my-4 flex flex-col items-center justify-center space-y-1">
    <img :class="['mx-auto w-auto object-contain', height ?? 'h-36']" :src alt="" />
    <div class="text-[10pt] italic">{{ description }}</div>
  </div>
</template>
