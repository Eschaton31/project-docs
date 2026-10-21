<script setup>
import { isArray } from 'lodash'

import { computed } from 'vue'

const { widths } = defineProps({
  data: Array,
  mono: Boolean,
  widths: Array,
})

const width1 = computed(() => (widths ? widths[0] : 'w-[30%]'))
const width2 = computed(() => (widths ? widths[1] : 'w-[40%]'))
const width3 = computed(() => (widths ? widths[2] : 'w-[30%]'))
</script>

<template>
  <table
    class="h-auto w-full table-fixed border-separate border-spacing-0 break-inside-auto rounded-md border border-solid border-[#b7b7b7] box-decoration-clone text-[8.5pt]">
    <thead class="table-header-group">
      <tr class="group break-inside-avoid-page bg-gray-300 font-bold">
        <th :class="[width1, 'px-2 py-1 text-center group-first:rounded-tl-md']">Variable</th>
        <th :class="[width2, 'border-l border-solid border-[#b7b7b7] px-2 py-1 text-left']">Description</th>
        <th :class="[width3, 'border-l border-solid border-[#b7b7b7] px-2 py-1 text-left group-first:rounded-tr-md']">
          Formats
        </th>
      </tr>
    </thead>

    <tbody>
      <template v-for="({ item, description, formats }, index) in data" :key="index">
        <tr class="row-block group border-t border-solid border-[#b7b7b7] even:bg-gray-100">
          <td :class="['px-2 py-1 text-center font-bold group-last:rounded-bl-md']">
            <span :class="{ 'font-mono text-[.65rem]': mono }" v-html="item" />
          </td>
          <td class="border-l border-solid border-[#b7b7b7] px-2 py-1">
            <template v-if="isArray(description)">
              <div v-for="(value, idx) in description" :key="idx" v-html="value" />
            </template>
            <span v-else v-html="description" />
          </td>
          <td class="border-l border-solid border-[#b7b7b7] px-2 py-1 group-last:rounded-br-md">
            <template v-if="isArray(formats)">
              <div v-for="(value, idx) in formats" :key="idx" v-html="value" />
            </template>
            <span v-else v-html="formats" />
          </td>
        </tr>
      </template>
    </tbody>
  </table>
</template>
