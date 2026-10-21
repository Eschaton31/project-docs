<script setup>
import { isArray } from 'lodash'
import { computed } from 'vue'

const props = defineProps({
  data: Array,
})

/**
 * Rows grouped by functionality: a row with the same functionality as the row above joins its group, and
 * the group's functionality cell spans all of its rows. Each row keeps its index in `data` for the cell slot.
 */
const groups = computed(() =>
  (props.data ?? []).reduce((result, row, index) => {
    if (!result.length || row.functionality !== result.at(-1).functionality) {
      result.push({ functionality: row.functionality, start: index, rows: [] })
    }
    result.at(-1).rows.push({ ...row, index })

    return result
  }, [])
)
</script>

<template>
  <div class="overflow-hidden rounded-md border border-solid border-[#b7b7b7] text-[8.5pt]">
    <table class="w-full table-fixed border-collapse">
      <colgroup>
        <col class="w-[30%]" />
        <col class="w-[35%]" />
        <col class="w-[35%]" />
      </colgroup>
      <thead>
        <tr class="rounded-md bg-gray-300 font-bold">
          <th class="px-2 py-1">Functionality</th>
          <th class="border-l border-r border-solid border-gray-200 px-2 py-1">Details</th>
          <th class="px-2 py-1">Remarks</th>
        </tr>
      </thead>
      <tbody v-for="(group, groupIndex) in groups" :key="group.start" :class="{ 'bg-gray-100': groupIndex % 2 === 1 }">
        <tr
          v-for="({ details, remarks, index }, rowIndex) in group.rows"
          :key="index"
          :class="rowIndex === 0 ? 'border-t border-solid border-[#b7b7b7]' : 'border-t border-solid border-gray-200'">
          <td v-if="rowIndex === 0" :rowspan="group.rows.length" class="px-2 py-1 align-top">
            <slot name="cell" :row="group.start" column="functionality" :value="group.functionality">
              <span v-html="group.functionality" />
            </slot>
          </td>
          <td class="border-l border-r border-solid border-[#b7b7b7] px-2 py-1">
            <slot name="cell" :row="index" column="details" :value="details">
              <template v-if="isArray(details)">
                <div v-for="(value, idx) in details" :key="idx" v-html="value" />
              </template>
              <span v-else v-html="details" />
            </slot>
          </td>
          <td class="px-2 py-1">
            <slot name="cell" :row="index" column="remarks" :value="remarks">
              <template v-if="isArray(remarks)">
                <div v-for="(value, idx) in remarks" :key="idx" v-html="value" />
              </template>
              <span v-else v-html="remarks" />
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
