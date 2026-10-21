<script setup>
import { isArray } from 'lodash'

defineProps({
  table: Array,
})
</script>

<template>
  <table class="w-full table-fixed border-collapse">
    <tbody>
      <tr v-for="({ id, value }, index) in table" :key="index" class="w-full text-[10pt] text-bene-gray">
        <td class="w-1/4 border border-solid border-[#b7b7b7] px-3 py-2 font-bold">
          <slot name="cell" :row="index" column="id" :value="id">{{ id }}</slot>
        </td>
        <td class="w-3/4 border border-solid border-[#b7b7b7] px-3 py-2 font-normal">
          <slot name="cell" :row="index" column="value" :value="value">
            <span v-html="isArray(value) ? value.join('<br />') : value" />
          </slot>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<style scoped></style>
