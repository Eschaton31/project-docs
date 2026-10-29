<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import { useAuthStore } from '@/stores/auth.js'
import { useEditorStore } from '@/stores/editor.js'
import { useProseReviewStore } from '@/stores/proseReview.js'

import { BlockList } from '../BlockList.js'
import { blockLabel } from '../editing.js'
import { registry } from '../registry.js'
import ScaffoldForm from './ScaffoldForm.vue'
import { ago } from './history.js'
import { plainText, wordDiff } from './proseDiff.js'

/**
 * AI prose review (admins only): Claude checks the document, or one topic, against a style guide based on
 * the Laravel docs and suggests wording changes, better block structure and (when asked) new content for thin
 * sections; or drafts documentation from uploaded R scripts (scaffold). Nothing changes until a suggestion is
 * accepted, and accepted changes can be undone like any other edit.
 */
const emit = defineEmits(['close'])

const editor = useEditorStore()
const reviews = useProseReviewStore()
const auth = useAuthStore()

// the server's answer when the panel loads; the signed-in user's abilities until then (they can predate the feature)
const canScaffold = computed(() => reviews.canScaffold ?? !!auth.user?.abilities?.aiScaffold)
// review the document's prose, or draft documentation from R scripts
const mode = ref(reviews.review?.mode === 'scaffold' && canScaffold.value ? 'scaffold' : 'review')
const isScaffold = computed(() => reviews.review?.mode === 'scaffold')
// the latest review loads after the panel opens: show the form that matches it
watch(
  () => reviews.review?.id,
  () => canScaffold.value && (mode.value = isScaffold.value ? 'scaffold' : 'review')
)

// suggestions for new sections are grouped together, ahead of the ones about existing blocks
const NEW_SECTIONS = 'new-sections'

const MODELS = [
  ['sonnet', 'Sonnet', 'faster, for everyday reviews'],
  ['opus', 'Opus', 'slower, more thorough'],
]

const scope = ref(reviews.scopeId ?? 'document')
const wording = ref(true)
const structure = ref(true)
// adds content rather than tidying what's there, so it's asked for explicitly
const expand = ref(false)
const brief = ref('')
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
const kinds = computed(() =>
  [wording.value && 'text', structure.value && 'restructure', expand.value && 'expand'].filter(Boolean)
)
const briefText = computed(() => (expand.value ? brief.value.trim() : ''))

