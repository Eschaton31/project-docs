<script setup>
import { isArray } from 'lodash'

defineProps({
  data: Array,
})
</script>

<template>
  <div class="overflow-hidden rounded-md border border-solid border-[#b7b7b7] text-[8.5pt]">
    <table class="h-full w-full table-fixed border-collapse">
      <thead>
        <tr class="rounded-md bg-gray-300 font-bold">
          <th class="w-[17%] px-2 py-1">Field</th>
          <th class="border-l border-r border-solid border-gray-200 px-2 py-1">Description</th>
          <th class="w-[14%] border-l border-r border-solid border-gray-200 px-2 py-1">Data Type</th>
          <th class="w-[30%] px-2 py-1">Possible Values</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="({ field, description, type, values }, index) in data" :key="index" class="even:bg-gray-100">
          <td class="w-1/5 px-2 py-1 text-center font-mono font-bold" v-html="field" />
          <td class="border-l border-r border-solid border-[#b7b7b7] px-2 py-1" v-html="description" />
          <td class="border-l border-r border-solid border-[#b7b7b7] px-2 py-1" v-html="type" />
          <td class="px-2 py-1">
            <template v-if="isArray(values)">
              <div v-for="(value, index) in values" :key="index" v-html="value" />
            </template>
            <span v-else v-html="values" />
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
