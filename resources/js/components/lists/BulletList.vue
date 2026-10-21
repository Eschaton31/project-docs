<script setup>
import { isArray } from 'lodash'

defineProps({
  list: Array,
})
</script>

<template>
  <ul class="list-outside list-disc space-y-1 pl-6" role="list">
    <template v-for="(item, key) in list" :key="key">
      <li class="pl-2 text-bene-gray">
        <template v-if="isArray(item)">
          <BulletList :list="item" />
        </template>
        <span v-else v-html="item"></span>
      </li>
    </template>
  </ul>
</template>
