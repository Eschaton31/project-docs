<script setup>
import { computed, ref } from 'vue'

import { useEditorStore } from '@/stores/editor.js'
import { useProseReviewStore } from '@/stores/proseReview.js'

import { blockLabel } from '../editing.js'

/**
 * Drafting documentation from R scripts (admins only): upload the scripts, say how they are run, and Claude
 * suggests the sections the document is missing, in the template's order. The scripts are only read, never run.
 */
const props = defineProps({
  // the model choices to offer, when this user can start a scaffold here
  models: { type: Array, default: () => [] },
})

const editor = useEditorStore()
const reviews = useProseReviewStore()

// topics a scaffold can go into (StoreProseScaffoldRequest::SCOPES)
const SCOPES = ['PrimaryTopic', 'SecondaryTopic']
const MAX_RUN_NOTES = 8000

/** [{ file, path }] in the order they were added */
const files = ref([])
const entry = ref(null)
const runNotes = ref('')
const scope = ref('document')
const model = ref('sonnet')
const dragging = ref(false)
const skipped = ref([])

const running = computed(() => ['queued', 'running'].includes(reviews.review?.status))

const scopeChoices = computed(() => {
  const choices = [['document', 'Whole document']]
  const selected = editor.selectedId
  const nearestFirst = selected ? [selected, ...editor.ancestorsOf(selected).reverse()] : []
  const topic = nearestFirst.map(editor.get).find((node) => node && SCOPES.includes(node.type))
  if (topic) choices.push([topic.id, `Into: ${blockLabel(topic).detail || blockLabel(topic).label}`])

  return choices
})

const isScript = (name) => /\.r$/i.test(name)

/** Adds picked or dropped files; a folder keeps each file's path inside it, and anything but .R is skipped. */
function add(list) {
  const known = new Set(files.value.map((item) => item.path))
  const left = []
  for (const file of list) {
    const path = file.webkitRelativePath || file.name
    if (!isScript(path)) {
      left.push(path)
      continue
    }
    if (known.has(path)) continue
    known.add(path)
    files.value.push({ file, path })
  }
  skipped.value = left
  if (!entry.value && files.value.length) {
    entry.value = (files.value.find((item) => /(^|\/)(main|run|run_all)\.r$/i.test(item.path)) ?? files.value[0]).path
  }
}

function remove(path) {
  files.value = files.value.filter((item) => item.path !== path)
  if (entry.value === path) entry.value = files.value[0]?.path ?? null
}

function onPick(event) {
  add([...event.target.files])
  event.target.value = ''
}

function onDrop(event) {
  dragging.value = false
  add([...(event.dataTransfer?.files ?? [])])
}

const size = (bytes) => (bytes < 1024 ? `${bytes} B` : `${Math.round(bytes / 1024)} KB`)

async function submit() {
  if (!files.value.length || running.value) return

  const form = new FormData()
  files.value.forEach(({ file, path }) => {
    form.append('files[]', file)
    form.append('paths[]', path)
  })
  if (entry.value) form.append('entry', entry.value)
  if (runNotes.value.trim()) form.append('runNotes', runNotes.value.trim())
  if (scope.value !== 'document') form.append('blockId', scope.value)
  if (props.models.length) form.append('model', model.value)

  if (await reviews.startScaffold(editor.document.slug, form)) files.value = []
}

const copied = ref(false)

async function copyCommand() {
  await navigator.clipboard?.writeText(reviews.scaffoldCommand)
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

/** Secrets blanked out in the last upload, per script. */
const redactions = computed(() =>
  (reviews.review?.sources ?? [])
    .filter((source) => source.redactions?.length)
    .map((source) => `${source.name}: line ${source.redactions.map((r) => r.line).join(', ')}`)
)
</script>

<template>
  <form class="space-y-2" data-scaffold-form @submit.prevent="submit">
    <p class="text-slate-600">
      Upload the R scripts and say how they are run. Claude reads them with the document and suggests the sections it's
      missing. The scripts are never run.
    </p>

    <div
      :class="[
        'rounded border border-dashed p-2 text-center text-slate-500',
        dragging ? 'border-bene-teal bg-teal-50' : 'border-slate-300 bg-white',
      ]"
      data-drop
      @dragleave="dragging = false"
      @dragover.prevent="dragging = true"
      @drop.prevent="onDrop">
      Drop .R files here, or
      <label class="cursor-pointer underline"
        >choose files<input accept=".R,.r" class="sr-only" multiple type="file" @change="onPick"
      /></label>
      or
      <label class="cursor-pointer underline"
        >a folder<input class="sr-only" multiple type="file" webkitdirectory @change="onPick"
      /></label>
    </div>
    <p v-if="skipped.length" class="text-amber-800">Skipped (not .R): {{ skipped.join(', ') }}</p>

    <ul v-if="files.length" class="divide-y divide-slate-100 rounded border border-slate-200 bg-white" data-files>
      <li v-for="item in files" :key="item.path" class="flex items-center gap-2 px-2 py-1">
        <label class="flex grow items-center gap-1.5 truncate" title="The script that is opened first">
          <input v-model="entry" :value="item.path" name="entry" type="radio" />
          <span class="truncate font-mono">{{ item.path }}</span>
        </label>
        <span class="text-slate-400">{{ size(item.file.size) }}</span>
        <button
          :aria-label="`Remove ${item.path}`"
          class="text-slate-400 hover:text-rose-700"
          type="button"
          @click="remove(item.path)">
          ✕
        </button>
      </li>
    </ul>
    <p v-if="files.length > 1" class="text-slate-500">The selected script is the one opened first.</p>

    <textarea
      v-model="runNotes"
      aria-label="How it's run"
      class="field-input min-h-20"
      :maxlength="MAX_RUN_NOTES"
      placeholder="How it's run, e.g. “Open main.R in RStudio, set report_month and input_dir at the top, run the Load section and check the row counts it prints, then run the rest. export.R is sourced at the end and writes report.xlsx.”" />

    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-slate-700">
      <select v-model="scope" aria-label="Where to draft" class="field-input w-auto">
        <option v-for="[value, label] in scopeChoices" :key="value" :value>{{ label }}</option>
      </select>
      <label v-for="[value, label, hint] in models" :key="value" :title="hint" class="flex items-center gap-1.5">
        <input v-model="model" :value name="scaffold-model" type="radio" /> {{ label }}
      </label>
    </div>

    <div class="flex items-center gap-3">
      <button
        :disabled="reviews.starting || running || !files.length"
        class="rounded bg-bene-teal px-3 py-1 font-semibold text-white disabled:opacity-40"
        type="submit">
        {{
          running
            ? 'Drafting…'
            : reviews.starting
              ? 'Uploading…'
              : models.length
                ? 'Draft documentation'
                : 'Upload for Claude Code'
        }}
      </button>
    </div>

    <p v-if="redactions.length" class="text-amber-800" data-redactions>
      Secrets were blanked out before anything was stored or sent: {{ redactions.join('; ') }}.
    </p>
    <div v-if="reviews.scaffoldCommand" class="space-y-1.5" data-claude-code>
      <p class="text-slate-600">Uploaded. With Claude Code in this project, run:</p>
      <div class="flex items-center gap-2">
        <code class="grow truncate rounded bg-white px-2 py-1 ring-1 ring-slate-200">{{
          reviews.scaffoldCommand
        }}</code>
        <button class="toolbar-button" type="button" @click="copyCommand">{{ copied ? 'Copied' : 'Copy' }}</button>
      </div>
    </div>
  </form>
</template>
