import { errorMessage, http } from '@/builder/api.js'
import { listenToReviews } from '@/builder/realtime.js'
import { defineStore } from 'pinia'

import { ref } from 'vue'

import { useEditorStore } from './editor.js'

const POLL_MS = 3000
const AWAITING_POLL_MS = 15_000
const SLOW_AFTER_MS = 30_000

/**
 * AI prose review of the open document (admins only): start one, follow it, and accept or dismiss what it
 * suggests. Accepted changes are made by the server and handed to the editor store, which shows them and
 * makes them undoable like any other edit.
 */
export const useProseReviewStore = defineStore('proseReview', () => {
  const open = ref(false)
  // block to review next (a topic), or null for the whole document
  const scopeId = ref(null)
  const review = ref(null)
  const suggestions = ref([])
  const starting = ref(false)
  // queued for a while: the review worker may not be running
  const slow = ref(false)
  const error = ref(null)
  const notice = ref(null)
  const busy = ref(new Set())

  // 'claude-code': reviews are started from Claude Code (/prose-review) with the user's own plan, not from here
  const runner = ref('api')
  // whether this user's Review button starts one (with Claude Code: only the plan's owner)
  const canStart = ref(true)
  // after uploading scripts that wait for Claude Code: the /doc-scaffold command to run there
  const scaffoldCommand = ref(null)
  // whether drafting from R scripts is on, as the server says when the panel loads (null until then)
  const canScaffold = ref(null)

  let documentSlug = null
  let documentId = null
  let pollTimer = null
  let stopListening = null

  function openPanel({ scope = null } = {}) {
    scopeId.value = scope
    open.value = true
    // waiting reviews are only polled while the panel is open
    if (review.value?.status === 'awaiting') refresh().catch(report)
  }

  /** Shows the latest review of the document (if any) and follows its progress. */
  async function load(document) {
    if (documentId === document.id) return
    reset()
    documentId = document.id
    documentSlug = document.slug
    // also announces reviews exported from Claude Code, which this tab didn't start
    stopListening = listenToReviews(document.id, ({ review: updated }) => {
      review.value = updated
      refresh().catch(report)
    })

    await showLatest()
  }

  /** The document's newest review: on opening, and to pick up one started in Claude Code. */
  async function showLatest() {
    try {
      const { data } = await http.get(`/api/documents/${documentSlug}/reviews`)
      runner.value = data.runner ?? 'api'
      canStart.value = data.canStart ?? runner.value === 'api'
      canScaffold.value = data.canScaffold ?? null
      if (data.reviews[0]) {
        review.value = data.reviews[0]
        await refresh()
      }
    } catch (e) {
      error.value = errorMessage(e)
    }
  }

  /** options: { blockId?, kinds, model, brief? } */
  async function start(slug, options) {
    starting.value = true
    error.value = null
    try {
      await useEditorStore().flushAll()
      const { data } = await http.post(`/api/documents/${slug}/reviews`, options)
      review.value = data.review
      suggestions.value = []
      await refresh()
    } catch (e) {
      error.value = errorMessage(e)
    } finally {
      starting.value = false
    }
  }

  /**
   * Uploads R scripts to draft documentation from. form: FormData with files[], paths[], entry, runNotes,
   * blockId, model. Without the editor runner the upload waits for Claude Code, and `command` says what to run.
   */
  async function startScaffold(slug, form) {
    starting.value = true
    error.value = null
    scaffoldCommand.value = null
    try {
      await useEditorStore().flushAll()
      const { data } = await http.post(`/api/documents/${slug}/scaffolds`, form)
      review.value = data.review
      scaffoldCommand.value = data.command
      suggestions.value = []
      await refresh()

      return true
    } catch (e) {
      error.value = errorMessage(e)

      return false
    } finally {
      starting.value = false
    }
  }

  /** One uploaded script of the current scaffold, as stored (secrets redacted). */
  async function sourceText(sourceId) {
    const { data } = await http.get(`/api/reviews/${review.value.id}/sources/${sourceId}`)

    return data.text
  }

  async function refresh() {
    clearTimeout(pollTimer)
    if (!review.value) return

    const { data } = await http.get(`/api/reviews/${review.value.id}`)
    review.value = data.review
    suggestions.value = data.suggestions

    const { status } = data.review
    slow.value = status === 'queued' && Date.now() - new Date(data.review.createdAt) > SLOW_AFTER_MS
    if (status === 'queued' || status === 'running') {
      pollTimer = setTimeout(() => refresh().catch(report), POLL_MS)
    } else if (status === 'awaiting' && open.value) {
      // exported to Claude Code: done whenever someone runs the import, which may be much later
      pollTimer = setTimeout(() => refresh().catch(report), AWAITING_POLL_MS)
    }
  }

  async function accept(suggestion) {
    await act(suggestion, async () => {
      const editor = useEditorStore()
      await editor.flushAll()
      const { data } = await http.post(`/api/suggestions/${suggestion.id}/accept`)
      editor.applyChange(data.change)
      update(data.suggestion)
      // a restructure removes blocks other suggestions pointed at; the server marked those stale
      if (suggestion.kind === 'restructure') await refresh()
    })
  }

  async function dismiss(suggestion) {
    await act(suggestion, async () => {
      const { data } = await http.post(`/api/suggestions/${suggestion.id}/dismiss`)
      update(data.suggestion)
    })
  }

  /** Accepts every pending wording suggestion for one rule. */
  async function acceptRule(rule) {
    await acceptMany(`/api/reviews/${review.value.id}/accept-rule`, { rule })
  }

  /** Accepts every pending suggestion of a scaffold, in the template's order. */
  async function acceptAll() {
    await acceptMany(`/api/reviews/${review.value.id}/accept-all`, {})
  }

  async function acceptMany(url, payload) {
    error.value = notice.value = null
    try {
      const editor = useEditorStore()
      await editor.flushAll()
      const { data } = await http.post(url, payload)
      data.changes.forEach(editor.applyChange)
      suggestions.value = data.suggestions
      const skipped = [
        data.stale && `${data.stale} no longer matched the document`,
        data.locked && `${data.locked} were on blocks someone is editing`,
      ].filter(Boolean)
      notice.value = `Accepted ${data.accepted}${skipped.length ? `; skipped ${skipped.join(' and ')}` : ''}.`
    } catch (e) {
      error.value = errorMessage(e)
    }
  }

  async function act(suggestion, task) {
    error.value = null
    busy.value = new Set([...busy.value, suggestion.id])
    try {
      await task()
    } catch (e) {
      // stale (409): the server says what the suggestion is now
      if (e.response?.data?.suggestion) update(e.response.data.suggestion)
      error.value = errorMessage(e)
    } finally {
      busy.value = new Set([...busy.value].filter((id) => id !== suggestion.id))
    }
  }

  function update(changed) {
    suggestions.value = suggestions.value.map((suggestion) => (suggestion.id === changed.id ? changed : suggestion))
  }

  function report(e) {
    error.value = errorMessage(e)
  }

  function reset() {
    clearTimeout(pollTimer)
    stopListening?.()
    stopListening = null
    documentId = documentSlug = null
    runner.value = 'api'
    canStart.value = true
    scaffoldCommand.value = null
    canScaffold.value = null
    review.value = null
    suggestions.value = []
    slow.value = false
    error.value = notice.value = null
    busy.value = new Set()
  }

  return {
    open,
    scopeId,
    review,
    suggestions,
    starting,
    slow,
    error,
    notice,
    busy,
    runner,
    canStart,
    scaffoldCommand,
    canScaffold,
    openPanel,
    load,
    showLatest,
    start,
    startScaffold,
    sourceText,
    refresh,
    accept,
    dismiss,
    acceptRule,
    acceptAll,
    reset,
  }
})
