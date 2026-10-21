<script setup>
import { errorMessage, http } from '@/builder/api.js'

import { computed, onMounted, ref, useId } from 'vue'

/**
 * Pick an image from public/img, or upload new ones into a folder there (an existing one, or a new
 * path such as "diagrams/server-migration"). Uploading a single file picks it; several are just added.
 */
const props = defineProps({
  current: { type: String, default: '' },
})
const emit = defineEmits(['select', 'close'])

const id = useId()
const images = ref([])
const folders = ref([])
const search = ref('')
// where uploads go; empty means the dated uploads folder the server picks
const folder = ref(folderOf(props.current))
const loading = ref(true)
const uploading = ref(null)
const dragging = ref(false)
const error = ref(null)

const groups = computed(() => {
  const term = search.value.trim().toLowerCase()
  const byFolder = {}
  for (const image of images.value) {
    if (term && !image.path.toLowerCase().includes(term)) continue
    ;(byFolder[image.folder] ??= []).push(image)
  }

  return byFolder
})

/** "/img/diagrams/x.png" → "diagrams" (uploads default to the folder of the image being replaced). */
function folderOf(path) {
  const match = (path ?? '').match(/^\/img\/(.+)\/[^/]+$/)

  return match ? decodeURIComponent(match[1]) : ''
}

async function load() {
  loading.value = true
  try {
    const { data } = await http.get('/api/images')
    images.value = data.data
    folders.value = data.folders ?? []
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

async function upload(files) {
  files = [...files].filter((file) => file.type.startsWith('image/'))
  if (!files.length) return

  error.value = null
  let last = null
  try {
    for (const [index, file] of files.entries()) {
      uploading.value = files.length > 1 ? `Uploading ${index + 1} of ${files.length}…` : 'Uploading…'
      const form = new FormData()
      form.append('file', file)
      if (folder.value.trim()) form.append('folder', folder.value.trim().replace(/^\/+|\/+$/g, ''))
      last = (await http.post('/api/images', form)).data.image
    }
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    uploading.value = null
  }

  if (files.length === 1 && last) return emit('select', last.path)
  await load()
}

function onFileInput(event) {
  upload(event.target.files)
  event.target.value = ''
}

function onDrop(event) {
  dragging.value = false
  upload(event.dataTransfer.files)
}

onMounted(load)
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-6" @click.self="emit('close')">
    <div
      :class="[
        'relative flex max-h-full w-full max-w-4xl flex-col rounded-lg bg-white shadow-xl',
        dragging ? 'ring-4 ring-bene-teal' : '',
      ]"
      @dragleave.self="dragging = false"
      @dragover.prevent="dragging = true"
      @drop.prevent="onDrop">
      <div class="flex items-center gap-2 border-b border-slate-200 p-3">
        <h2 class="text-sm font-bold text-slate-800">Choose an image</h2>
        <input
          v-model="search"
          class="field-input ml-4 max-w-xs"
          placeholder="Filter by name or folder"
          type="search" />
        <button class="toolbar-button ml-auto" type="button" @click="emit('close')">Close</button>
      </div>

      <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs">
        <label :for="`${id}-folder`" class="font-semibold text-slate-600">Upload to</label>
        <span class="text-slate-400">/img/</span>
        <input
          :id="`${id}-folder`"
          v-model="folder"
          :list="`${id}-folders`"
          class="field-input max-w-sm py-1 text-xs"
          placeholder="uploads/<year-month> (or type a new folder)"
          type="text" />
        <datalist :id="`${id}-folders`">
          <option v-for="name in folders" :key="name" :value="name" />
        </datalist>
        <label :class="['toolbar-button cursor-pointer', uploading ? 'pointer-events-none opacity-50' : '']">
          {{ uploading ?? 'Upload…' }}
          <input
            accept="image/png,image/jpeg,image/gif,image/webp"
            class="hidden"
            multiple
            type="file"
            @change="onFileInput" />
        </label>
        <span class="text-slate-400">or drop images here</span>
      </div>

      <p v-if="error" class="px-4 pt-3 text-xs text-rose-700">{{ error }}</p>
      <p v-if="loading" class="p-6 text-sm text-slate-500">Loading images…</p>

      <div v-else class="space-y-4 overflow-y-auto p-4">
        <p v-if="!Object.keys(groups).length" class="text-sm text-slate-500">No images match.</p>
        <section v-for="(items, name) in groups" :key="name" class="space-y-2">
          <div class="flex items-baseline gap-2">
            <h3 class="text-xs font-semibold text-slate-500">{{ name || '(top level)' }}</h3>
            <button
              v-if="folder !== name"
              class="text-[11px] text-slate-400 underline hover:text-bene-teal"
              type="button"
              @click="folder = name">
              upload here
            </button>
            <span v-else class="text-[11px] text-bene-teal">uploads go here</span>
          </div>
          <div class="grid grid-cols-4 gap-2">
            <button
              v-for="image in items"
              :key="image.path"
              :class="[
                'flex flex-col gap-1 rounded border p-1 text-left hover:border-bene-teal',
                image.path === props.current ? 'border-bene-teal ring-2 ring-teal-100' : 'border-slate-200',
              ]"
              :title="image.path"
              type="button"
              @click="emit('select', image.path)">
              <img :src="image.path" alt="" class="h-24 w-full rounded bg-slate-50 object-contain" loading="lazy" />
              <span class="truncate text-[11px] text-slate-600">{{ image.name }}</span>
            </button>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
