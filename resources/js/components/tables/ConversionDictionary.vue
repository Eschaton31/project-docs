<script setup>
import { isArray } from 'lodash'

import { computed } from 'vue'

import { sizeAttrs } from '@/composables/sizing.js'

const { widths } = defineProps({
  data: Array,
  mono: Boolean,
  // CSS widths or Tailwind width classes, one per column
  widths: Array,
})

const width1 = computed(() => sizeAttrs(widths ? widths[0] : '30%', 'w-', 'width'))
const width2 = computed(() => sizeAttrs(widths ? widths[1] : '40%', 'w-', 'width'))
const width3 = computed(() => sizeAttrs(widths ? widths[2] : '30%', 'w-', 'width'))
</script>

<template>
  <table
    class="h-auto w-full table-fixed border-separate border-spacing-0 break-inside-auto rounded-md border border-solid border-[#b7b7b7] box-decoration-clone text-[8.5pt]">
    <thead class="table-header-group">
      <tr class="group break-inside-avoid-page bg-gray-300 font-bold">
        <th v-bind="width1" class="px-2 py-1 text-center group-first:rounded-tl-md">Variable</th>
        <th v-bind="width2" class="border-l border-solid border-[#b7b7b7] px-2 py-1 text-left">Description</th>
        <th
          v-bind="width3"
          class="border-l border-solid border-[#b7b7b7] px-2 py-1 text-left group-first:rounded-tr-md">
          Formats
        </th>
      </tr>
    </thead>

    <tbody>
      <template v-for="({ item, description, formats }, index) in data" :key="index">
        <tr class="row-block group border-t border-solid border-[#b7b7b7] even:bg-gray-100">
          <td :class="['px-2 py-1 text-center font-bold group-last:rounded-bl-md']">
            <span :class="{ 'font-mono text-[.65rem]': mono }">
              <slot name="cell" :row="index" column="item" :value="item"><span v-html="item" /></slot>
            </span>
          </td>
          <td class="border-l border-solid border-[#b7b7b7] px-2 py-1">
            <slot name="cell" :row="index" column="description" :value="description">
              <template v-if="isArray(description)">
                <div v-for="(value, idx) in description" :key="idx" v-html="value" />
              </template>
              <span v-else v-html="description" />
            </slot>
          </td>
          <td class="border-l border-solid border-[#b7b7b7] px-2 py-1 group-last:rounded-br-md">
            <slot name="cell" :row="index" column="formats" :value="formats">
              <template v-if="isArray(formats)">
                <div v-for="(value, idx) in formats" :key="idx" v-html="value" />
              </template>
              <span v-else v-html="formats" />
            </slot>
          </td>
        </tr>
      </template>
    </tbody>
  </table>
</template>
