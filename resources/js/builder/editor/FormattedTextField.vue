<script setup>
import { ref, watch } from 'vue'

import { conceptsToText, listToText, textToConcepts, textToList, textToTree, treeToText } from '../editing.js'

/**
 * Structured values written as indented text:
 * list (sub-lists by indenting), lines (one string per line), concepts (“Term: definition”),
 * tree (“type name | note”). The value is only emitted as parsed data.
 */
const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  format: { type: String, required: true },
  readonly: Boolean,
  id: String,
})
const emit = defineEmits(['update:modelValue'])

const FORMATS = {
  list: { toText: listToText, fromText: textToList },
  lines: {
    toText: (list) => (list ?? []).join('\n'),
    fromText: (text) =>
      text
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean),
  },
  concepts: { toText: conceptsToText, fromText: textToConcepts },
  tree: { toText: treeToText, fromText: textToTree },
}

const { toText, fromText } = FORMATS[props.format]
const text = ref(toText(props.modelValue))
const focused = ref(false)

// pick up outside changes (undo, someone else's save) unless the user is typing here
watch(
  () => props.modelValue,
  (value) => {
    if (!focused.value) text.value = toText(value)
  }
)

function onInput(event) {
  text.value = event.target.value
  emit('update:modelValue', fromText(text.value))
}
</script>

<template>
  <textarea
    :id
    :class="['field-input', format === 'lines' ? 'min-h-20' : 'min-h-32', format === 'tree' ? 'font-mono text-xs' : '']"
    :readonly
    :value="text"
    spellcheck="false"
    @blur="focused = false"
    @focus="focused = true"
    @input="onInput" />
</template>
