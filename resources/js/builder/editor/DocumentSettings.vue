<script setup>
import { computed } from 'vue'

import { reported, useEditorStore } from '@/stores/editor.js'

import { TITLE_PAGE_FIELDS, newBlock } from '../editing.js'
import { printFileName } from '../fileName.js'
import FieldInput from './FieldInput.vue'
import UserBadge from './UserBadge.vue'

/** Settings of the whole document, shown when no block is selected. */
const emit = defineEmits(['outline'])

const store = useEditorStore()
const doc = computed(() => store.document)
const meta = computed(() => doc.value.meta ?? {})
const holder = computed(() => store.lockedByOther('settings'))

const setMeta = (changes) => store.editDocument({ meta: { ...meta.value, ...changes } })
const setRunningTitle = (part, value) => setMeta({ runningTitle: { ...meta.value.runningTitle, [part]: value } })
const setTitlePage = (name, value) => store.editDocument({ titlePage: { ...doc.value.titlePage, [name]: value } })

function addTopic() {
  const last = store.roots.at(-1)
  store.insert({ parentId: null, ...(last ? { afterId: last.id } : {}) }, [newBlock('PrimaryTopic')]).catch(reported)
}
</script>

<template>
  <div class="space-y-5 p-4">
    <div class="space-y-2">
      <h2 class="text-sm font-bold text-slate-800">Document</h2>
      <div class="flex flex-wrap gap-1.5">
        <button class="toolbar-button" type="button" @click="addTopic">Add top-level topic</button>
        <button class="toolbar-button" type="button" @click="emit('outline')">Paste outline…</button>
      </div>
    </div>

    <div v-if="holder" class="flex items-center gap-2 rounded bg-amber-50 px-3 py-2 text-xs text-amber-900">
      <UserBadge :color="holder.color" :name="holder.name" size="sm" />
      {{ holder.name }} is editing the document settings. You can edit them once they move on.
    </div>

    <fieldset :disabled="!!holder" class="space-y-5 disabled:opacity-60">
      <section class="space-y-3">
        <FieldInput
          :field="{ name: 'title', label: 'Name in the document list', type: 'text' }"
          :model-value="doc.title"
          @update:model-value="store.editDocument({ title: $event })" />
        <FieldInput
          :field="{
            name: 'fileName',
            label: 'PDF file name',
            type: 'text',
            hint: `Date patterns in braces are filled in when printing: ${printFileName(meta.fileName || doc.slug)}`,
          }"
          :model-value="meta.fileName"
          @update:model-value="setMeta({ fileName: $event })" />
        <FieldInput
          :field="{ name: 'bold', label: 'Running title (bold part)', type: 'text' }"
          :model-value="meta.runningTitle?.bold"
          @update:model-value="setRunningTitle('bold', $event)" />
        <FieldInput
          :field="{ name: 'light', label: 'Running title (light part)', type: 'text' }"
          :model-value="meta.runningTitle?.light"
          @update:model-value="setRunningTitle('light', $event)" />
        <FieldInput
          :field="{ name: 'toc', label: 'Table of contents page', type: 'boolean' }"
          :model-value="meta.toc !== false"
          @update:model-value="setMeta({ toc: $event })" />
      </section>

      <section class="space-y-3 border-t border-slate-200 pt-4">
        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Title page</h3>
        <FieldInput
          v-for="field in TITLE_PAGE_FIELDS"
          :key="field.name"
          :field
          :model-value="doc.titlePage?.[field.name]"
          @update:model-value="setTitlePage(field.name, $event)" />
      </section>
    </fieldset>
  </div>
</template>
