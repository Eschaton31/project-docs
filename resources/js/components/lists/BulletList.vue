<script setup>
import { computed } from 'vue'

import { listEntries } from './listEntries.js'

const props = defineProps({
  list: Array,
  // position of this (sub-)list within the top-level list, for editing items in place
  path: { type: Array, default: () => [] },
  // this list's own items in bold; each sub-list has its own setting
  bold: Boolean,
})

const entries = computed(() => listEntries(props.list, props.path))
// a sub-list doesn't take the bold of the list around it
const weight = computed(() => (props.bold ? 'font-bold' : props.path.length ? 'font-normal' : ''))
</script>

<template>
  <ul :class="['list-outside list-disc space-y-1 pl-6', weight]" role="list">
    <li v-for="entry in entries" :key="entry.key" :class="['pl-2 text-bene-gray', { 'list-none': !entry.path }]">
      <slot v-if="entry.path" name="item" :path="entry.path" :value="entry.value"
        ><span v-html="entry.value"></span
      ></slot>
      <BulletList
        v-if="entry.children"
        :bold="entry.children.bold"
        :class="{ 'mt-1': entry.path }"
        :list="entry.children.list"
        :path="entry.children.path">
        <template v-if="$slots.item" #item="scope"><slot name="item" v-bind="scope" /></template>
      </BulletList>
    </li>
  </ul>
</template>
