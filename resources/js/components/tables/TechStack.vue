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
          <td class="px-2 py-1 text-center">
            <slot name="cell" :row="index" column="component" :value="component"><span v-html="component" /></slot>
          </td>
          <td class="border-l border-r border-solid border-[#b7b7b7] px-2 py-1 text-center">
            <slot name="cell" :row="index" column="tech" :value="tech">
              <template v-if="isArray(tech)">
                <div v-for="(value, idx) in tech" :key="idx" v-html="value" />
              </template>
              <span v-else v-html="tech" />
            </slot>
          </td>
          <td class="px-2 py-1 text-center">
            <slot name="cell" :row="index" column="framework" :value="framework">
              <template v-if="isArray(framework)">
                <div v-for="(value, idx) in framework" :key="idx" v-html="value" />
              </template>
              <span v-else v-html="framework" />
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
