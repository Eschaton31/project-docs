<script setup>
import { javascript } from '@codemirror/lang-javascript'
import { json } from '@codemirror/lang-json'
import { sql } from '@codemirror/lang-sql'
import { xml } from '@codemirror/lang-xml'
import { yaml } from '@codemirror/lang-yaml'
import { StreamLanguage } from '@codemirror/language'
import { properties } from '@codemirror/legacy-modes/mode/properties'
import { r } from '@codemirror/legacy-modes/mode/r'
import { shell } from '@codemirror/legacy-modes/mode/shell'
import { Compartment, EditorState } from '@codemirror/state'
import { EditorView, basicSetup } from 'codemirror'

import { onBeforeUnmount, onMounted, useTemplateRef, watch } from 'vue'

/** Code editor (CodeMirror) with highlighting for the block's language. */
const props = defineProps({
  modelValue: { type: String, default: '' },
  language: { type: String, default: '' },
  readonly: Boolean,
})
const emit = defineEmits(['update:modelValue'])

// the languages used in the documents (see CODE_LANGUAGES); others get no highlighting
const LANGUAGES = {
  sql: () => sql(),
  clickhouse: () => sql(),
  r: () => StreamLanguage.define(r),
  ini: () => StreamLanguage.define(properties),
  yaml: () => yaml(),
  xml: () => xml(),
  json: () => json(),
  javascript: () => javascript(),
  js: () => javascript(),
  bash: () => StreamLanguage.define(shell),
  shell: () => StreamLanguage.define(shell),
  command: () => StreamLanguage.define(shell),
}
const languageFor = (name) => LANGUAGES[(name ?? '').toLowerCase()]?.() ?? []

const container = useTemplateRef('container')
const languageSlot = new Compartment()
const editableSlot = new Compartment()
let view = null

onMounted(() => {
  view = new EditorView({
    parent: container.value,
    state: EditorState.create({
      doc: props.modelValue ?? '',
      extensions: [
        basicSetup,
        EditorView.lineWrapping,
        languageSlot.of(languageFor(props.language)),
        editableSlot.of([EditorView.editable.of(!props.readonly), EditorState.readOnly.of(props.readonly)]),
        EditorView.updateListener.of((update) => {
          if (update.docChanged) emit('update:modelValue', update.state.doc.toString())
        }),
        EditorView.theme({
          '&': { fontSize: '12px', border: '1px solid #cbd5e1', borderRadius: '4px', backgroundColor: 'white' },
          '&.cm-focused': { outline: 'none', borderColor: '#0b6374' },
          '.cm-scroller': { fontFamily: "'JetBrains Mono', monospace", minHeight: '8rem', maxHeight: '28rem' },
        }),
      ],
    }),
  })
})
onBeforeUnmount(() => view?.destroy())

watch(
  () => props.language,
  (name) => view?.dispatch({ effects: languageSlot.reconfigure(languageFor(name)) })
)
watch(
  () => props.readonly,
  (readonly) =>
    view?.dispatch({
      effects: editableSlot.reconfigure([EditorView.editable.of(!readonly), EditorState.readOnly.of(readonly)]),
    })
)
// outside changes (undo, someone else's save) unless this editor is being typed in
watch(
  () => props.modelValue,
  (value) => {
    if (!view || view.hasFocus || value === view.state.doc.toString()) return
    view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: value ?? '' } })
  }
)
</script>

<template>
  <div ref="container" />
</template>
