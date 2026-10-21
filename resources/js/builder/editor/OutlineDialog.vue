<script setup>
import { computed, ref } from 'vue'

import { reported, useEditorStore } from '@/stores/editor.js'

import { TOPIC_LADDER, outlineToNodes } from '../editing.js'
import { registry } from '../registry.js'

/**
 * Outline mode: type or paste a heading skeleton, one heading per line, indented two spaces per level.
 * Headings go under the selected topic (or the topic around the selection), or the top level.
 */
const emit = defineEmits(['close'])

const store = useEditorStore()
const text = ref('')
const busy = ref(false)

const target = computed(() => {
  let id = store.selectedId
  while (id && !registry[store.get(id).type].topic) id = store.parentIdOf(id)
  if (!id) return { parentId: null, type: 'PrimaryTopic', where: 'the top level of the document' }

  const topic = store.get(id)
  const level = TOPIC_LADDER.indexOf(topic.type)
  if (level === TOPIC_LADDER.length - 1) return null

  return { parentId: id, type: TOPIC_LADDER[level + 1], where: `“${topic.props.title}”` }
})

const nodes = computed(() => (target.value ? outlineToNodes(text.value, target.value.type) : []))
const count = computed(() => countNodes(nodes.value))

async function insert() {
  busy.value = true
  const last = store.childrenOf(target.value.parentId).at(-1)
  try {
    await store.insert({ parentId: target.value.parentId, ...(last ? { afterId: last.id } : {}) }, nodes.value)
    emit('close')
  } catch (error) {
    reported(error)
  } finally {
    busy.value = false
  }
}

function countNodes(list) {
  return list.reduce((total, node) => total + 1 + countNodes(node.children), 0)
}
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-6" @click.self="emit('close')">
    <div class="w-full max-w-xl space-y-3 rounded-lg bg-white p-5 shadow-xl">
      <h2 class="text-sm font-bold text-slate-800">Paste an outline</h2>

      <template v-if="target">
        <p class="text-xs text-slate-600">
          One heading per line; indent two spaces (or a tab) per level. They are added at the end of
          {{ target.where }}, starting at {{ registry[target.type].label }}.
        </p>
        <textarea
          v-model="text"
          class="field-input min-h-64 font-mono text-xs"
          placeholder="HIV TREATMENT REGISTRY&#10;  Data Catalog&#10;    Correction Tables&#10;  Set-up"
          spellcheck="false" />
        <div class="flex items-center justify-between">
          <span class="text-xs text-slate-500">{{ count }} headings</span>
          <div class="flex gap-2">
            <button class="toolbar-button" type="button" @click="emit('close')">Cancel</button>
            <button
              :disabled="!count || busy"
              class="rounded bg-bene-teal px-3 py-1 text-xs font-semibold text-white disabled:opacity-40"
              type="button"
              @click="insert">
              {{ busy ? 'Adding…' : 'Add headings' }}
            </button>
          </div>
        </div>
      </template>
      <template v-else>
        <p class="text-xs text-slate-600">
          Quaternary topics are the deepest heading level. Select a higher topic to add headings under it.
        </p>
        <div class="text-right">
          <button class="toolbar-button" type="button" @click="emit('close')">Close</button>
        </div>
      </template>
    </div>
  </div>
</template>
