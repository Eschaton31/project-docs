<script setup>
import { computed } from 'vue'

import { useAuthStore } from '@/stores/auth.js'
import { reported, useEditorStore } from '@/stores/editor.js'
import { useProseReviewStore } from '@/stores/proseReview.js'

import { OPTION_FIELDS, blockEditing, blockLabel, newBlock, toTemplate } from '../editing.js'
import { allowedChildren, registry } from '../registry.js'
import AddBlockMenu from './AddBlockMenu.vue'
import BlockHistory from './BlockHistory.vue'
import DocumentSettings from './DocumentSettings.vue'
import FieldInput from './FieldInput.vue'
import UserBadge from './UserBadge.vue'

const emit = defineEmits(['outline'])

const store = useEditorStore()
const reviews = useProseReviewStore()
const auth = useAuthStore()
const canReview = computed(() => !!auth.user?.abilities?.aiReview)

const node = computed(() => store.selected)
const definition = computed(() => node.value && registry[node.value.type])
const fields = computed(() => (node.value ? (blockEditing[node.value.type]?.fields ?? []) : []))
const parentId = computed(() => (node.value ? store.parentIdOf(node.value.id) : null))
const parentType = computed(() => (parentId.value ? store.get(parentId.value).type : null))
const siblings = computed(() => (node.value ? store.childrenOf(parentId.value) : []))
const position = computed(() => siblings.value.findIndex((sibling) => sibling.id === node.value?.id))

const insideTypes = computed(() =>
  node.value && definition.value.slot === 'blocks' ? allowedChildren(node.value.type) : []
)
const besideTypes = computed(() => (node.value ? allowedChildren(parentType.value) : []))

// someone else is editing this block (read-only here), or a block inside it (can't move/delete it)
const holder = computed(() => (node.value ? store.lockedByOther(`block:${node.value.id}`) : null))
const subtreeHolder = computed(() => (node.value ? store.subtreeLockedByOther(node.value.id) : null))

function setProp(name, value) {
  store.edit(node.value.id, { props: { [name]: value } })
}

function setOption(name, value) {
  // unchecked/empty options are removed rather than stored as false/''
  store.edit(node.value.id, { options: { [name]: value === false || value === '' ? undefined : value } })
}

const addInside = (type) => store.insert({ parentId: node.value.id }, [newBlock(type)]).catch(reported)
const addAfter = (type) =>
  store.insert({ parentId: parentId.value, afterId: node.value.id }, [newBlock(type)]).catch(reported)
const duplicate = () =>
  store.insert({ parentId: parentId.value, afterId: node.value.id }, [toTemplate(node.value)]).catch(reported)

function moveUp() {
  store
    .move(node.value.id, { parentId: parentId.value, beforeId: siblings.value[position.value - 1].id })
    .catch(reported)
}

function moveDown() {
  store
    .move(node.value.id, { parentId: parentId.value, afterId: siblings.value[position.value + 1].id })
    .catch(reported)
}

function remove() {
  const count = countBlocks(node.value.children)
  const name = blockLabel(node.value)
  const what = `${name.label}${name.detail ? ` “${name.detail}”` : ''}`
  if (count && !confirm(`Delete ${what} and the ${count} blocks inside it? You can undo this.`)) return

  store.remove(node.value.id).catch(reported)
}

function countBlocks(nodes) {
  return nodes.reduce((count, child) => count + 1 + countBlocks(child.children), 0)
}
</script>

<template>
  <DocumentSettings v-if="!node" @outline="emit('outline')" />

  <div v-else :key="node.id" class="space-y-5 p-4">
    <div class="space-y-3">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-bold text-slate-800">{{ definition.label }}</h2>
        <button class="text-xs text-slate-500 underline" type="button" @click="store.select(null)">
          Document settings
        </button>
      </div>

      <div class="flex flex-wrap gap-1.5">
        <AddBlockMenu v-if="insideTypes.length" :types="insideTypes" label="Add inside…" @add="addInside" />
        <AddBlockMenu :types="besideTypes" label="Add after…" @add="addAfter" />
        <button
          v-if="definition.topic && node.type !== 'QuaternaryTopic'"
          class="toolbar-button"
          type="button"
          @click="emit('outline')">
          Paste outline…
        </button>
        <button
          v-if="definition.topic && canReview"
          class="toolbar-button"
          type="button"
          @click="reviews.openPanel({ scope: node.id })">
          AI review this section…
        </button>
      </div>
      <div class="flex flex-wrap gap-1.5">
        <button :disabled="position <= 0 || !!subtreeHolder" class="toolbar-button" type="button" @click="moveUp">
          ↑ Up
        </button>
        <button
          :disabled="position >= siblings.length - 1 || !!subtreeHolder"
          class="toolbar-button"
          type="button"
          @click="moveDown">
          ↓ Down
        </button>
        <button class="toolbar-button" type="button" @click="duplicate">Duplicate</button>
        <button :disabled="!!subtreeHolder" class="toolbar-button text-rose-700" type="button" @click="remove">
          Delete
        </button>
      </div>
    </div>

    <div
      v-if="holder || subtreeHolder"
      class="flex items-center gap-2 rounded bg-amber-50 px-3 py-2 text-xs text-amber-900">
      <UserBadge :color="(holder ?? subtreeHolder).color" :name="(holder ?? subtreeHolder).name" size="sm" />
      <span v-if="holder">{{ holder.name }} is editing this block. You can look, and edit once they move on.</span>
      <span v-else
        >{{ subtreeHolder.name }} is editing something inside this block, so it can't be moved or deleted.</span
      >
    </div>

    <!-- fieldset disables every input inside while someone else holds the block -->
    <fieldset :disabled="!!holder" class="space-y-5 disabled:opacity-60">
      <section v-if="fields.length" class="space-y-3">
        <FieldInput
          v-for="field in fields"
          :key="field.name"
          :context="node.props"
          :field
          :model-value="node.props?.[field.name]"
          :readonly="!!holder"
          @update:model-value="setProp(field.name, $event)" />
      </section>
      <p v-else-if="definition.slot === 'blocks'" class="text-xs text-slate-500">
        This block only holds other blocks; select them in the outline or the page to edit.
      </p>

      <section class="space-y-2 border-t border-slate-200 pt-4">
        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Options</h3>
        <FieldInput
          v-for="field in OPTION_FIELDS"
          :key="field.name"
          :field
          :model-value="node.options?.[field.name]"
          @update:model-value="setOption(field.name, $event)" />
      </section>
    </fieldset>

    <BlockHistory :block-id="node.id" :readonly="!!holder" />
  </div>
</template>
