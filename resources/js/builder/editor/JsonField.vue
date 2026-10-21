<script setup>
import { ref, watch } from 'vue'

/**
 * Structured values (table rows, concept lists, file trees) until they get dedicated editors.
 * Only valid JSON is emitted; while the text is invalid the last good value stays in place.
 */
const props = defineProps({
  modelValue: { default: null },
  id: String,
})
const emit = defineEmits(['update:modelValue'])

const text = ref(format(props.modelValue))
const error = ref(null)
const focused = ref(false)

// pick up outside changes (undo, someone else's save) unless the user is typing here
watch(
  () => props.modelValue,
  (value) => {
    if (!focused.value) {
      text.value = format(value)
      error.value = null
    }
  }
)

function onInput(event) {
  text.value = event.target.value
  try {
    const value = text.value.trim() === '' ? null : JSON.parse(text.value)
    error.value = null
    emit('update:modelValue', value)
  } catch (e) {
    error.value = e.message
  }
}

function format(value) {
  return value == null ? '' : JSON.stringify(value, null, 2)
}
</script>

<template>
  <div class="space-y-1">
    <textarea
      :id
      :class="['field-input min-h-40 font-mono text-xs', error ? 'border-rose-400' : '']"
      :value="text"
      spellcheck="false"
      @blur="focused = false"
      @focus="focused = true"
      @input="onInput" />
    <p v-if="error" class="text-[11px] text-rose-700">Not saved until this is valid JSON: {{ error }}</p>
  </div>
</template>
