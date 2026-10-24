<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import { useEditorStore } from '@/stores/editor.js'
import { useProseReviewStore } from '@/stores/proseReview.js'

import { BlockList } from '../BlockList.js'
import { blockLabel } from '../editing.js'
import { registry } from '../registry.js'
import { ago } from './history.js'
import { plainText, wordDiff } from './proseDiff.js'

/**
 * AI prose review (admins only): Claude checks the document, or one topic, against a style guide based on
 * the Laravel docs and suggests wording changes and better block structure. Nothing changes until a
 * suggestion is accepted, and accepted changes can be undone like any other edit.
 */
const emit = defineEmits(['close'])

const editor = useEditorStore()
const reviews = useProseReviewStore()

const MODELS = [
  ['sonnet', 'Sonnet', 'faster, for everyday reviews'],
  ['opus', 'Opus', 'slower, more thorough'],
]

const scope = ref(reviews.scopeId ?? 'document')
const wording = ref(true)
const structure = ref(true)
const model = ref('sonnet')
const showAll = ref(false)
const kind = ref('all')

watch(
  () => reviews.scopeId,
  (id) => (scope.value = id ?? 'document')
)

const scopeBlock = computed(() => (scope.value === 'document' ? null : editor.get(scope.value)))
const scopeChoices = computed(() => {
  const choices = [['document', 'Whole document']]
  // the topic around the selection: the selected block itself, or its nearest topic ancestor
  const selected = editor.selectedId
  const nearestFirst = selected ? [selected, ...editor.ancestorsOf(selected).reverse()] : []
  const topic = nearestFirst.map(editor.get).find((node) => node && registry[node.type]?.topic)
  for (const node of new Set([topic, scopeBlock.value].filter(Boolean))) {
    choices.push([node.id, `This section: ${blockLabel(node).detail || blockLabel(node).label}`])
  }

  return choices
})

const running = computed(() => ['queued', 'running'].includes(reviews.review?.status))
const kinds = computed(() => [wording.value && 'text', structure.value && 'restructure'].filter(Boolean))

// reviews run in Claude Code with the user's own Claude plan: show the command to run there
const inClaudeCode = computed(() => reviews.runner === 'claude-code')
const command = computed(() =>
  [
    `/prose-review ${editor.document.slug}`,
    scope.value !== 'document' && `--block=${scope.value}`,
    kinds.value.length === 1 && `--kinds=${kinds.value[0]}`,
  ]
    .filter(Boolean)
    .join(' ')
)
const copied = ref(false)

