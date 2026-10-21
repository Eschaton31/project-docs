<script setup>
import { onMounted, useTemplateRef, watch } from 'vue'

import { placeCaret } from './caret.js'

/**
 * Plain text edited in place on the page (headings, captions). Enter or Escape finishes, as does
 * clicking elsewhere.
 */
const props = defineProps({
  value: { type: String, default: '' },
  // where the user clicked, to put the caret there
  coords: { type: Object, default: null },
})
const emit = defineEmits(['update', 'done'])

const el = useTemplateRef('el')

onMounted(() => {
  el.value.textContent = props.value
  el.value.focus()
  placeCaret(el.value, props.coords)
})

// someone else's change or the panel: show it unless this is being typed in
watch(
  () => props.value,
  (value) => {
    if (document.activeElement !== el.value && el.value.textContent !== value) el.value.textContent = value
  }
)

function onInput() {
  emit('update', el.value.textContent)
}

function onKeydown(event) {
  if (event.key === 'Enter' || event.key === 'Escape') {
    event.preventDefault()
    emit('done')
  }
}

// keep it plain text whatever is pasted (for browsers without contenteditable="plaintext-only")
function onPaste(event) {
  event.preventDefault()
  const text = event.clipboardData.getData('text/plain').replace(/\s+/g, ' ')
  document.execCommand('insertText', false, text)
}
</script>

<template>
  <span
    ref="el"
    class="inline-editing rounded-sm outline-none ring-2 ring-bene-teal/40"
    contenteditable="plaintext-only"
    data-inline-editor
    @blur="emit('done')"
    @input="onInput"
    @keydown="onKeydown"
    @paste="onPaste" />
</template>