// this user can't start reviews here (with Claude Code, only the plan's owner can): show the command to run
// in Claude Code instead
const inClaudeCode = computed(() => !reviews.canStart)
const command = computed(() =>
  [
    `/prose-review ${editor.document.slug}`,
    scope.value !== 'document' && `--block=${scope.value}`,
    // text and restructure are the command's default
    kinds.value.join(',') !== 'text,restructure' && `--kinds=${kinds.value.join(',')}`,
    // last, since everything after it is the brief; on one line so it pastes as one command
    briefText.value && `--brief: ${briefText.value.replace(/\s+/g, ' ')}`,
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
    brief: briefText.value || null,
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

/**
 * Suggestions grouped by the block they're about, in document order; ones on removed blocks go last. New
 * sections come first, in the template's order.
 */
const groups = computed(() => {
  const byBlock = new Map()
  for (const suggestion of visible.value) {
    const id = suggestion.kind === 'scaffold' ? NEW_SECTIONS : suggestion.blockIds[0]
    if (!byBlock.has(id)) byBlock.set(id, [])
    byBlock.get(id).push(suggestion)
  }
  byBlock.get(NEW_SECTIONS)?.sort((a, b) => a.order - b.order)

  return [...byBlock.entries()]
    .map(([id, items]) => ({ id, node: id === NEW_SECTIONS ? null : editor.get(id), items }))
    .sort((a, b) => rank(a.id) - rank(b.id))
})

const rank = (id) => (id === NEW_SECTIONS ? -1 : (order.value.get(id) ?? Infinity))

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
  if (group.id === NEW_SECTIONS) return 'New sections'
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
const status = (suggestion) =>
  suggestion.kind === 'scaffold' && suggestion.status === 'stale'
    ? 'The topic it goes into was deleted'
    : STATUS[suggestion.status]

/** Where a new section goes: after the existing block it was given, wherever the template's order puts it. */
function placement(suggestion) {
  const anchor = suggestion.blockIds[0] && editor.get(suggestion.blockIds[0])
  if (!anchor) return 'Goes first, in the template’s order'
  const { label, detail } = blockLabel(anchor)

  return `Goes after “${detail || label}”, in the template’s order`
}

/** Where an expansion goes, relative to the block it's grouped under. */
const POSITIONS = {
  before: 'Adds before this block',
  after: 'Adds after this block',
  start: 'Adds at the start of this section',
  end: 'Adds at the end of this section',
}

const SOURCES = {
  document: 'from the document',
  brief: 'from your brief',
  code: 'from the scripts',
  run_notes: 'from your run notes',
}

/** Expansions that lean on the model's own knowledge, which someone should check before accepting. */
const needsFactCheck = (suggestion) => suggestion.sources?.includes('general')

/** "clean.R 10–42" for each script range a scaffold suggestion draws on. */
function citations(suggestion) {
  const byRef = new Map((reviews.review?.sources ?? []).map((source) => [source.ref, source]))

  return (suggestion.citations ?? []).map((citation) => ({
    ...citation,
    source: byRef.get(citation.file),
    label: `${byRef.get(citation.file)?.name ?? citation.file} ${citation.from === citation.to ? citation.from : `${citation.from}–${citation.to}`}`,
  }))
}

// the script a citation points at: { name, lines, from, to }
const viewing = ref(null)

async function view(citation) {
  if (!citation.source) return
  try {
    const text = await reviews.sourceText(citation.source.id)
    viewing.value = {
      name: citation.source.name,
      lines: text.replace(/\n$/, '').split('\n'),
      from: citation.from,
      to: citation.to,
    }
  } catch {
    viewing.value = { name: citation.source.name, lines: [], from: 0, to: 0, gone: true }
  }
}

/** Hovering a suggestion marks its blocks in the canvas and outline, and for wording the phrase it changes. */
function pointAt(suggestion) {
  editor.point(suggestion.blockIds, suggestion.kind === 'text' ? plainText(suggestion.original) : null)
}

/** Where a block is when the canvas shows another topic, so it can't be marked there (clicking goes to it). */
function elsewhere(group) {
  if (!group.node) return null
  const topic = editor.rootOf(group.id)
  if (!topic || topic.id === editor.canvasRootId) return null
  if (topic.id === group.id) return 'not on the page, click to show'
  const { label, detail } = blockLabel(topic)

  return `in “${detail || label}”, click to show`
}

function onKeydown(event) {
  if (event.key !== 'Escape') return
  if (viewing.value) viewing.value = null
  else emit('close')
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  reviews.load(editor.document)
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  editor.point(null)
})
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

    <div v-if="canScaffold" class="flex gap-1 border-b border-slate-200 bg-slate-50 px-3 pt-2 text-xs" role="tablist">
      <button
        v-for="[value, label] in [
          ['review', 'Review'],
          ['scaffold', 'Scaffold from code'],
        ]"
        :key="value"
        :aria-selected="mode === value"
        :class="[
          'rounded-t border border-b-0 px-2 py-1',
          mode === value
            ? 'border-slate-200 bg-white font-semibold text-slate-800'
            : 'border-transparent text-slate-500',
        ]"
        role="tab"
        type="button"
        @click="mode = value">
        {{ label }}
      </button>
    </div>

    <div v-if="mode === 'scaffold'" class="border-b border-slate-200 bg-slate-50 p-3 text-xs">
      <ScaffoldForm :models="inClaudeCode ? [] : MODELS" />
    </div>

    <form v-else class="space-y-2 border-b border-slate-200 bg-slate-50 p-3 text-xs" @submit.prevent="startReview">
      <select v-model="scope" aria-label="What to review" class="field-input">
        <option v-for="[value, label] in scopeChoices" :key="value" :value>{{ label }}</option>
      </select>
      <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-slate-700">
        <label class="flex items-center gap-1.5"><input v-model="wording" type="checkbox" /> Wording</label>
        <label class="flex items-center gap-1.5"><input v-model="structure" type="checkbox" /> Structure</label>
        <label
          class="flex items-center gap-1.5"
          title="Add introductions, explanations and examples where a section is thin"
          ><input v-model="expand" type="checkbox" /> Expand</label
        >
        <template v-if="!inClaudeCode">
          <span class="mx-1 h-4 w-px bg-slate-200" />
          <label v-for="[value, label, hint] in MODELS" :key="value" :title="hint" class="flex items-center gap-1.5">
            <input v-model="model" :value name="model" type="radio" /> {{ label }}
          </label>
        </template>
      </div>
      <textarea
        v-if="expand"
        v-model="brief"
        aria-label="Brief"
        class="field-input min-h-16"
        maxlength="4000"
        placeholder="Optional: what to cover, and facts Claude may use (e.g. “Explain the retry setting: 3 tries, 30 seconds apart”). Anything Claude adds from general knowledge is marked for a fact check." />
      <div v-if="inClaudeCode" class="space-y-1.5" data-claude-code>
        <p class="text-slate-600">
          Reviews run in Claude Code on the owner's Claude plan, so only they can start one here. With Claude Code in
          this project, you can run:
        </p>
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
      <div v-else class="flex items-center gap-3">
        <button
          :disabled="reviews.starting || running || !kinds.length"
          class="rounded bg-bene-teal px-3 py-1 font-semibold text-white disabled:opacity-40"
          type="submit">
          {{ running ? 'Reviewing…' : reviews.starting ? 'Starting…' : 'Review' }}
        </button>
        <span v-if="reviews.runner === 'claude-code'" class="text-slate-500">Runs in Claude Code with your plan</span>
      </div>
    </form>

    <div class="space-y-1 px-3 pt-2 text-xs">
      <p v-if="reviews.error" class="text-rose-700">{{ reviews.error }}</p>
      <p v-if="reviews.notice" class="text-slate-600">{{ reviews.notice }}</p>
      <p v-if="reviews.slow" class="text-amber-800">
        The review hasn't started yet. Reviews run on their own queue worker (<code>ai-queue</code> in
        <code>composer dev</code>); check that it's running.
      </p>
      <p v-if="running" class="text-slate-600">
        {{
          isScaffold
            ? 'Claude is reading the scripts and the document. This can take a few minutes.'
            : 'Claude is reading the document. This usually takes a minute or two.'
        }}
      </p>
      <p v-else-if="reviews.review?.status === 'awaiting' && isScaffold" class="text-slate-600">
        Waiting for Claude Code: run <code>/doc-scaffold --review={{ reviews.review.id }}</code
        >. The drafts appear here once they're imported.
      </p>
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
        <option value="expand">Expand</option>
        <option v-if="isScaffold" value="scaffold">New sections</option>
      </select>
      <label class="flex items-center gap-1.5 text-slate-600"
        ><input v-model="showAll" type="checkbox" /> Show resolved</label
      >
      <button
        v-if="isScaffold && pendingCount > 1"
        class="toolbar-button"
        data-accept-all
        type="button"
        @click="reviews.acceptAll()">
        Accept all ({{ pendingCount }})
      </button>
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
      <div v-if="viewing" class="rounded border border-slate-300 bg-white text-xs shadow" data-source-viewer>
        <div class="flex items-center gap-2 border-b border-slate-200 px-2 py-1">
          <span class="font-mono font-semibold text-slate-700">{{ viewing.name }}</span>
          <span v-if="!viewing.gone" class="text-slate-500">lines {{ viewing.from }}–{{ viewing.to }}</span>
          <button class="toolbar-button ml-auto" type="button" @click="viewing = null">Close</button>
        </div>
        <p v-if="viewing.gone" class="p-2 text-slate-500">
          This script is no longer stored (scripts are kept 30 days).
        </p>
        <pre v-else class="max-h-72 overflow-auto p-2 font-mono text-[11px] leading-snug"><span
            v-for="(line, i) in viewing.lines"
            :key="i"
            :class="['block', i + 1 >= viewing.from && i + 1 <= viewing.to ? 'bg-amber-50' : 'text-slate-500']"
            ><span class="mr-2 inline-block w-8 select-none text-right text-slate-400">{{ i + 1 }}</span>{{ line }}</span></pre>
      </div>

      <p v-if="reviews.review?.status === 'done' && !visible.length" class="text-xs text-slate-500">
        {{
          reviews.suggestions.length
            ? 'Nothing left to look at.'
            : isScaffold
              ? 'No suggestions: the document already covers these scripts.'
              : 'No suggestions: this already reads well.'
        }}
      </p>

      <section v-for="group in groups" :key="group.id" class="space-y-2">
        <button
          :disabled="!group.node"
          class="w-full truncate text-left text-xs font-semibold text-slate-700 hover:underline disabled:no-underline"
          type="button"
          @blur="editor.point(null)"
          @click="editor.select(group.id)"
          @focus="editor.point([group.id])"
          @mouseenter="editor.point([group.id])"
          @mouseleave="editor.point(null)">
          {{ groupTitle(group) }}
          <span v-if="elsewhere(group)" class="font-normal text-slate-400" data-elsewhere>
            · {{ elsewhere(group) }}
          </span>
        </button>

        <article
          v-for="suggestion in group.items"
          :key="suggestion.id"
          :class="[
            'space-y-2 rounded border p-2 text-xs transition-colors',
            suggestion.status === 'pending'
              ? 'border-slate-200 hover:border-amber-400 hover:bg-amber-50/40'
              : 'border-slate-100 opacity-60',
          ]"
          :data-suggestion="suggestion.id"
          @focusin="pointAt(suggestion)"
          @focusout="editor.point(null)"
          @mouseenter="pointAt(suggestion)"
          @mouseleave="editor.point(null)">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-700">{{ suggestion.rule }}</span>
            <span v-if="suggestion.kind === 'restructure'" class="rounded bg-indigo-50 px-1.5 py-0.5 text-indigo-800">
              structure
            </span>
            <template v-if="suggestion.kind === 'expand' || suggestion.kind === 'scaffold'">
              <span v-if="suggestion.kind === 'scaffold'" class="rounded bg-emerald-50 px-1.5 py-0.5 text-emerald-800"
                >new section</span
              >
              <span v-else class="rounded bg-sky-50 px-1.5 py-0.5 text-sky-800">expand</span>
              <span
                v-if="needsFactCheck(suggestion)"
                class="rounded bg-rose-50 px-1.5 py-0.5 font-medium text-rose-800"
                data-fact-check
                title="Uses Claude's general knowledge, not this document or your brief: check it before accepting"
                >check facts</span
              >
              <span v-for="source in suggestion.sources.filter((s) => SOURCES[s])" :key="source" class="text-slate-500">
                {{ SOURCES[source] }}
              </span>
            </template>
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

          <div
            v-else-if="suggestion.kind === 'expand' || suggestion.kind === 'scaffold'"
            class="space-y-1"
            data-expansion>
            <div class="text-[11px] text-slate-500">
              {{ suggestion.kind === 'scaffold' ? placement(suggestion) : POSITIONS[suggestion.position] }}
            </div>
            <div v-if="suggestion.citations?.length" class="flex flex-wrap gap-1" data-citations>
              <button
                v-for="citation in citations(suggestion)"
                :key="`${citation.file}-${citation.from}`"
                class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-700 hover:bg-slate-200"
                title="Show these lines of the script"
                type="button"
                @click="view(citation)">
                {{ citation.label }}
              </button>
            </div>
            <div class="prose-preview max-h-64 overflow-auto rounded border border-emerald-100 p-2">
              <BlockList :nodes="previewNodes(suggestion.replacement ?? [])" />
            </div>
          </div>

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
          <p v-else class="text-[11px] text-slate-500">{{ status(suggestion) }}</p>
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
