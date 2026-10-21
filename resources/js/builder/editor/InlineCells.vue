<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue'

import { registry } from '../registry.js'
import InlineCell from './InlineCell.vue'
import { placeCaret } from './caret.js'
import { addressKey, append, insertAfter, readAt, removeAt, writeAt } from './inlineData.js'

/**
 * A table or list edited in place: the same component, with every cell/item swapped for an editable one
 * through its scoped slots. Tab moves between cells; Enter adds a list item or goes down a row;
 * Backspace on an empty item removes it; Escape or clicking elsewhere finishes.
 */
const props = defineProps({
  node: { type: Object, required: true },
  // the INLINE_EDITABLE rule for this type (see inline.js)
  rule: { type: Object, required: true },
  coords: { type: Object, default: null },
})
const emit = defineEmits(['update', 'done'])

const root = useTemplateRef('root')
const definition = computed(() => registry[props.node.type])
const shape = computed(() => props.rule.shape)
const isTable = computed(() => shape.value === 'table')
const value = computed(() => props.node.props?.[props.rule.prop] ?? [])

// the cell with the caret, and the one to move it to after a change that re-renders the list
const active = ref(null)
const focusKey = ref(null)
let finishTimer = null

const attrs = computed(() => {
  const result = {}
  for (const prop of definition.value.props) {
    if (props.node.props?.[prop] !== undefined) result[prop] = props.node.props[prop]
  }
  if (props.node.options?.anchorId) result.id = props.node.options.anchorId

  return result
})

const save = (next) => emit('update', next)

function cell(address, cellValue) {
  const field = address.field
  const rule = props.rule

  return {
    value: cellValue,
    lines: isTable.value && (rule.lines ?? []).includes(field),
    plain: rule.plain || (rule.plainFields ?? []).includes(field),
    nullable: rule.nullable,
    block: isTable.value,
    focus: focusKey.value === addressKey(address),
    onFocused: () => {
      active.value = address
      if (focusKey.value === addressKey(address)) focusKey.value = null
    },
    onUpdate: (next) => save(writeAt(value.value, shape.value, address, next)),
    onEnter: () => enter(address),
    onRemoveEmpty: () => removeEmpty(address),
    onDone: () => emit('done'),
  }
}

function moveTo(address) {
  focusKey.value = address ? addressKey(address) : null
}

function enter(address) {
  if (isTable.value) {
    const below = { path: [address.path[0] + 1], field: address.field }
    if (address.path[0] + 1 < value.value.length) moveTo(below)

    return
  }
  // in a concept list, Enter goes from the term to its definition first
  if (shape.value === 'concepts' && address.field === 'id') return moveTo({ path: address.path, field: 'value' })

  const result = insertAfter(value.value, shape.value, address, { nullable: props.rule.nullable })
  save(result.value)
  moveTo(result.address)
}

function removeEmpty(address) {
  if (isTable.value) return
  if (shape.value === 'concepts') {
    const item = readAt(value.value, 'concepts', { path: address.path, field: null })
    if (item?.id || item?.value || item?.children?.length) return
  }
  remove(address)
}

function remove(address = active.value) {
  if (!address) return
  const result = removeAt(value.value, shape.value, address)
  save(result.value)
  active.value = null
  moveTo(result.address)
}

function add() {
  const result = active.value
    ? insertAfter(value.value, shape.value, active.value, { nullable: props.rule.nullable })
    : append(value.value, shape.value, { nullable: props.rule.nullable, columns: props.rule.columns })
  save(result.value)
  moveTo(result.address)
}

// finished when focus has left the table/list (checked a moment later: re-renders move focus too)
function onFocusOut() {
  clearTimeout(finishTimer)
  finishTimer = setTimeout(() => {
    if (root.value && !root.value.contains(document.activeElement) && !focusKey.value) emit('done')
  }, 150)
}

onMounted(async () => {
  await nextTick()
  const cells = root.value?.querySelectorAll('[data-inline-cell]') ?? []
  const clicked =
    props.coords && document.elementFromPoint(props.coords.x, props.coords.y)?.closest('[data-inline-cell]')
  const target = clicked && root.value.contains(clicked) ? clicked : cells[0]
  if (target) {
    target.focus({ preventScroll: true })
    placeCaret(target, clicked ? props.coords : null)
  }
  // only now: the click position is in screen coordinates, which scrolling would invalidate
  root.value?.scrollIntoView({ block: 'nearest' })
})
onBeforeUnmount(() => clearTimeout(finishTimer))

const noun = computed(() => (isTable.value ? 'row' : 'item'))
</script>

<template>
  <div ref="root" class="relative scroll-mt-12" data-inline-editor @focusout="onFocusOut">
    <!-- mousedown.prevent keeps the caret in the cell while using the toolbar -->
    <div
      class="absolute bottom-full left-0 z-10 mb-1 flex gap-0.5 rounded border border-slate-200 bg-white p-0.5 font-sans text-xs shadow-sm"
      @mousedown.prevent>
      <button class="rt-button" :title="`Add a ${noun} after the current one`" type="button" @click="add">
        Add {{ noun }}
      </button>
      <button :disabled="!active" class="rt-button" type="button" @click="remove()">Remove {{ noun }}</button>
      <button class="rt-button" title="Done (Esc)" type="button" @click="emit('done')">Done</button>
    </div>

    <component :is="definition.component" v-bind="attrs">
      <template #cell="{ row, column, value: cellValue }">
        <InlineCell v-bind="cell({ path: [row], field: column }, cellValue)" />
      </template>
      <template #item="{ path, value: itemValue }">
        <InlineCell v-bind="cell({ path, field: null }, itemValue)" />
      </template>
      <template #term="{ path, value: term }">
        <InlineCell v-bind="cell({ path, field: 'id' }, term)" />
      </template>
      <template #definition="{ path, value: meaning }">
        <InlineCell v-bind="cell({ path, field: 'value' }, meaning)" />
      </template>
      <template #name="{ path, value: name }">
        <InlineCell v-bind="cell({ path, field: 'name' }, name)" />
      </template>
      <template #note="{ path, value: note }">
        <InlineCell v-bind="cell({ path, field: 'note' }, note)" />
      </template>
    </component>
  </div>
</template>
