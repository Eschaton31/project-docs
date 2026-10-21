<script setup>
import { nextTick, onBeforeUnmount, onMounted, useTemplateRef, watch } from 'vue'

import { EditorContent, useEditor } from '@tiptap/vue-3'

import { richTextExtensions } from './richText.js'

/**
 * A RichText block edited in place on the page, with a small floating toolbar. Same formatter and
 * HTML shape as the panel editor. Escape or clicking elsewhere finishes.
 */
const props = defineProps({
  html: { type: String, default: '' },
  // where the user clicked, to put the caret there
  coords: { type: Object, default: null },
})
const emit = defineEmits(['update', 'done'])

const wrapper = useTemplateRef('wrapper')
let lastEmitted = props.html
// window.prompt (link address) takes focus away; that blur shouldn't end editing
let prompting = false

const editor = useEditor({
  content: props.html,
  extensions: richTextExtensions(),
  editorProps: {
    attributes: { class: 'inline-editing rounded-sm outline-none ring-2 ring-bene-teal/40' },
    handleKeyDown: (view, event) => {
      if (event.key !== 'Escape') return false
      emit('done')

      return true
    },
  },
  onUpdate: ({ editor }) => {
    lastEmitted = editor.getHTML()
    emit('update', lastEmitted)
  },
  onBlur: () => {
    if (!prompting) emit('done')
  },
})

// EditorContent moves the editor into the page on its next tick; the caret can only be placed from
// the click position once it is laid out there
onMounted(async () => {
  await nextTick()
  await new Promise((resolve) => requestAnimationFrame(resolve))
  const instance = editor.value
  if (!instance || instance.isDestroyed) return

  const position = props.coords && instance.view.posAtCoords({ left: props.coords.x, top: props.coords.y })
  instance
    .chain()
    .focus()
    .setTextSelection(position?.pos ?? instance.state.doc.content.size)
    .run()
  // only now: the click position is in screen coordinates, which scrolling would invalidate
  wrapper.value?.scrollIntoView({ block: 'nearest' })
})
onBeforeUnmount(() => editor.value?.destroy())

watch(
  () => props.html,
  (html) => {
    if (!editor.value || html === lastEmitted || editor.value.isFocused) return
    lastEmitted = html
    editor.value.commands.setContent(html ?? '', { emitUpdate: false })
  }
)

const run = (command) => editor.value?.chain().focus()[command]().run()
const active = (name) => editor.value?.isActive(name)

function editLink() {
  const current = editor.value.getAttributes('link').href ?? ''
  prompting = true
  const href = window.prompt('Link address (empty to remove the link)', current)
  prompting = false
  if (href === null) return editor.value.commands.focus()

  const chain = editor.value.chain().focus().extendMarkRange('link')
  href.trim() ? chain.setLink({ href: href.trim() }).run() : chain.unsetLink().run()
}
</script>

<template>
  <div ref="wrapper" class="relative scroll-mt-12" data-inline-editor>
    <!-- mousedown.prevent keeps the caret in the text while using the toolbar -->
    <div
      class="absolute bottom-full left-0 z-10 mb-1 flex gap-0.5 rounded border border-slate-200 bg-white p-0.5 font-sans shadow-sm"
      @mousedown.prevent>
      <button
        :class="['rt-button font-bold', { 'rt-active': active('bold') }]"
        title="Bold (Ctrl+B)"
        type="button"
        @click="run('toggleBold')">
        B
      </button>
      <button
        :class="['rt-button italic', { 'rt-active': active('italic') }]"
        title="Italic (Ctrl+I)"
        type="button"
        @click="run('toggleItalic')">
        I
      </button>
      <button
        :class="['rt-button underline', { 'rt-active': active('underline') }]"
        title="Underline (Ctrl+U)"
        type="button"
        @click="run('toggleUnderline')">
        U
      </button>
      <button
        :class="['rt-button font-mono', { 'rt-active': active('codetext') }]"
        title="Inline code (Ctrl+E)"
        type="button"
        @click="editor.chain().focus().toggleMark('codetext').run()">
        &lt;/&gt;
      </button>
      <button :class="['rt-button', { 'rt-active': active('link') }]" title="Link" type="button" @click="editLink">
        Link
      </button>
      <button class="rt-button" title="Done (Esc)" type="button" @click="emit('done')">Done</button>
    </div>
    <EditorContent :editor />
  </div>
</template>
