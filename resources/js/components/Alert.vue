<script setup>
import { InfoIcon, TriangleAlertIcon, WrenchIcon, XCircleIcon } from 'lucide-vue-next'

import { computed, useSlots } from 'vue'

const props = defineProps({
  type: String,
  title: String,
})

const bgColor = computed(() => {
  if (props.type === 'warn') {
    return 'bg-yellow-50'
  }

  if (props.type === 'success') {
    return 'bg-green-100'
  }

  if (props.type === 'error') {
    return 'bg-red-50'
  }

  return 'bg-[#edf3fd]'
})
const textColor = computed(() => {
  if (props.type === 'warn') {
    return 'text-yellow-700'
  }

  if (props.type === 'success') {
    return 'text-green-700'
  }

  if (props.type === 'error') {
    return 'text-red-700'
  }

  return 'text-[#5287da]'
})
const slots = useSlots()
</script>
<template>
  <div :class="['rounded-md p-2', bgColor]">
    <div class="flex gap-2 text-[10pt]">
      <div class="flex-shrink-0 pt-[0.125rem]">
        <TriangleAlertIcon v-if="type === 'warn'" aria-hidden="true" class="size-4 text-white" fill="#facc15" />
        <WrenchIcon v-else-if="type === 'success'" aria-hidden="true" class="size-4 text-green-400" />
        <XCircleIcon v-else-if="type === 'error'" aria-hidden="true" class="text-red-400 size-4" />
        <InfoIcon v-else aria-hidden="true" class="size-4 text-white" fill="#4786eb" />
      </div>
      <div :class="[textColor, 'flex-1 space-y-1']">
        <span class="alert-title font-bold"
          ><slot name="title"><span v-html="title" /></slot
        ></span>
        <p class="pr-2 text-justify">
          <slot></slot>
        </p>
        <p v-if="slots.link">
          <slot name="link"></slot>
        </p>
      </div>
    </div>
  </div>
</template>
