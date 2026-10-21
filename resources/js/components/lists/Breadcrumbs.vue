<script setup>
import { ChevronRightIcon } from 'lucide-vue-next'

defineProps({
  list: Array,
})
</script>

<template>
  <span class="inline-flex w-fit items-center">
    <template v-for="(item, index) in list" :key="index">
      <codetext class="flex"
        ><slot name="item" :path="[index]" :value="item">{{ item }}</slot></codetext
      >
      <ChevronRightIcon v-if="index + 1 < list.length" size="12" />
    </template>
  </span>
</template>

<style scoped></style>
