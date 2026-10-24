<script setup>
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  provide,
  shallowRef,
  useTemplateRef,
  watch,
  watchEffect,
} from 'vue'

import { useEditorStore } from '@/stores/editor.js'

import { BlockList } from '../BlockList.js'
import { scrollToBlock } from '../scrollToBlock.js'
import { findTextRange } from './findText.js'
import { createInlineEdit, inlineEditCheck } from './inline.js'

/**
 * Live, unpaginated view of the top-level topic that contains the selection, at A4 text width.
 * Showing one topic at a time keeps typing fast on long documents; Print Preview shows the whole thing.
 *
 * Text can be edited in place: click a selected block again (or double-click, or press Enter) to type in
 * it. Edits go through the same store as the panel, so they save, lock and undo the same way.
 */
const store = useEditorStore()
const page = useTemplateRef('page')

// keep in step with the store's canvasRootId, which says which blocks are on the page
const root = computed(() => (store.selectedId ? store.rootOf(store.selectedId) : store.roots[0]) ?? null)

// { id, coords } of the block being edited in place
const editing = shallowRef(null)
const stopEditing = () => (editing.value = null)

provide(
  'inlineEdit',
  createInlineEdit({
    editing: () => editing.value,
    update: (id, prop, value) => store.edit(id, { props: { [prop]: value } }),
    stop: stopEditing,
  })
)

/** Starts editing in place if the block allows it here and nobody else holds it. */
function startEditing(id, target, coords = null) {
  if (store.lockedByOther(`block:${id}`)) return false

  const check = inlineEditCheck(store.get(id), target)
  if (!check.ok) {
    if (check.reason) store.notice = { type: 'warning', text: check.reason }
    return false
  }
  editing.value = { id, coords }

  return true
}

// highlight via a stylesheet rather than classes, since the blocks are rendered by the hand-built components
const highlight = document.createElement('style')
// CSS Custom Highlight name for the phrase a hovered suggestion would change
const PHRASE = 'pointed-phrase'
onMounted(() => {
  document.head.appendChild(highlight)
  window.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
  highlight.remove()
  window.removeEventListener('keydown', onKeydown)
})

// RichText is display: contents (no box of its own), so it is outlined through the block that holds it
const outline = (id, style) => {
  const selector = `.block-canvas [data-block-id="${id}"]`

  return `${selector}:not(.contents), .block-canvas :has(> ${selector}.contents) { outline: ${style}; outline-offset: 2px; }`
}

// the selection in teal; blocks other people are editing dashed, in their colour; blocks pointed at (a hovered
// AI suggestion) in amber, with the phrase it would change marked
watchEffect(() => {
  highlight.textContent = [
    ...store.othersLocks
      .filter((lock) => lock.target.startsWith('block:'))
      .map((lock) => outline(lock.target.slice(6), `2px dashed ${lock.color || '#64748b'}`)),
    // while typing in place the editor shows its own frame
    store.selectedId && editing.value?.id !== store.selectedId ? outline(store.selectedId, '2px solid #0b6374') : '',
    ...(store.pointer?.ids ?? []).map((id) => pointed(id)),
    `::highlight(${PHRASE}) { background-color: #fde68a; color: inherit; }`,
  ].join('\n')
})

function pointed(id) {
  const selector = `.block-canvas [data-block-id="${id}"]`
  const boxes = `${selector}:not(.contents), .block-canvas :has(> ${selector}.contents)`

  return `${boxes} { outline: 2px solid #f59e0b; outline-offset: 2px; background-color: rgb(254 243 199 / 0.45); }`
}

// bring what's pointed at into view, and mark the phrase (where the browser has the CSS Custom Highlight API)
watch(
  () => [store.pointer, root.value],
  async () => {
    await nextTick()
    const pointer = store.pointer
    const elements = (pointer?.ids ?? [])
      .map((id) => page.value?.querySelector(`[data-block-id="${CSS.escape(id)}"]`))
      .filter(Boolean)

    if (typeof CSS !== 'undefined' && CSS.highlights) {
      const ranges = pointer?.text
        ? elements.map((element) => findTextRange(element, pointer.text)).filter(Boolean)
        : []
      ranges.length ? CSS.highlights.set(PHRASE, new Highlight(...ranges)) : CSS.highlights.delete(PHRASE)
    }
    if (elements[0]) scrollToBlock(page.value, pointer.ids[0], { block: 'nearest', behavior: 'smooth' })
  }
)
onBeforeUnmount(() => typeof CSS !== 'undefined' && CSS.highlights?.delete(PHRASE))

watch(
  () => store.selectedId,
  async (id) => {
    if (editing.value && editing.value.id !== id) stopEditing()
    await nextTick()
    if (id) scrollToBlock(page.value, id, { block: 'nearest' })
  },
  { immediate: true }
)

// someone else took the block over (or it was deleted) while it was being edited here
watch(
  () => editing.value && (store.lockedByOther(`block:${editing.value.id}`) || !store.get(editing.value.id)),
  (lost) => lost && stopEditing()
)

function onClick(event) {
  // clicking selects; links in the text shouldn't navigate away from the editor
  if (event.target.closest('a')) event.preventDefault()
  // clicks inside an in-place editor (text, its toolbar) belong to it
  if (event.target.closest('[data-inline-editor]')) return

  const block = event.target.closest('[data-block-id]')
  if (!block) return

  const id = block.dataset.blockId
  // a second click on the selected block starts editing it in place
  if (id === store.selectedId && startEditing(id, event.target, { x: event.clientX, y: event.clientY })) return

  stopEditing()
  store.select(id)
}

function onDoubleClick(event) {
  if (event.target.closest('[data-inline-editor]')) return
  const block = event.target.closest('[data-block-id]')
  if (!block) return

  store.select(block.dataset.blockId)
  startEditing(block.dataset.blockId, event.target, { x: event.clientX, y: event.clientY })
}

/** Enter on a selected text block starts editing it (unless focus is in a form field). */
function onKeydown(event) {
  if (event.key !== 'Enter' || editing.value || !store.selectedId) return
  if (event.target.closest?.('input, textarea, select, button, [contenteditable]')) return

  const element = page.value?.querySelector(`[data-block-id="${CSS.escape(store.selectedId)}"]`)
  if (!element) return
  // the part that would be clicked: a topic's heading, an image's caption…
  const target =
    element.querySelector(
      '.primary-topic, .secondary-topic, .tertiary-topic, .quaternary-topic, .alert-title, .image-caption'
    ) ?? element
  if (startEditing(store.selectedId, target)) event.preventDefault()
}
</script>

<template>
  <div class="block-canvas p-6">
    <div
      v-if="root"
      ref="page"
      class="mx-auto min-h-[11.69in] w-[8.27in] cursor-default bg-white p-[0.5in] shadow"
      @click="onClick"
      @dblclick="onDoubleClick">
      <BlockList :nodes="[root]" />
    </div>
    <p v-else class="mt-24 text-center text-sm text-slate-500">
      This document is empty. Add a topic from the panel on the right, or paste an outline.
    </p>
    <p v-if="root" class="mx-auto mt-2 w-[8.27in] text-right text-[11px] text-slate-400">
      Click a selected block again, double-click, or press Enter to edit its text in place.
    </p>
  </div>
</template>
