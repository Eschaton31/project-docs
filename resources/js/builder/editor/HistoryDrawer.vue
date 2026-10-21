<script setup>
import { errorMessage, http } from '@/builder/api.js'

import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

import { reported, useEditorStore } from '@/stores/editor.js'

import UserBadge from './UserBadge.vue'
import { ago, describe } from './history.js'

/**
 * The document's activity feed (who changed what; deleted blocks can be brought back) and its published
 * versions (frozen copies with their own PDF).
 */
const emit = defineEmits(['close'])

const store = useEditorStore()
const tab = ref('activity')
const slug = store.document.slug

const activity = ref([])
const hasMore = ref(false)
const versions = ref([])
const error = ref(null)
const label = ref('')
const final = ref(true)
const publishing = ref(false)
let pollTimer = null

async function loadActivity(before = null) {
  error.value = null
  try {
    const { data } = await http.get(`/api/documents/${slug}/activity`, { params: before ? { before } : {} })
    activity.value = before ? [...activity.value, ...data.data] : data.data
    hasMore.value = data.hasMore
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function loadVersions() {
  try {
    versions.value = (await http.get(`/api/documents/${slug}/snapshots`)).data.data
  } catch (e) {
    error.value = errorMessage(e)
  }
  // keep checking while a version's PDF is still being made
  clearTimeout(pollTimer)
  if (versions.value.some((version) => ['queued', 'running'].includes(version.pdf?.status))) {
    pollTimer = setTimeout(loadVersions, 2000)
  }
}

async function publish() {
  publishing.value = true
  error.value = null
  try {
    await store.flushAll()
    await http.post(`/api/documents/${slug}/snapshots`, {
      label: label.value.trim(),
      mode: final.value ? 'final' : 'draft',
    })
    label.value = ''
    await loadVersions()
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    publishing.value = false
  }
}

function open(entry) {
  if (entry.blockId && store.get(entry.blockId)) store.select(entry.blockId)
}

async function restore(entry) {
  await store.restore(entry.blockId).catch(reported)
  await loadActivity()
}

watch(tab, (name) => (name === 'activity' ? loadActivity() : loadVersions()), { immediate: true })
onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
  clearTimeout(pollTimer)
  window.removeEventListener('keydown', onKeydown)
})

function onKeydown(event) {
  if (event.key === 'Escape') emit('close')
}
</script>

<template>
  <!-- positioned within the editor body, so the header (and Download PDF) stays usable -->
  <aside class="absolute inset-y-0 right-0 z-30 flex w-[28rem] flex-col border-l border-slate-200 bg-white shadow-xl">
    <div class="flex items-center gap-1 border-b border-slate-200 px-3 py-2 text-xs">
      <button
        v-for="[name, title] in [
          ['activity', 'Activity'],
          ['versions', 'Published versions'],
        ]"
        :key="name"
        :class="[
          'rounded px-2 py-1',
          tab === name ? 'bg-teal-100 font-semibold text-slate-900' : 'text-slate-600 hover:bg-slate-100',
        ]"
        type="button"
        @click="tab = name">
        {{ title }}
      </button>
      <button class="toolbar-button ml-auto" type="button" @click="emit('close')">Close</button>
    </div>

    <p v-if="error" class="px-4 pt-3 text-xs text-rose-700">{{ error }}</p>

    <div v-if="tab === 'activity'" class="grow space-y-1 overflow-y-auto p-3">
      <div class="flex justify-end">
        <button class="text-[11px] text-slate-500 underline" type="button" @click="loadActivity()">Refresh</button>
      </div>
      <ol class="space-y-1">
        <li
          v-for="entry in activity"
          :key="entry.seq"
          :class="[
            'flex gap-2 rounded p-1.5 text-xs',
            entry.blockId && store.get(entry.blockId) ? 'cursor-pointer hover:bg-slate-50' : '',
          ]"
          @click="open(entry)">
          <UserBadge :color="entry.user?.color" :name="entry.user?.name ?? '?'" size="sm" />
          <div class="min-w-0 grow">
            <div class="text-slate-700">
              <span class="font-semibold">{{ entry.user?.name ?? 'Someone' }}</span>
              {{ describe(entry) }}
            </div>
            <div class="text-[11px] text-slate-400">{{ ago(entry.at) }}</div>
          </div>
          <button
            v-if="entry.restorable"
            class="shrink-0 self-start text-[11px] text-bene-teal underline"
            type="button"
            @click.stop="restore(entry)">
            Restore
          </button>
        </li>
      </ol>
      <button v-if="hasMore" class="toolbar-button w-full" type="button" @click="loadActivity(activity.at(-1).seq)">
        Older changes
      </button>
    </div>

    <div v-else class="grow space-y-4 overflow-y-auto p-3">
      <form class="space-y-2 rounded border border-slate-200 bg-slate-50 p-3" @submit.prevent="publish">
        <p class="text-xs text-slate-600">
          Publishing freezes the document as it is now (for example when submitting it) and makes its PDF. Later edits
          don't change a published version.
        </p>
        <input
          v-model="label"
          class="field-input"
          maxlength="120"
          placeholder="Label, e.g. v1.0 submitted"
          required
          type="text" />
        <label class="flex items-center gap-2 text-xs text-slate-700">
          <input v-model="final" type="checkbox" />
          Final version (leave out drafts and empty topics)
        </label>
        <button
          :disabled="publishing || !label.trim()"
          class="rounded bg-bene-teal px-3 py-1 text-xs font-semibold text-white disabled:opacity-40"
          type="submit">
          {{ publishing ? 'Publishing…' : 'Publish version' }}
        </button>
      </form>

      <p v-if="!versions.length" class="text-xs text-slate-500">Nothing published yet.</p>
      <ol class="space-y-2">
        <li v-for="version in versions" :key="version.id" class="space-y-1 rounded border border-slate-200 p-2 text-xs">
          <div class="flex items-baseline justify-between gap-2">
            <span class="font-semibold text-slate-800">{{ version.label }}</span>
            <span class="text-[11px] text-slate-400">{{ version.createdBy }} · {{ ago(version.createdAt) }}</span>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <a
              :href="`/render/${slug}?snapshot=${version.id}&mode=${version.pdf?.mode ?? 'final'}`"
              class="text-bene-teal underline"
              target="_blank">
              View
            </a>
            <a
              v-if="version.pdf?.status === 'done'"
              :href="`/api/snapshots/${version.id}/pdf`"
              class="text-bene-teal underline"
              >PDF</a
            >
            <span v-else-if="version.pdf?.status === 'failed'" :title="version.pdf.error" class="text-rose-700"
              >PDF failed</span
            >
            <span v-else class="text-slate-500">Making PDF…</span>
            <a :href="`/api/snapshots/${version.id}/export`" class="text-slate-500 underline">JSON</a>
          </div>
        </li>
      </ol>
    </div>
  </aside>
</template>