async function copyCommand() {
  await navigator.clipboard?.writeText(command.value)
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

const MODEL_NAMES = { sonnet: 'Sonnet', opus: 'Opus', 'claude-code': 'Claude Code' }

function startReview() {
  if (inClaudeCode.value) return
  reviews.start(editor.document.slug, {
    blockId: scope.value === 'document' ? null : scope.value,
    kinds: kinds.value,
    model: model.value,
  })
}

/** Document order of every block, so suggestions read top to bottom. */
const order = computed(() => {
  const ids = new Map()
  const walk = (nodes) =>
    nodes.forEach((node) => {
      ids.set(node.id, ids.size)
      walk(node.children)
    })
  walk(editor.roots)

  return ids
})

const visible = computed(() =>
  reviews.suggestions.filter(
    (suggestion) =>
      (showAll.value || suggestion.status === 'pending') && (kind.value === 'all' || suggestion.kind === kind.value)
  )
)

/** Suggestions grouped by the block they're about, in document order; ones on removed blocks go last. */
const groups = computed(() => {
  const byBlock = new Map()
  for (const suggestion of visible.value) {
    const id = suggestion.blockIds[0]
    if (!byBlock.has(id)) byBlock.set(id, [])
    byBlock.get(id).push(suggestion)
  }

  return [...byBlock.entries()]
    .map(([id, items]) => ({ id, node: editor.get(id), items }))
    .sort((a, b) => (order.value.get(a.id) ?? Infinity) - (order.value.get(b.id) ?? Infinity))
})

const pendingCount = computed(() => reviews.suggestions.filter((suggestion) => suggestion.status === 'pending').length)

/** Rules with several pending wording suggestions, which can be accepted together. */
const batchRules = computed(() => {
  const counts = new Map()
  reviews.suggestions
    .filter((suggestion) => suggestion.kind === 'text' && suggestion.status === 'pending')
    .forEach((suggestion) => counts.set(suggestion.rule, (counts.get(suggestion.rule) ?? 0) + 1))

  return [...counts.entries()].filter(([, count]) => count > 1)
})

function groupTitle(group) {
  if (!group.node) return 'Removed block'
  const { label, detail } = blockLabel(group.node)

  return detail ? `${label} “${detail}”` : label
}

const diff = (suggestion) => wordDiff(plainText(suggestion.original), plainText(suggestion.suggested))

/** The blocks a restructure would replace, as they are now. */
const currentBlocks = (suggestion) => suggestion.blockIds.map(editor.get).filter(Boolean)

/** Replacement nodes with ids the renderer can key on. */
function previewNodes(nodes, prefix = 'preview') {
  return nodes.map((node, i) => ({
    ...node,
    id: `${prefix}-${i}`,
    children: previewNodes(node.children ?? [], `${prefix}-${i}`),
  }))
}

const STATUS = { accepted: 'Accepted', dismissed: 'Dismissed', stale: 'Text changed since the review' }

function onKeydown(event) {
  if (event.key === 'Escape') emit('close')
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  reviews.load(editor.document)
})
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <aside
    aria-label="AI review"
    class="absolute inset-y-0 right-0 z-30 flex w-[32rem] flex-col border-l border-slate-200 bg-white shadow-xl">
    <div class="flex items-center gap-2 border-b border-slate-200 px-3 py-2 text-xs">
      <h2 class="font-semibold text-slate-800">AI review</h2>
      <span class="text-slate-500">Laravel docs style</span>
      <button class="toolbar-button ml-auto" type="button" @click="emit('close')">Close</button>
    </div>

    <form class="space-y-2 border-b border-slate-200 bg-slate-50 p-3 text-xs" @submit.prevent="startReview">
      <select v-model="scope" aria-label="What to review" class="field-input">
        <option v-for="[value, label] in scopeChoices" :key="value" :value>{{ label }}</option>
      </select>
      <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-slate-700">
        <label class="flex items-center gap-1.5"><input v-model="wording" type="checkbox" /> Wording</label>
        <label class="flex items-center gap-1.5"><input v-model="structure" type="checkbox" /> Structure</label>
        <template v-if="!inClaudeCode">
          <span class="mx-1 h-4 w-px bg-slate-200" />
          <label v-for="[value, label, hint] in MODELS" :key="value" :title="hint" class="flex items-center gap-1.5">
            <input v-model="model" :value name="model" type="radio" /> {{ label }}
          </label>
        </template>
      </div>
      <div v-if="inClaudeCode" class="space-y-1.5" data-claude-code>
        <p class="text-slate-600">Reviews run in Claude Code with your own Claude plan. In this project, run:</p>
        <div class="flex items-center gap-2">
          <code class="grow truncate rounded bg-white px-2 py-1 ring-1 ring-slate-200">{{ command }}</code>
          <button :disabled="!kinds.length" class="toolbar-button" type="button" @click="copyCommand">
            {{ copied ? 'Copied' : 'Copy' }}
          </button>
        </div>
        <p class="text-slate-500">
          The suggestions appear here when it's done.
          <button class="underline" type="button" @click="reviews.showLatest()">Check now</button>
        </p>
      </div>
      <button
        v-else
        :disabled="reviews.starting || running || !kinds.length"
        class="rounded bg-bene-teal px-3 py-1 font-semibold text-white disabled:opacity-40"
        type="submit">
        {{ running ? 'Reviewing…' : reviews.starting ? 'Starting…' : 'Review' }}
      </button>
    </form>

    <div class="space-y-1 px-3 pt-2 text-xs">
      <p v-if="reviews.error" class="text-rose-700">{{ reviews.error }}</p>
      <p v-if="reviews.notice" class="text-slate-600">{{ reviews.notice }}</p>
      <p v-if="reviews.slow" class="text-amber-800">
        The review hasn't started yet. Reviews run on their own queue worker (<code>ai-queue</code> in
        <code>composer dev</code>); check that it's running.
      </p>
      <p v-if="running" class="text-slate-600">Claude is reading the document. This usually takes a minute or two.</p>
      <p v-else-if="reviews.review?.status === 'awaiting'" class="text-slate-600">
        Claude Code is reviewing (review {{ reviews.review.id }}). Its suggestions appear here once they're imported.
      </p>
      <p v-else-if="reviews.review?.status === 'failed'" class="text-rose-700">
        The review failed: {{ reviews.review.error }}
      </p>
      <p v-else-if="reviews.review" class="text-slate-500">
        {{ pendingCount }} to go of {{ reviews.suggestions.length }} suggestions ·
        {{ MODEL_NAMES[reviews.review.model] ?? reviews.review.model }} · {{ ago(reviews.review.createdAt) }}
      </p>
    </div>

    <div v-if="reviews.suggestions.length" class="flex flex-wrap items-center gap-2 px-3 pt-2 text-xs">
      <select v-model="kind" aria-label="Kind" class="rounded border border-slate-300 px-1 py-0.5">
        <option value="all">All kinds</option>
        <option value="text">Wording</option>
        <option value="restructure">Structure</option>
      </select>
      <label class="flex items-center gap-1.5 text-slate-600"
        ><input v-model="showAll" type="checkbox" /> Show resolved</label
      >
      <button
        v-for="[rule, count] in batchRules"
        :key="rule"
        class="toolbar-button"
        type="button"
        @click="reviews.acceptRule(rule)">
        Accept all “{{ rule }}” ({{ count }})
      </button>
    </div>

    <div class="grow space-y-4 overflow-y-auto p-3">
      <p v-if="reviews.review?.status === 'done' && !visible.length" class="text-xs text-slate-500">
        {{ reviews.suggestions.length ? 'Nothing left to look at.' : 'No suggestions: this already reads well.' }}
      </p>

      <section v-for="group in groups" :key="group.id" class="space-y-2">
        <button
          :disabled="!group.node"
          class="w-full truncate text-left text-xs font-semibold text-slate-700 hover:underline disabled:no-underline"
          type="button"
          @click="editor.select(group.id)">
          {{ groupTitle(group) }}
        </button>

        <article
          v-for="suggestion in group.items"
          :key="suggestion.id"
          :class="[
            'space-y-2 rounded border p-2 text-xs',
            suggestion.status === 'pending' ? 'border-slate-200' : 'border-slate-100 opacity-60',
          ]"
          :data-suggestion="suggestion.id">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-700">{{ suggestion.rule }}</span>
            <span v-if="suggestion.kind === 'restructure'" class="rounded bg-indigo-50 px-1.5 py-0.5 text-indigo-800">
              structure
            </span>
            <span v-if="suggestion.severity === 'major'" class="rounded bg-amber-50 px-1.5 py-0.5 text-amber-800"
              >major</span
            >
            <span class="text-slate-600">{{ suggestion.rationale }}</span>
          </div>

          <p v-if="suggestion.kind === 'text'" class="leading-relaxed text-slate-800">
            <template v-for="(part, i) in diff(suggestion)" :key="i">
              <del v-if="part.type === 'del'" class="bg-rose-50 text-rose-800">{{ part.text }}</del>
              <ins v-else-if="part.type === 'ins'" class="bg-emerald-50 text-emerald-800 no-underline">{{
                part.text
              }}</ins>
              <span v-else>{{ part.text }}</span>
            </template>
          </p>

          <div v-else class="grid grid-cols-2 gap-2">
            <div class="space-y-1">
              <div class="text-[11px] text-slate-500">Now</div>
              <div class="prose-preview max-h-64 overflow-auto rounded border border-slate-100 p-2">
                <BlockList v-if="currentBlocks(suggestion).length" :nodes="currentBlocks(suggestion)" />
                <span v-else class="text-slate-400">Already changed</span>
              </div>
            </div>
            <div class="space-y-1">
              <div class="text-[11px] text-slate-500">Suggested</div>
              <div class="prose-preview max-h-64 overflow-auto rounded border border-emerald-100 p-2">
                <BlockList :nodes="previewNodes(suggestion.replacement ?? [])" />
              </div>
            </div>
          </div>

          <div v-if="suggestion.status === 'pending'" class="flex gap-2">
            <button
              :disabled="reviews.busy.has(suggestion.id)"
              class="rounded bg-bene-teal px-2 py-0.5 font-semibold text-white disabled:opacity-40"
              type="button"
              @click="reviews.accept(suggestion)">
              Accept
            </button>
            <button
              :disabled="reviews.busy.has(suggestion.id)"
              class="toolbar-button"
              type="button"
              @click="reviews.dismiss(suggestion)">
              Dismiss
            </button>
          </div>
          <p v-else class="text-[11px] text-slate-500">{{ STATUS[suggestion.status] }}</p>
        </article>
      </section>
    </div>
  </aside>
</template>

<style scoped>
/* the print components are sized for an A4 page; shrink them to fit side by side */
.prose-preview {
  zoom: 0.75;
}
</style>
