<script setup>
import { isArray } from 'lodash'

defineProps({
  table: Array,
})
</script>

<template>
  <table class="w-full table-fixed border-collapse">
    <tbody>
      <tr v-for="{ id, value } in table" :key="id" class="w-full text-[10pt] text-bene-gray">
        <td class="w-1/4 border border-solid border-[#b7b7b7] px-3 py-2 font-bold">{{ id }}</td>
        <td
          class="w-3/4 border border-solid border-[#b7b7b7] px-3 py-2 font-normal"
          v-html="isArray(value) ? value.join('<br />') : value"></td>
      </tr>
    </tbody>
  </table>
</template>

<style scoped></style>
