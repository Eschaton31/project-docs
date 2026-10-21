<script setup>
import { isArray } from 'lodash'

import { computed } from 'vue'

import { sizeAttrs } from '@/composables/sizing.js'

const { widths } = defineProps({
  data: Array,
  itemName: String,
  descriptionName: String,
  mono: Boolean,
  left: Boolean,
  // CSS widths (['35%', '65%']) or Tailwind width classes (['w-[35%]', 'w-[65%]'])
  widths: Array,
})

const width1 = computed(() => sizeAttrs(widths ? widths[0] : '35%', 'w-', 'width'))
const width2 = computed(() => sizeAttrs(widths ? widths[1] : '65%', 'w-', 'width'))
</script>

<template>
  <table
    class="h-auto w-full table-fixed border-separate border-spacing-0 break-inside-auto rounded-md border border-solid border-[#b7b7b7] box-decoration-clone text-[8.5pt]">
    <thead class="table-header-group">
      <tr class="group break-inside-avoid-page bg-gray-300 font-bold">
        <th v-bind="width1" :class="[left ? 'text-left' : 'text-center', 'px-2 py-1 group-first:rounded-tl-md']">
          {{ itemName ?? 'Item' }}
        </th>
        <th
          v-bind="width2"
          class="border-l border-solid border-[#b7b7b7] px-2 py-1 text-left group-first:rounded-tr-md">
          {{ descriptionName ?? 'Description' }}
        </th>
      </tr>
    </thead>

    <tbody>
      <template v-for="({ item, description }, index) in data" :key="index">
        <tr class="row-block group border-t border-solid border-[#b7b7b7] even:bg-gray-100">
          <td :class="[left ? 'text-left' : 'text-center', 'px-2 py-1 font-bold group-last:rounded-bl-md']">
            <span :class="{ 'font-mono text-[.65rem]': mono }">
              <slot name="cell" :row="index" column="item" :value="item"><span v-html="item" /></slot>
            </span>
          </td>
          <td class="border-l border-solid border-[#b7b7b7] px-2 py-1 group-last:rounded-br-md">
            <slot name="cell" :row="index" column="description" :value="description">
              <template v-if="isArray(description)">
                <div v-for="(value, idx) in description" :key="idx" v-html="value" />
              </template>
              <span v-else v-html="description" />
            </slot>
          </td>
        </tr>
      </template>
    </tbody>
  </table>
</template>
