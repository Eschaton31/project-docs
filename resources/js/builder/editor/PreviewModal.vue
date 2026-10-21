<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Print Preview: the paginated print view in an iframe, scrolled to the selected block. It shows the saved
 * document as of opening (or Reload).
 */
const props = defineProps({
  slug: { type: String, required: true },
  blockId: { type: String, default: null },
  pdfBusy: Boolean,
})
// download: server-side PDF; print: the browser's print dialog
const emit = defineEmits(['close', 'download', 'print'])

const mode = ref('draft')
const loadedAt = ref(Date.now())
const ready = ref(false)

const src = computed(() => {
  const query = new URLSearchParams({ mode: mode.value, t: loadedAt.value })
  if (props.blockId) query.set('block', props.blockId)

  return `/render/${encodeURIComponent(props.slug)}?${query}`
})

function reload() {
  ready.value = false
  loadedAt.value = Date.now()
}

function setFinal(final) {
  mode.value = final ? 'final' : 'draft'
  reload()
}

function onMessage(event) {
  if (event.origin === window.location.origin && event.data?.type === 'paged:ready') ready.value = true
}

onMounted(() => window.addEventListener('message', onMessage))
onBeforeUnmount(() => window.removeEventListener('message', onMessage))
</script>

<template>
  <div class="fixed inset-0 z-40 flex flex-col bg-slate-900/60 p-4">
    <div class="flex items-center gap-2 rounded-t-lg bg-white px-4 py-2 text-xs">
      <span class="font-bold text-slate-800">Print preview</span>
      <span v-if="!ready" class="text-slate-500">Paginating…</span>
      <label class="ml-4 flex items-center gap-1 text-slate-600">
        <input :checked="mode === 'final'" type="checkbox" @change="setFinal($event.target.checked)" />
        Final version (hide drafts and empty topics)
      </label>
      <div class="ml-auto flex gap-2">
        <button class="toolbar-button" type="button" @click="reload">Reload</button>
        <a :href="src" class="toolbar-button" target="_blank">Open in new tab</a>
        <button class="toolbar-button" title="Your browser's print dialog" type="button" @click="emit('print', mode)">
          Print…
        </button>
        <button
          :disabled="pdfBusy"
          class="rounded bg-bene-teal px-3 py-1 text-xs font-semibold text-white disabled:opacity-40"
          type="button"
          @click="emit('download', mode)">
          {{ pdfBusy ? 'Making PDF…' : 'Download PDF' }}
        </button>
        <button class="toolbar-button" type="button" @click="emit('close')">Close</button>
      </div>
    </div>
    <iframe :key="src" :src class="w-full grow rounded-b-lg bg-slate-200" title="Print preview" />
  </div>
</template>
