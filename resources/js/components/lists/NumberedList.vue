<script setup>
import { isArray } from 'lodash'

defineProps({
  list: Array,
})
</script>

<template>
  <ul class="list-outside list-decimal space-y-1 pl-6" role="list">
    <template v-for="(item, key) in list" :key="key">
      <li :value="key+1" class="pl-2 text-bene-gray">
        <template v-if="isArray(item)">
          <NumberedList :list="item" />
        </template>
        <span v-else v-html="item"></span>
      </li>
    </template>
  </ul>
</template>
