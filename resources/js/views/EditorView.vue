<script setup>
import { errorMessage } from '@/builder/api.js'
import BlockCanvas from '@/builder/editor/BlockCanvas.vue'
import HistoryDrawer from '@/builder/editor/HistoryDrawer.vue'
import OutlineDialog from '@/builder/editor/OutlineDialog.vue'
import OutlineTree from '@/builder/editor/OutlineTree.vue'
import PreviewModal from '@/builder/editor/PreviewModal.vue'
import PropertiesPanel from '@/builder/editor/PropertiesPanel.vue'
import ProseReviewPanel from '@/builder/editor/ProseReviewPanel.vue'
import UserBadge from '@/builder/editor/UserBadge.vue'
import { useExport } from '@/builder/editor/useExport.js'
import { onBeforeRouteLeave, useRoute } from 'vue-router'

import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import { useAuthStore } from '@/stores/auth.js'
import { useEditorStore } from '@/stores/editor.js'
import { useProseReviewStore } from '@/stores/proseReview.js'

const route = useRoute()
const store = useEditorStore()
const reviews = useProseReviewStore()
const auth = useAuthStore()
const pdf = useExport()

const loadError = ref(null)
const showPreview = ref(false)
const showOutline = ref(false)
const showHistory = ref(false)
const canReview = computed(() => !!auth.user?.abilities?.aiReview)

// the history and the AI review share the drawer space
watch(showHistory, (open) => open && (reviews.open = false))
watch(
  () => reviews.open,
  (open) => open && (showHistory.value = false)
)

const slug = computed(() => route.params.slug)
const statusText = computed(() => ({ saving: 'Saving…', saved: 'All changes saved', error: 'Not saved' })[store.status])

async function load() {
  loadError.value = null
  reviews.reset()
  reviews.open = false
  try {
    await store.load(slug.value)
    document.title = `${store.document.title} · Project Docs`
  } catch (error) {
    loadError.value = errorMessage(error)
  }
}

watch(slug, load)
onMounted(load)

async function openPreview() {
  await store.flushAll()
  showPreview.value = true
}

/** One click: the server renders the saved document in headless Chrome and the PDF downloads. */
async function downloadPdf(mode = 'draft') {
  await store.flushAll()
  await pdf.downloadPdf(slug.value, mode)
}

/** The browser's own print dialog (print or "Save as PDF" on this machine). */
async function printInBrowser(mode = 'draft') {
  // open the tab during the click (popup blockers), point it at the print view once everything is saved
  const tab = window.open('about:blank', '_blank')
  await store.flushAll()
  const url = `/render/${encodeURIComponent(slug.value)}?print=1&mode=${mode}`
  tab ? (tab.location = url) : (window.location = url)
}

function onKeydown(event) {
  if (!(event.ctrlKey || event.metaKey)) return
  // text fields keep their own undo
  if (event.target.closest('input, textarea, select, [contenteditable]')) return

  const key = event.key.toLowerCase()
  if (key === 'z' && !event.shiftKey) {
    event.preventDefault()
    store.undo()
  } else if ((key === 'z' && event.shiftKey) || key === 'y') {
    event.preventDefault()
    store.redo()
  }
}

function onBeforeUnload(event) {
  if (store.status === 'saving') event.preventDefault()
}

// closing or reloading the tab: hand back its locks so others don't wait for them to expire
const onPageHide = () => store.dispose()

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('beforeunload', onBeforeUnload)
  window.addEventListener('pagehide', onPageHide)
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('beforeunload', onBeforeUnload)
  window.removeEventListener('pagehide', onPageHide)
  store.dispose()
  reviews.reset()
})
onBeforeRouteLeave(() => store.flushAll())
</script>

