<script setup>
import DocumentRenderer from '@/builder/DocumentRenderer.vue'
import { errorMessage, http } from '@/builder/api.js'
import { createRenderTracker, paginate, printFileName } from '@/builder/paged.js'
import { scrollToBlock } from '@/builder/scrollToBlock.js'
import { useRoute } from 'vue-router'

import { nextTick, onMounted, provide, ref } from 'vue'

/**
 * Print view of a stored document: renders it with the hand-built components, then Paged.js paginates it.
 * ?mode=final leaves out draft blocks and empty topics; ?block=<id> scrolls to a block; ?print=1 opens the
 * print dialog once paginated; ?snapshot=<id> shows a published version instead of the live document;
 * ?source=<signed /api/print/… URL> is how the server-side PDF export (headless Chrome, no login) loads it.
 */
const route = useRoute()
const mode = route.query.mode === 'final' ? 'final' : 'draft'

const data = ref(null)
const error = ref(null)

const tracker = createRenderTracker()
provide('renderTracker', tracker.track)

function dataUrl() {
  const { source, snapshot } = route.query
  // only this app's signed print endpoints, never an arbitrary URL from the query string
  if (typeof source === 'string' && source.startsWith('/api/print/')) return source
  if (snapshot) return `/api/snapshots/${encodeURIComponent(snapshot)}`

  return `/api/documents/${encodeURIComponent(route.params.slug)}`
}

onMounted(async () => {
  try {
    data.value = (await http.get(dataUrl())).data
  } catch (e) {
    error.value = errorMessage(e)
    return
  }

  // the browser's "Save as PDF" suggests the page title as the file name
  document.title = printFileName(data.value.document.meta?.fileName || data.value.document.slug)

  await nextTick()
  try {
    await paginate(tracker)

    // ?block=<id>: the editor's preview opens at the selected block
    if (route.query.block) scrollToBlock(window.document, route.query.block, { block: 'center' })
    // ?print=1: the editor's Print… button
    if (route.query.print) window.print()
  } catch (e) {
    console.error(e)
    // Paged.js may already have replaced the body, so report outside the Vue tree
    const banner = Object.assign(window.document.createElement('div'), {
      className: 'fixed inset-x-0 top-0 z-50 bg-rose-700 p-3 text-sm text-white print:hidden',
      textContent: `Pagination failed: ${e.message}`,
    })
    window.document.body.prepend(banner)
  }
})
</script>

<template>
  <div v-if="error" class="p-8 text-sm text-rose-700">Could not load the document: {{ error }}</div>
  <!-- blocks lay out before Paged.js moves them onto pages, so hold them to the page's content width (A4 less
       the 0.5in side margins in app.css, the editor canvas's width too); otherwise anything that sizes itself
       on mount, like a gantt chart, is laid out at the window's width and shrunk to fit the page -->
  <div v-else-if="data" class="max-w-[7.27in]">
    <DocumentRenderer :blocks="data.blocks" :document="data.document" :mode />
  </div>
</template>
