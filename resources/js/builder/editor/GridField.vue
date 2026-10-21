<script setup>
import { computed, ref } from 'vue'

import { parseDelimited } from '../editing.js'

/**
 * Table rows as a grid. With columns, each row is an object with those keys ('lines' cells become an array
 * when they have several lines). Without columns (SQL results), the columns come from the rows and can be
 * renamed, added and removed; an empty cell is NULL.
 */
const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  columns: { type: Array, default: null },
  readonly: Boolean,
})
const emit = defineEmits(['update:modelValue'])

const rows = computed(() => props.modelValue ?? [])
const dynamic = computed(() => !props.columns)
const keys = computed(() =>
  dynamic.value ? [...new Set(rows.value.flatMap((row) => Object.keys(row ?? {})))] : props.columns.map((c) => c.key)
)
const kindOf = (key) => props.columns?.find((c) => c.key === key)?.kind ?? 'text'
const labelOf = (key) => props.columns?.find((c) => c.key === key)?.label ?? key

const pasting = ref(false)
const pasted = ref('')

function cellText(row, key) {
  const value = row?.[key]
  if (Array.isArray(value)) return value.join('\n')

  return value == null ? '' : String(value)
}

function toValue(key, text) {
  if (dynamic.value) return text === '' ? null : text
  if (kindOf(key) === 'lines' && text.includes('\n')) return text.split('\n')

  return text
}

const emptyRow = () => Object.fromEntries(keys.value.map((key) => [key, dynamic.value ? null : '']))

function update(next) {
  emit('update:modelValue', next)
}

function setCell(index, key, text) {
  update(rows.value.map((row, i) => (i === index ? { ...row, [key]: toValue(key, text) } : row)))
}

function moveRow(index, by) {
  const next = [...rows.value]
  const [row] = next.splice(index, 1)
  next.splice(index + by, 0, row)
  update(next)
}

const addRow = () => update([...rows.value, emptyRow()])
const removeRow = (index) => update(rows.value.filter((_, i) => i !== index))

function renameColumn(oldKey, newKey) {
  newKey = newKey.trim()
  if (!newKey || newKey === oldKey || keys.value.includes(newKey)) return
  update(
    rows.value.map((row) => Object.fromEntries(Object.entries(row).map(([k, v]) => [k === oldKey ? newKey : k, v])))
  )
}

function addColumn() {
  let name = 'column'
  for (let n = 2; keys.value.includes(name); n++) name = `column_${n}`
  update((rows.value.length ? rows.value : [{}]).map((row) => ({ ...row, [name]: null })))
}

function removeColumn(key) {
  update(rows.value.map((row) => Object.fromEntries(Object.entries(row).filter(([k]) => k !== key))))
}

/** Rows pasted from a spreadsheet or SQL client; with free columns the first line is the header. */
function applyPaste(append) {
  let lines = parseDelimited(pasted.value)
  if (!lines.length) return

  let incoming
  if (dynamic.value) {
    const [header, ...body] = lines
    incoming = body.map((cells) => Object.fromEntries(header.map((key, i) => [key, toValue(key, cells[i] ?? '')])))
  } else {
    const labels = props.columns.map((c) => c.label.toLowerCase())
    if (lines[0].every((cell, i) => cell.trim().toLowerCase() === labels[i] || cell.trim() === keys.value[i])) {
      lines = lines.slice(1)
    }
    incoming = lines.map((cells) => Object.fromEntries(keys.value.map((key, i) => [key, toValue(key, cells[i] ?? '')])))
  }

  update(append ? [...rows.value, ...incoming] : incoming)
  pasted.value = ''
  pasting.value = false
}
</script>

<template>
  <div class="space-y-2">
    <div class="overflow-x-auto rounded border border-slate-300">
      <table class="w-full border-collapse text-xs">
        <thead class="bg-slate-100 text-left text-slate-600">
          <tr>
            <th v-for="key in keys" :key="key" class="min-w-28 border-b border-slate-300 px-1 py-1 font-semibold">
              <div v-if="dynamic" class="flex items-center gap-1">
                <input
                  :readonly
                  :value="key"
                  class="w-full rounded border border-transparent bg-transparent px-1 font-mono hover:border-slate-300"
                  @change="renameColumn(key, $event.target.value)" />
                <button
                  v-if="!readonly"
                  class="text-slate-400 hover:text-rose-700"
                  title="Remove column"
                  type="button"
                  @click="removeColumn(key)">
                  ✕
                </button>
              </div>
              <span v-else class="px-1">{{ labelOf(key) }}</span>
            </th>
            <th class="w-16 border-b border-slate-300" />
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in rows" :key="index" class="border-b border-slate-200 align-top last:border-b-0">
            <td v-for="key in keys" :key="key" class="p-0.5">
              <textarea
                :placeholder="dynamic ? 'NULL' : ''"
                :readonly
                :value="cellText(row, key)"
                class="block w-full resize-none rounded border border-transparent px-1 py-0.5 [field-sizing:content] hover:border-slate-200 focus:border-bene-teal focus:outline-none"
                rows="1"
                @input="setCell(index, key, $event.target.value)" />
            </td>
            <td class="whitespace-nowrap px-1 text-slate-400">
              <template v-if="!readonly">
                <button
                  :disabled="index === 0"
                  class="px-0.5 hover:text-slate-700 disabled:opacity-30"
                  title="Move up"
                  type="button"
                  @click="moveRow(index, -1)">
                  ↑
                </button>
                <button
                  :disabled="index === rows.length - 1"
                  class="px-0.5 hover:text-slate-700 disabled:opacity-30"
                  title="Move down"
                  type="button"
                  @click="moveRow(index, 1)">
                  ↓
                </button>
                <button class="px-0.5 hover:text-rose-700" title="Remove row" type="button" @click="removeRow(index)">
                  ✕
                </button>
              </template>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td :colspan="keys.length + 1" class="px-2 py-3 text-center text-slate-400">No rows yet.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="!readonly" class="flex flex-wrap gap-1.5">
      <button class="toolbar-button" type="button" @click="addRow">Add row</button>
      <button v-if="dynamic" class="toolbar-button" type="button" @click="addColumn">Add column</button>
      <button class="toolbar-button" type="button" @click="pasting = !pasting">Paste rows…</button>
    </div>

    <div v-if="pasting" class="space-y-1.5 rounded border border-slate-200 bg-slate-50 p-2">
      <p class="text-[11px] text-slate-600">
        Paste from a spreadsheet or SQL client (tab-separated) or CSV.
        <template v-if="dynamic">The first line is the column names.</template>
        <template v-else>Columns in order: {{ columns.map((c) => c.label).join(', ') }}.</template>
      </p>
      <textarea v-model="pasted" class="field-input min-h-24 font-mono text-xs" spellcheck="false" />
      <div class="flex gap-1.5">
        <button :disabled="!pasted.trim()" class="toolbar-button" type="button" @click="applyPaste(false)">
          Replace rows
        </button>
        <button :disabled="!pasted.trim()" class="toolbar-button" type="button" @click="applyPaste(true)">
          Add to rows
        </button>
      </div>
    </div>
  </div>
</template>
