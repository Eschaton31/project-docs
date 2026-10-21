<script setup>
import { computed, nextTick, onMounted, useTemplateRef, watch } from 'vue'

import { cleanInlineHtml, placeCaret } from './caret.js'
import { fitsRichText } from './richText.js'

/**
 * One table cell or list item edited in place. Keeps inline formatting (Ctrl+B/I/U, Ctrl+E for code);
 * `lines` cells take several lines (Enter adds one) and save as an array when they have more than one.
 * Enter elsewhere, Backspace on an empty item and focus are reported to the list/table around it.
 */
const props = defineProps({
  value: { type: [String, Number, Array], default: null },
  // several lines allowed (stored as an array)
  lines: Boolean,
  // plain text (SQL results, breadcrumbs, file names): no formatting
  plain: Boolean,
  // an emptied cell is NULL (SQL results)
  nullable: Boolean,
  // fill the cell rather than just the text, so empty cells are easy to click
  block: Boolean,
  // move the caret here (after Enter, Add row, …)
  focus: Boolean,
})
const emit = defineEmits(['update', 'enter', 'remove-empty', 'focused', 'done'])

const el = useTemplateRef('el')
const asLines = (value) => (Array.isArray(value) ? value : [value ?? '']).map(String)
// markup the formatter can't keep (layout HTML) is shown, not edited, here
const editable = computed(() => props.plain || asLines(props.value).every((line) => fitsRichText(line)))
const shown = computed(() => asLines(props.value).join('<br>'))

function render() {
  if (props.plain) el.value.textContent = props.value ?? ''
  else el.value.innerHTML = shown.value
}

function read() {
  if (props.plain) {
    const text = el.value.textContent

    return props.nullable && text === '' ? null : text
  }

  const html = cleanInlineHtml(el.value.innerHTML)
  if (!props.lines) return html.trim()

  const lines = html.split(/<br\s*\/?>/i).map((line) => line.trim())
  while (lines.length > 1 && lines.at(-1) === '') lines.pop()

  return lines.length > 1 ? lines : lines[0]
}

onMounted(() => {
  if (!editable.value) return
  render()
  if (props.focus) focusEnd()
})

watch(
  () => props.value,
  () => {
    if (editable.value && el.value && document.activeElement !== el.value) render()
  }
)
watch(
  () => props.focus,
  (focus) => focus && nextTick(focusEnd)
)

function focusEnd() {
  el.value?.focus()
  if (el.value) placeCaret(el.value, null)
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    event.preventDefault()
    emit('done')
  } else if (event.key === 'Enter') {
    event.preventDefault()
    if (props.lines && !props.plain) document.execCommand('insertLineBreak')
    else emit('enter')
  } else if (event.key === 'Backspace' && el.value.textContent === '') {
    event.preventDefault()
    emit('remove-empty')
  } else if (!props.plain && (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'e') {
    event.preventDefault()
    wrapInCode()
  }
}

/** Ctrl+E: the selected text becomes <codetext>. */
function wrapInCode() {
  const selection = window.getSelection()
  if (!selection?.rangeCount || selection.isCollapsed) return

  const range = selection.getRangeAt(0)
  const code = document.createElement('codetext')
  code.appendChild(range.extractContents())
  range.insertNode(code)
  selection.selectAllChildren(code)
  emit('update', read())
}

// pasted text arrives as plain text, whatever it was copied from
function onPaste(event) {
  event.preventDefault()
  const text = event.clipboardData.getData('text/plain')
  document.execCommand('insertText', false, props.lines ? text : text.replace(/\s*\n\s*/g, ' '))
}
</script>

<template>
  <span
    v-if="editable"
    ref="el"
    :class="[
      'inline-cell min-h-[1.25em] min-w-[2ch] rounded-sm outline-none',
      block ? 'block' : 'inline-block',
      lines ? 'whitespace-pre-wrap' : '',
    ]"
    :contenteditable="plain ? 'plaintext-only' : 'true'"
    data-inline-cell
    spellcheck="true"
    @focus="emit('focused')"
    @input="emit('update', read())"
    @keydown="onKeydown"
    @paste="onPaste" />
  <span
    v-else
    class="cursor-not-allowed opacity-60"
    title="Contains layout HTML; edit it in the panel"
    v-html="shown" />
</template>
