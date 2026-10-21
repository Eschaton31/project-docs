import { Mark } from '@tiptap/core'
import Bold from '@tiptap/extension-bold'
import Document from '@tiptap/extension-document'
import HardBreak from '@tiptap/extension-hard-break'
import Italic from '@tiptap/extension-italic'
import Link from '@tiptap/extension-link'
import Text from '@tiptap/extension-text'
import Underline from '@tiptap/extension-underline'
import { UndoRedo } from '@tiptap/extensions'

/**
 * TipTap setup for RichText blocks. The document holds inline content only (no <p> wrappers) and marks
 * render as the tags the hand-built pages use (<b>, <i>, <u>, <codetext>, <a class="text-link">), so the
 * stored HTML keeps the same shape whichever way it was written.
 */
const InlineDocument = Document.extend({ content: 'inline*' })

const B = Bold.extend({ renderHTML: ({ HTMLAttributes }) => ['b', HTMLAttributes, 0] })
const I = Italic.extend({ renderHTML: ({ HTMLAttributes }) => ['i', HTMLAttributes, 0] })

/** <codetext> is the inline code style from app.css. */
export const CodeText = Mark.create({
  name: 'codetext',
  parseHTML: () => [{ tag: 'codetext' }],
  renderHTML: () => ['codetext', 0],
  addKeyboardShortcuts() {
    return { 'Mod-e': () => this.editor.commands.toggleMark(this.name) }
  },
})

export const richTextExtensions = () => [
  InlineDocument,
  Text,
  HardBreak,
  B,
  I,
  Underline,
  CodeText,
  Link.configure({
    openOnClick: false,
    autolink: false,
    HTMLAttributes: { class: 'text-link', target: null, rel: null },
  }),
  UndoRedo,
]

const INLINE_TAGS = new Set(['B', 'STRONG', 'I', 'EM', 'U', 'A', 'CODETEXT', 'BR'])

/**
 * Whether the formatter can edit this HTML without losing anything. Imported text sometimes carries
 * layout markup (a centred <div> with an <img>, styled spans); that is edited as raw HTML instead.
 */
export function fitsRichText(html) {
  const template = document.createElement('template')
  template.innerHTML = html ?? ''

  const tagAllowed = (element) =>
    INLINE_TAGS.has(element.tagName.toUpperCase()) ||
    // a bare <span> (left by <span v-html> on the hand-built pages) changes nothing; the formatter drops it
    (element.tagName === 'SPAN' && element.attributes.length === 0)

  const allowed = (element) =>
    tagAllowed(element) &&
    [...element.attributes].every(
      ({ name, value }) =>
        element.tagName === 'A' && (name === 'href' || (name === 'class' && value === 'text-link') || name === 'target')
    ) &&
    [...element.children].every(allowed)

  return [...template.content.children].every(allowed)
}
