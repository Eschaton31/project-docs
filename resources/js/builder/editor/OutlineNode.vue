<script setup>
import { computed, inject, ref } from 'vue'

import { reported, useEditorStore } from '@/stores/editor.js'

import { blockLabel } from '../editing.js'
import { canContain, registry } from '../registry.js'
import UserBadge from './UserBadge.vue'

/**
 * One row of the outline, recursive. Drag a row onto another: top/bottom edge drops it before/after,
 * the middle drops it inside (only where the nesting rules allow).
 */
const props = defineProps({
  node: { type: Object, required: true },
  depth: { type: Number, default: 0 },
})

const store = useEditorStore()
// { id } of the row being dragged, shared by the whole outline (provided by OutlineTree)
const dragging = inject('outlineDrag')
const zone = ref(null)

const label = computed(() => blockLabel(props.node))
const isTopic = computed(() => registry[props.node.type]?.topic)
const isCollapsed = computed(() => store.collapsed.has(props.node.id))
const isSelected = computed(() => store.selectedId === props.node.id)
const options = computed(() => props.node.options ?? {})
const holder = computed(() => store.lockedByOther(`block:${props.node.id}`))

function onDragStart(event) {
  dragging.id = props.node.id
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', props.node.id)
}

function dropZone(event) {
  const dragged = dragging.id && store.get(dragging.id)
  if (!dragged || store.isInside(props.node.id, dragged.id)) return null

  const parentType = store.get(store.parentIdOf(props.node.id))?.type ?? null
  const inside = canContain(props.node.type, dragged.type)
  const beside = canContain(parentType, dragged.type)
  const rect = event.currentTarget.getBoundingClientRect()
  const y = (event.clientY - rect.top) / rect.height

  if (inside && (!beside || (y > 0.25 && y < 0.75))) return 'inside'
  if (beside) return y < 0.5 ? 'before' : 'after'

  return null
}

function onDragOver(event) {
  zone.value = dropZone(event)
  if (zone.value) event.preventDefault()
}

async function onDrop() {
  const where = zone.value
  const id = dragging.id
  zone.value = null
  dragging.id = null
  if (!where || !id) return

  const parentId = store.parentIdOf(props.node.id)
  const target = {
    inside: { parentId: props.node.id },
    before: { parentId, beforeId: props.node.id },
    after: { parentId, afterId: props.node.id },
  }[where]
  await store.move(id, target).catch(reported)
}
</script>

<template>
  <li>
    <div
      :class="[
        'group flex cursor-pointer items-center gap-1 rounded py-0.5 pr-2 text-[13px]',
        isSelected ? 'bg-teal-100 text-slate-900' : 'hover:bg-slate-100',
        zone === 'before' ? 'shadow-[inset_0_2px_0_#0b6374]' : '',
        zone === 'after' ? 'shadow-[inset_0_-2px_0_#0b6374]' : '',
        zone === 'inside' ? 'ring-2 ring-inset ring-bene-teal' : '',
        options.hidden ? 'opacity-50' : '',
      ]"
      :data-outline-id="node.id"
      :style="{ paddingLeft: `${depth * 14 + 4}px` }"
      draggable="true"
      @click="store.select(node.id)"
      @dragend="dragging.id = null"
      @dragleave="zone = null"
      @dragover="onDragOver"
      @dragstart="onDragStart"
      @drop.prevent="onDrop">
      <button
        v-if="node.children.length"
        :aria-label="isCollapsed ? 'Expand' : 'Collapse'"
        class="w-4 shrink-0 text-slate-400 hover:text-slate-700"
        type="button"
        @click.stop="store.toggle(node.id)">
        {{ isCollapsed ? '▸' : '▾' }}
      </button>
      <span v-else class="w-4 shrink-0" />

      <template v-if="isTopic">
        <span class="truncate font-semibold">{{ label.detail || label.label }}</span>
      </template>
      <template v-else>
        <span class="shrink-0 text-slate-600">{{ label.label }}</span>
        <span class="truncate text-slate-400">{{ label.detail }}</span>
      </template>

      <UserBadge
        v-if="holder"
        :color="holder.color"
        :name="holder.name"
        :title="`${holder.name} is editing this`"
        class="ml-auto"
        size="sm" />
      <span v-if="options.draft" class="ml-auto shrink-0 rounded bg-amber-100 px-1 text-[10px] text-amber-800"
        >draft</span
      >
      <span v-if="options.pageBreakBefore" class="shrink-0 text-[10px] text-slate-400" title="Starts on a new page"
        >⤓</span
      >
    </div>

    <ul v-if="!isCollapsed && node.children.length">
      <OutlineNode v-for="child in node.children" :key="child.id" :depth="depth + 1" :node="child" />
    </ul>
  </li>
</template>