<template>
  <div class="flex h-screen flex-col bg-slate-100 text-slate-800">
    <header class="flex shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 py-2 text-sm">
      <RouterLink class="text-slate-500 hover:text-slate-800" to="/">← Documents</RouterLink>
      <h1 class="truncate font-bold">{{ store.document?.title ?? slug }}</h1>
      <span :class="['text-xs', store.status === 'error' ? 'text-rose-700' : 'text-slate-500']">{{ statusText }}</span>

      <div class="ml-auto flex items-center gap-1.5">
        <div
          v-if="store.members.length"
          :title="`Here now: ${store.members.map((member) => member.name).join(', ')}`"
          class="mr-3 flex -space-x-1">
          <UserBadge
            v-for="member in store.members"
            :key="member.id"
            :color="member.color"
            :name="member.name"
            class="ring-2 ring-white" />
        </div>
        <button
          :disabled="!store.undoStack.length"
          class="toolbar-button"
          title="Undo (Ctrl+Z)"
          type="button"
          @click="store.undo()">
          Undo
        </button>
        <button
          :disabled="!store.redoStack.length"
          class="toolbar-button"
          title="Redo (Ctrl+Shift+Z)"
          type="button"
          @click="store.redo()">
          Redo
        </button>
        <span class="mx-2 h-5 w-px bg-slate-200" />
        <button :disabled="!store.document" class="toolbar-button" type="button" @click="showHistory = !showHistory">
          History
        </button>
        <button
          v-if="canReview"
          :disabled="!store.document"
          class="toolbar-button"
          title="Claude suggests wording and structure changes, in the style of the Laravel docs"
          type="button"
          @click="reviews.open ? (reviews.open = false) : reviews.openPanel()">
          AI review
        </button>
        <button :disabled="!store.document" class="toolbar-button" type="button" @click="openPreview">
          Print preview
        </button>
        <button
          :disabled="!store.document || pdf.busy.value"
          class="rounded bg-bene-teal px-3 py-1 text-xs font-semibold text-white disabled:opacity-40"
          type="button"
          @click="downloadPdf()">
          {{ pdf.busy.value ? 'Making PDF…' : 'Download PDF' }}
        </button>
      </div>
    </header>

    <div
      v-if="pdf.slow.value || pdf.error.value"
      :class="[
        'flex shrink-0 items-center gap-3 px-4 py-2 text-xs',
        pdf.error.value ? 'bg-rose-50 text-rose-800' : 'bg-amber-50 text-amber-800',
      ]">
      <template v-if="pdf.error.value">PDF export failed: {{ pdf.error.value }}</template>
      <template v-else>
        The PDF hasn't started yet. Exports run on the queue worker, which <code>composer dev</code> starts; check that
        it's running.
      </template>
      <button v-if="pdf.error.value" class="ml-auto underline" type="button" @click="pdf.error.value = null">
        Dismiss
      </button>
    </div>
    <div
      v-if="store.notice"
      :class="[
        'flex shrink-0 items-center gap-3 px-4 py-2 text-xs',
        store.notice.type === 'error' ? 'bg-rose-50 text-rose-800' : 'bg-amber-50 text-amber-800',
      ]">
      {{ store.notice.text }}
      <button class="ml-auto underline" type="button" @click="store.notice = null">Dismiss</button>
    </div>

    <p v-if="loadError" class="p-8 text-sm text-rose-700">Could not open the document: {{ loadError }}</p>

    <main v-else-if="store.document" class="relative flex min-h-0 grow">
      <nav class="w-80 shrink-0 overflow-y-auto border-r border-slate-200 bg-white px-1" aria-label="Outline">
        <OutlineTree />
      </nav>
      <section class="min-w-0 grow overflow-auto">
        <BlockCanvas />
      </section>
      <aside class="w-96 shrink-0 overflow-y-auto border-l border-slate-200 bg-white">
        <PropertiesPanel @outline="showOutline = true" />
      </aside>
      <HistoryDrawer v-if="showHistory" @close="showHistory = false" />
      <ProseReviewPanel v-if="canReview && reviews.open" @close="reviews.open = false" />
    </main>

    <PreviewModal
      v-if="showPreview"
      :block-id="store.selectedId"
      :pdf-busy="pdf.busy.value"
      :slug
      @close="showPreview = false"
      @download="downloadPdf"
      @print="printInBrowser" />
    <OutlineDialog v-if="showOutline" @close="showOutline = false" />
  </div>
</template>
