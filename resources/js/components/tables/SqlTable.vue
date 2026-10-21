<script setup>
import { computed } from 'vue'

const { data } = defineProps({
  data: Array,
  description: String,
  dark: Boolean,
})

const headers = computed(() => Object.keys(data[0]))
</script>

<template>
  <div class="flex flex-col items-center justify-center space-y-1">
    <table
      :class="[
        'w-full table-fixed border-separate border-spacing-0 rounded-md border-b border-t border-solid font-mono text-[8pt]',
        dark ? 'border-[#1e1f22] text-gray-200' : 'border-[#f5f8fe] text-[#080808]',
      ]">
      <slot />
      <thead>
        <tr :class="['group', dark ? 'bg-[#2e2f32]' : 'bg-[#efefef]']">
          <th
            v-for="header in headers"
            :key="header"
            :class="[
              'border-l border-r px-2 py-1.5 text-left group-first:first:rounded-tl-md group-first:last:rounded-tr-md',
              dark ? 'border-[#1e1f22]' : 'border-[#f5f8fe]',
            ]">
            {{ header }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(row, index) in data"
          :key="index"
          :class="['group', dark ? 'odd:bg-[#26282e] even:bg-[#1e1f22]' : 'odd:bg-[#ffffff] even:bg-[#f5f8fe]']">
          <td
            v-for="(cell, col) in row"
            :key="col"
            :class="[
              'border-l border-r px-2 py-1.5 group-last:first:rounded-bl-md group-last:last:rounded-br-md',
              dark ? 'border-[#313438]' : 'border-[#ebecf0]',
            ]">
            <span v-if="cell == null" :class="[dark ? 'text-[#6c7d77]' : 'text-[#8c8c8c]']">
              <i>&lt;null&gt;</i>
            </span>
            <span v-else>{{ cell }}</span>
          </td>
        </tr>
      </tbody>
    </table>
    <div class="italic"><span v-html="description" /></div>
  </div>
</template>
