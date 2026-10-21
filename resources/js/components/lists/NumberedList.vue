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

// a sub-list doesn't take the bold of the list around it
const weight = computed(() => (props.bold ? 'font-bold' : props.path.length ? 'font-normal' : ''))

// 1 2 3, then a b c, then i ii iii; deeper levels start over
const MARKERS = ['list-decimal', 'list-[lower-alpha]', 'list-[lower-roman]']
const marker = computed(() => MARKERS[props.path.length % MARKERS.length])

// numbered explicitly: a sub-list with no item before it takes up a list item but not a number
const entries = computed(() => {
  let number = 0

  return listEntries(props.list, props.path).map((entry) => ({ ...entry, number: entry.path ? ++number : null }))
})
</script>

<template>
  <ol :class="['list-outside space-y-1 pl-6', marker, weight]" role="list">
    <li
      v-for="entry in entries"
      :key="entry.key"
      :class="['pl-2 text-bene-gray', { 'list-none': !entry.path }]"
      :value="entry.number ?? undefined">
      <slot v-if="entry.path" :path="entry.path" :value="entry.value" name="item"
        ><span v-html="entry.value"></span
      ></slot>
      <NumberedList
        v-if="entry.children"
        :bold="entry.children.bold"
        :class="{ 'mt-1': entry.path }"
        :list="entry.children.list"
        :path="entry.children.path">
        <template v-if="$slots.item" #item="scope"><slot name="item" v-bind="scope" /></template>
      </NumberedList>
    </li>
  </ol>
</template>
