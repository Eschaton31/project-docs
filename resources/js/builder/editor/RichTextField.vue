<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'

import { EditorContent, useEditor } from '@tiptap/vue-3'

import { fitsRichText, richTextExtensions } from './richText.js'

/**
 * Formatted text for RichText blocks: bold, italic, underline, inline code and links, with a raw HTML view.
 * HTML the formatter can't represent opens in the HTML view so nothing is silently dropped.
 */
const props = defineProps({
  modelValue: { type: String, default: '' },
  readonly: Boolean,
  id: String,
})
const emit = defineEmits(['update:modelValue'])

const complex = !fitsRichText(props.modelValue)
const mode = ref(complex ? 'html' : 'rich')
let lastEmitted = props.modelValue

const editor = useEditor({
  content: props.modelValue,
  extensions: richTextExtensions(),
  editable: !props.readonly,
  editorProps: { attributes: { class: 'field-input min-h-24 leading-relaxed', id: props.id ?? '' } },
  onUpdate: ({ editor }) => {
    lastEmitted = editor.getHTML()
    emit('update:modelValue', lastEmitted)
  },
})

// someone else's change, undo, or the HTML view: show it unless this editor is being typed in
watch(
  () => props.modelValue,
  (value) => {
    if (!editor.value || value === lastEmitted || editor.value.isFocused) return
    lastEmitted = value
    editor.value.commands.setContent(value ?? '', { emitUpdate: false })
  }
)
watch(
  () => props.readonly,
  (readonly) => editor.value?.setEditable(!readonly)
)
onBeforeUnmount(() => editor.value?.destroy())

const run = (command) => editor.value?.chain().focus()[command]().run()
const active = (name) => editor.value?.isActive(name)

function editLink() {
  const current = editor.value.getAttributes('link').href ?? ''
  const href = window.prompt('Link address (empty to remove the link)', current)
  if (href === null) return

  const chain = editor.value.chain().focus().extendMarkRange('link')
  href.trim() ? chain.setLink({ href: href.trim() }).run() : chain.unsetLink().run()
}

function onHtmlInput(event) {
  lastEmitted = event.target.value
  emit('update:modelValue', lastEmitted)
}

function showRich() {
  if (!fitsRichText(props.modelValue)) return
  editor.value.commands.setContent(props.modelValue ?? '', { emitUpdate: false })
  mode.value = 'rich'
}
</script>

<template>
  <div class="space-y-1">
    <div class="flex flex-wrap items-center gap-1">
      <template v-if="mode === 'rich'">
        <button
          :class="['rt-button font-bold', { 'rt-active': active('bold') }]"
          :disabled="readonly"
          title="Bold (Ctrl+B)"
          type="button"
          @click="run('toggleBold')">
          B
        </button>
        <button
          :class="['rt-button italic', { 'rt-active': active('italic') }]"
          :disabled="readonly"
          title="Italic (Ctrl+I)"
          type="button"
          @click="run('toggleItalic')">
          I
        </button>
        <button
          :class="['rt-button underline', { 'rt-active': active('underline') }]"
          :disabled="readonly"
          title="Underline (Ctrl+U)"
          type="button"
          @click="run('toggleUnderline')">
          U
        </button>
        <button
          :class="['rt-button font-mono', { 'rt-active': active('codetext') }]"
          :disabled="readonly"
          title="Inline code (Ctrl+E)"
          type="button"
          @click="editor.chain().focus().toggleMark('codetext').run()">
          &lt;/&gt;
        </button>
        <button
          :class="['rt-button', { 'rt-active': active('link') }]"
          :disabled="readonly"
          title="Link"
          type="button"
          @click="editLink">
          Link
        </button>
        <button
          :disabled="readonly"
          class="rt-button"
          title="Clear formatting"
          type="button"
          @click="run('unsetAllMarks')">
          Clear
        </button>
      </template>
      <button
        class="ml-auto text-[11px] text-slate-500 underline disabled:no-underline disabled:opacity-50"
        :disabled="mode === 'html' && !fitsRichText(modelValue)"
        type="button"
        @click="mode === 'rich' ? (mode = 'html') : showRich()">
        {{ mode === 'rich' ? 'Edit HTML' : 'Back to formatting' }}
      </button>
    </div>

    <EditorContent v-if="mode === 'rich'" :editor />
    <template v-else>
      <textarea
        :id
        :readonly
        :value="modelValue"
        class="field-input min-h-32 font-mono text-xs"
        spellcheck="false"
        @input="onHtmlInput" />
      <p v-if="!fitsRichText(modelValue)" class="text-[11px] text-amber-700">
        This text contains layout HTML the formatter can't keep, so it's edited as HTML.
      </p>
    </template>
  </div>
</template>
