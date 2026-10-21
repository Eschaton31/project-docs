import { errorMessage, http } from '@/builder/api.js'

import { ref } from 'vue'

const POLL_MS = 1500
const SLOW_AFTER_MS = 20_000
const GIVE_UP_AFTER_MS = 5 * 60_000

/**
 * One-click PDF: asks the server to render the document in headless Chrome, waits for the queued job,
 * then downloads the file.
 */
export function useExport() {
  const busy = ref(false)
  // taking long enough that the queue worker may not be running
  const slow = ref(false)
  const error = ref(null)

  async function downloadPdf(slug, mode = 'draft') {
    busy.value = true
    slow.value = false
    error.value = null
    const started = Date.now()

    try {
      let exported = (await http.post(`/api/documents/${encodeURIComponent(slug)}/exports`, { mode })).data.export
      while (exported.status === 'queued' || exported.status === 'running') {
        if (Date.now() - started > GIVE_UP_AFTER_MS) throw new Error('The PDF is taking too long.')
        await new Promise((resolve) => setTimeout(resolve, POLL_MS))
        slow.value = Date.now() - started > SLOW_AFTER_MS && exported.status === 'queued'
        exported = (await http.get(`/api/exports/${exported.id}`)).data.export
      }
      if (exported.status === 'failed') throw new Error(exported.error || 'The PDF could not be generated.')

      download(exported.downloadUrl)
    } catch (e) {
      error.value = errorMessage(e)
    } finally {
      busy.value = false
      slow.value = false
    }
  }

  return { busy, slow, error, downloadPdf }
}

/** The response is sent as an attachment, so this downloads without leaving the editor. */
export function download(url) {
  const link = Object.assign(document.createElement('a'), { href: url, download: '' })
  document.body.appendChild(link)
  link.click()
  link.remove()
}
