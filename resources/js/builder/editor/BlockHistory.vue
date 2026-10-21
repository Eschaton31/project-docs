<script setup>
import { errorMessage, http } from '@/builder/api.js'

import { ref, watch } from 'vue'

import { useEditorStore } from '@/stores/editor.js'

import UserBadge from './UserBadge.vue'
import { ago, changedFields } from './history.js'

/**
 * Changes to one block, newest first. Restoring a version is an ordinary edit, so it saves under this
 * tab's lock and can be undone.
 */
const props = defineProps({
  blockId: { type: String, required: true },
  readonly: Boolean,
})

const store = useEditorStore()
const open = ref(false)
const entries = ref(null)
const error = ref(null)

const VERBS = {
  'block.create': 'added it',
  'block.insert': 'added it',
  'block.update': 'edited',
  'block.move': 'moved it',
  'block.delete': 'deleted it',
  'block.restore': 'restored it',
}

async function load() {
  error.value = null
  try {
    entries.value = (await http.get(`/api/blocks/${props.blockId}/revisions`)).data.data
  } catch (e) {
    error.value = errorMessage(e)
  }
}

watch(open, (isOpen) => isOpen && load())
watch(
  () => props.blockId,
  () => {
    entries.value = null
    if (open.value) load()
  }
)

/** Versions that can be brought back: the content a change left the block with. */
const restorable = (entry) => entry.after?.props && ['block.create', 'block.update', 'block.restore'].includes(entry.op)

async function restore(entry) {
  store.setBlock(props.blockId, { props: entry.after.props, options: entry.after.options })
  await store.flush(props.blockId)
  await load()
}
</script>

<template>
  <section class="border-t border-slate-200 pt-4">
    <button
      class="flex w-full items-center justify-between text-xs font-bold uppercase tracking-wide text-slate-500"
      type="button"
      @click="open = !open">
      History
      <span>{{ open ? '▾' : '▸' }}</span>
    </button>

    <div v-if="open" class="mt-2 space-y-2">
      <p v-if="error" class="text-xs text-rose-700">{{ error }}</p>
      <p v-else-if="!entries" class="text-xs text-slate-500">Loading…</p>
      <p v-else-if="!entries.length" class="text-xs text-slate-500">No changes recorded.</p>

      <ol v-else class="space-y-2">
        <li v-for="(entry, index) in entries" :key="entry.seq" class="flex gap-2 text-xs">
          <UserBadge :color="entry.user?.color" :name="entry.user?.name ?? '?'" size="sm" />
          <div class="min-w-0 grow">
            <div class="text-slate-700">
              <span class="font-semibold">{{ entry.user?.name ?? 'Someone' }}</span>
              {{ VERBS[entry.op] ?? entry.op }}
              <span class="text-slate-400">· {{ ago(entry.at) }}</span>
            </div>
            <div v-if="entry.op === 'block.update'" class="truncate text-[11px] text-slate-500">
              {{ changedFields(entry.before, entry.after).join(', ') || 'no visible change' }}
            </div>
          </div>
          <button
            v-if="index > 0 && restorable(entry)"
            :disabled="readonly"
            class="shrink-0 self-start text-[11px] text-bene-teal underline disabled:opacity-40"
            title="Make the block look like it did after this change"
            type="button"
            @click="restore(entry)">
            Restore
          </button>
        </li>
      </ol>
    </div>
  </section>
</template>
