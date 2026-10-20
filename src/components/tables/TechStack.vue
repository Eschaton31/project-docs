<script setup>
import { isArray } from 'lodash'

defineProps({
  data: Array,
})
</script>

<template>
  <div class="overflow-hidden rounded-md border border-solid border-[#b7b7b7] text-[8.5pt]">
    <table class="w-full table-fixed border-collapse">
      <thead>
        <tr class="rounded-md bg-gray-300 font-bold">
          <th class="px-2 py-1">Component</th>
          <th class="border-l border-r border-solid border-gray-200 px-2 py-1">Technology</th>
          <th class="px-2 py-1">Framework</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="({ component, tech, framework }, index) in data" :key="index" class="even:bg-gray-100">
          <td class="px-2 py-1 text-center" v-html="component" />
          <td class="border-l border-r border-solid border-[#b7b7b7] px-2 py-1 text-center">
            <template v-if="isArray(tech)">
              <div v-for="(value, index) in tech" :key="index" v-html="value" />
            </template>
            <span v-else v-html="tech" />
          </td>
          <td class="px-2 py-1 text-center">
            <template v-if="isArray(framework)">
              <div v-for="(value, index) in framework" :key="index" v-html="value" />
            </template>
            <span v-else v-html="framework" />
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
