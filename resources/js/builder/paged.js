import { Previewer } from 'pagedjs'
import Prism from 'prismjs'

import { RepeatTableHeaderHandler } from '@/composables/functions.js'

/**
 * Collects async work (e.g. Mermaid diagrams) that must finish before the document is paginated.
 * Components reach it through inject('renderTracker').
 */
export function createRenderTracker() {
  const pending = new Set()

  return {
    track(promise) {
      const settled = Promise.resolve(promise).catch(() => {})
      pending.add(settled)
      settled.finally(() => pending.delete(settled))
    },
    async settled() {
      while (pending.size) await Promise.all([...pending])
    },
  }
}

/**
 * Paginates the current page with Paged.js once everything has loaded, the same way the hand-built pages
 * do on window load. Paged.js replaces the body, so call this on a page loaded on its own (new tab/iframe).
 */
export async function paginate(tracker) {
  await tracker.settled()
  await headSettled()
  await document.fonts.ready
  await Promise.all(
    [...document.querySelectorAll('img')].map((image) => (image.complete ? null : image.decode().catch(() => {})))
  )

  Prism.highlightAll()
  // resize() needs an element: called bare (as main.js does on load) it throws and would stop pagination
  document.querySelectorAll('pre.line-numbers').forEach((pre) => Prism.plugins.lineNumbers?.resize(pre))

  const previewer = new Previewer()
  previewer.registerHandlers(RepeatTableHeaderHandler)
  await previewer.preview()

  // for the editor's preview iframe and the server-side PDF export
  window.PAGED_READY = true
  window.parent?.postMessage({ type: 'paged:ready' }, window.location.origin)
}

export { printFileName } from './fileName.js'

/**
 * Resolves once <head> has stopped changing: vue-code-block fetches its theme CSS after mounting, and
 * Paged.js only copies the styles that exist when it starts.
 */
function headSettled(quietMs = 300, maxMs = 5000) {
  return new Promise((resolve) => {
    const finish = () => {
      observer.disconnect()
      clearTimeout(quiet)
      clearTimeout(limit)
      resolve()
    }
    const observer = new MutationObserver(() => {
      clearTimeout(quiet)
      quiet = setTimeout(finish, quietMs)
    })
    let quiet = setTimeout(finish, quietMs)
    const limit = setTimeout(finish, maxMs)

    observer.observe(document.head, { childList: true, subtree: true, characterData: true })
  })
}
