<script setup>
import { isArray } from 'lodash'

defineProps({
  list: Array,
  // position of this (sub-)list within the top-level list, for editing items in place
  path: { type: Array, default: () => [] },
})
</script>

<template>
  <ul class="list-outside list-disc space-y-1 pl-6" role="list">
    <template v-for="(item, key) in list" :key="key">
      <li class="pl-2 text-bene-gray">
        <template v-if="isArray(item)">
          <BulletList :list="item" :path="[...path, key]">
            <template v-if="$slots.item" #item="scope"><slot name="item" v-bind="scope" /></template>
          </BulletList>
        </template>
        <slot v-else name="item" :path="[...path, key]" :value="item"><span v-html="item"></span></slot>
      </li>
    </template>
  </ul>
</template>
