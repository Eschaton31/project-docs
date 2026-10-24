import { clientId, http } from '@/builder/api.js'
import { toTemplate } from '@/builder/editing.js'
import { joinDocument } from '@/builder/realtime.js'
import { defineStore } from 'pinia'

import { computed, markRaw, reactive, ref, shallowRef } from 'vue'

const SAVE_DELAY = 600
const UNDO_LIMIT = 100
// the server keeps locks for 60s; renew well before that
const LOCK_RENEW_MS = 20_000
const CLOCK_TICK_MS = 5_000

/** For UI calls: failed requests are already reported through the store's notice. */
export const reported = () => {}

/**
 * State of the open document and every change made to it.
 *
 * Property edits apply locally at once and are saved per block after a short pause. All requests for one
 * block run through a queue, so each carries the version returned by the previous one. Undo/redo replays
 * the inverse request (restore for delete, move back for move, …), so it covers only this tab's changes.
 *
 * Collaboration: the selected block (or the settings, once edited) is locked for this tab, which the
 * server enforces. Other people's changes arrive as "document changed" notifications; the store then
 * fetches the events since the last one it applied and replays them, skipping its own.
 */
export const useEditorStore = defineStore('editor', () => {
  const document = ref(null)
  const roots = ref([])
  const selectedId = ref(null)
  const collapsed = ref(new Set())
  const undoStack = shallowRef([])
  const redoStack = shallowRef([])
  const inFlight = ref(0)
  const failed = ref(false)
  const notice = ref(null)

  const members = ref([])
  const locks = ref(new Map())
  // ticks so lock expiry shows up without an event
  const clock = ref(Date.now())

  // id → { node, parentId }; rebuilt after structural changes (documents are a few hundred blocks)
  let index = new Map()
  // reactive so the save indicator follows pending edits
  const drafts = reactive(new Map())
  const queues = new Map()
  const documentDirty = ref(false)
  let documentTimer = null

  let lastSeq = 0
  let syncing = null
  let syncAgain = false
  let wantedLock = null
  let heldLock = null
  let leaveChannel = null
  let timers = []

  const selected = computed(() => (selectedId.value ? (index.get(selectedId.value)?.node ?? null) : null))
  /** Live locks held by other tabs. */
  const othersLocks = computed(() =>
    [...locks.value.values()].filter((lock) => lock.expiresAt > clock.value && lock.clientId !== clientId)
  )
  const status = computed(() => {
    if (failed.value) return 'error'
    if (inFlight.value > 0 || drafts.size > 0 || documentDirty.value) return 'saving'

    return 'saved'
  })

  // ── loading ────────────────────────────────────────────────────────────────

  async function load(slug) {
    reset()
    const { data } = await http.get(`/api/documents/${encodeURIComponent(slug)}`)
    document.value = data.document
    roots.value = data.blocks
    lastSeq = data.document.seq
    reindex()
    // start with the top-level topics open and everything below folded
    collapsed.value = new Set(
      [...index.values()].filter(({ parentId }) => parentId !== null).map(({ node }) => node.id)
    )

    locks.value = new Map()
    ;(data.locks ?? []).forEach(setLock)
    connect()
  }

  function reset() {
    disconnect()
    drafts.forEach((draft) => clearTimeout(draft.timer))
    drafts.clear()
    queues.clear()
    clearTimeout(documentTimer)
    documentDirty.value = false
    index = new Map()
    document.value = null
    roots.value = []
    selectedId.value = null
    undoStack.value = []
    redoStack.value = []
    failed.value = false
    notice.value = null
    members.value = []
    locks.value = new Map()
    lastSeq = 0
    wantedLock = null
    heldLock = null
  }

  function reindex() {
    index = new Map()
    const walk = (nodes, parentId) =>
      nodes.forEach((node) => {
        index.set(node.id, { node, parentId })
        walk(node.children, node.id)
      })
    walk(roots.value, null)
  }

  // ── tree queries ───────────────────────────────────────────────────────────

  const get = (id) => index.get(id)?.node ?? null
  const parentIdOf = (id) => index.get(id)?.parentId ?? null
  const childrenOf = (parentId) => (parentId === null ? roots.value : (get(parentId)?.children ?? []))

  function ancestorsOf(id) {
    const ids = []
    for (let parentId = parentIdOf(id); parentId !== null; parentId = parentIdOf(parentId)) ids.unshift(parentId)

    return ids
  }

  /** Top-level block containing id (what the canvas shows). */
  function rootOf(id) {
    return get(ancestorsOf(id)[0] ?? id)
  }

  function isInside(id, ancestorId) {
    return id === ancestorId || ancestorsOf(id).includes(ancestorId)
  }

  /** Where a block sits, as the neighbours the API understands. */
  function locationOf(id) {
    const parentId = parentIdOf(id)
    const siblings = childrenOf(parentId)
    const at = siblings.findIndex((node) => node.id === id)

    if (at > 0) return { parentId, afterId: siblings[at - 1].id }
    if (siblings.length > 1) return { parentId, beforeId: siblings[1].id }

    return { parentId }
  }

  /** Selecting a block also claims its lock (and gives up the previous one). */
  function select(id) {
    selectedId.value = id
    if (id) reveal(id)
    claim(id ? `block:${id}` : null)
  }

  function reveal(id) {
    const open = new Set(collapsed.value)
    ancestorsOf(id).forEach((ancestor) => open.delete(ancestor))
    collapsed.value = open
  }

  function toggle(id) {
    const next = new Set(collapsed.value)
    next.has(id) ? next.delete(id) : next.add(id)
    collapsed.value = next
  }

  // ── property edits ─────────────────────────────────────────────────────────

  /** Applies {props?, options?} locally and schedules a save. */
  function edit(id, changes) {
    change(id, (node) => apply(node, changes))
  }

  /** Replaces a block's props and options wholesale, e.g. restoring an older version from its history. */
  function setBlock(id, values) {
    change(id, (node) => replace(node, values))
  }

  function change(id, mutate) {
    const node = get(id)
    if (!node) return

    // markRaw: the snapshot must stay a plain object (it is cloned and sent as-is)
    if (!drafts.has(id)) drafts.set(id, markRaw({ before: snapshot(node), timer: null }))
    mutate(node)

    const draft = drafts.get(id)
    clearTimeout(draft.timer)
    draft.timer = setTimeout(() => flush(id), SAVE_DELAY)
  }

  /** Saves pending edits of one block now; resolves when its queue is empty. */
  function flush(id) {
    const draft = drafts.get(id)
    if (draft) {
      clearTimeout(draft.timer)
      drafts.delete(id)
      const after = snapshot(get(id))
      if (JSON.stringify(after) !== JSON.stringify(draft.before)) {
        // version: what this save produced, so undo can tell whether someone changed the block since
        const entry = { kind: 'update', id, before: draft.before, after, version: null }
        record(entry)
        enqueue(id, async () => {
          entry.version = await saveBlock(id, after)
        })
      }
    }

    return queues.get(id) ?? Promise.resolve()
  }

  async function flushAll() {
    ;[...drafts.keys()].forEach(flush)
    const saves = [...queues.values()]
    if (documentDirty.value) saves.push(flushDocument())
    await Promise.all(saves)
  }

  /** @returns {Promise<number|undefined>} the new version, or undefined when the save was refused */
  async function saveBlock(id, values) {
    const node = get(id)
    if (!node) return

    try {
      const { data } = await request(http.patch(`/api/blocks/${id}`, { version: node.version, ...values }))
      node.version = data.block.version

      return node.version
    } catch (error) {
      const { status } = error.response ?? {}
      if (status !== 409 && status !== 423) throw error

      // someone else saved first (409) or holds the block (423): show what is actually saved
      const current = error.response.data.current
      if (current) {
        replace(node, current)
        node.version = current.version
      }
      if (status === 409) {
        notice.value = { type: 'warning', text: 'That block was changed by someone else; their version is shown.' }
      }
    }
  }

  // ── structure ──────────────────────────────────────────────────────────────

  /** Inserts new nodes (with children) at a position: { parentId, afterId?, beforeId? }. */
  async function insert(where, nodes, { recordUndo = true, selectFirst = true } = {}) {
    const { data } = await request(
      http.post(`/api/documents/${document.value.slug}/blocks/tree`, { ...where, blocks: nodes })
    )
    data.blocks.forEach((block) => place(where.parentId ?? null, block))
    reindex()

    if (recordUndo) record({ kind: 'insert', ids: data.blocks.map((block) => block.id) })
    if (selectFirst && data.blocks[0]) select(data.blocks[0].id)

    return data.blocks
  }

  async function remove(id, { recordUndo = true } = {}) {
    clearTimeout(drafts.get(id)?.timer)
    drafts.delete(id)

    await enqueue(id, async () => {
      await request(http.delete(`/api/blocks/${id}`, { data: { version: get(id).version } }))
    })

    const parentId = parentIdOf(id)
    unplace(id)
    reindex()
    if (selectedId.value && !index.has(selectedId.value)) select(parentId)
    if (recordUndo) record({ kind: 'delete', id })
  }

  async function restore(id) {
    const { data } = await request(http.post(`/api/blocks/${id}/restore`))
    place(data.block.parentId, data.block)
    reindex()
    select(id)
  }

  /** Moves a block: where = { parentId, afterId? | beforeId? }. */
  async function move(id, where, { recordUndo = true } = {}) {
    const from = locationOf(id)
    await flush(id)

    await enqueue(id, async () => {
      const node = get(id)
      const { data } = await request(
        http.patch(`/api/blocks/${id}/move`, {
          version: node.version,
          parentId: where.parentId ?? null,
          ...neighbour(where),
        })
      )
      relocate(node, data.block)
    })

    if (recordUndo) record({ kind: 'move', id, from, to: where })
    reveal(id)
  }

  // ── document settings ──────────────────────────────────────────────────────

  /** changes: { title?, meta?, titlePage? } (meta/titlePage replace the whole object). */
  function editDocument(changes) {
    Object.assign(document.value, changes)
    documentDirty.value = true
    if (heldLock !== 'settings') claim('settings')
    clearTimeout(documentTimer)
    documentTimer = setTimeout(flushDocument, SAVE_DELAY)
  }

  async function flushDocument() {
    if (!documentDirty.value) return
    clearTimeout(documentTimer)
    documentDirty.value = false

    const { slug, version, title, meta, titlePage } = document.value
    try {
      const { data } = await request(http.patch(`/api/documents/${slug}`, { version, title, meta, titlePage }))
      document.value.version = data.document.version
    } catch (error) {
      const { status } = error.response ?? {}
      if (status !== 409 && status !== 423) throw error
      Object.assign(document.value, pickSettings(error.response.data.current))
      if (status === 409) {
        notice.value = {
          type: 'warning',
          text: 'The document settings were changed by someone else; theirs are shown.',
        }
      }
    }
  }

  // ── undo / redo ────────────────────────────────────────────────────────────

  function record(entry) {
    undoStack.value = [...undoStack.value, entry].slice(-UNDO_LIMIT)
    redoStack.value = []
  }

  async function undo() {
    await flushAll()
    const entry = undoStack.value.at(-1)
    if (!entry) return

    undoStack.value = undoStack.value.slice(0, -1)
    try {
      await replay(entry, 'undo')
      redoStack.value = [...redoStack.value, entry]
    } catch (error) {
      notice.value = { type: 'error', text: `Could not undo: ${error.response?.data?.message ?? error.message}` }
    }
  }

  async function redo() {
    await flushAll()
    const entry = redoStack.value.at(-1)
    if (!entry) return

    redoStack.value = redoStack.value.slice(0, -1)
    try {
      await replay(entry, 'redo')
      undoStack.value = [...undoStack.value, entry]
    } catch (error) {
      notice.value = { type: 'error', text: `Could not redo: ${error.response?.data?.message ?? error.message}` }
    }
  }

  async function replay(entry, direction) {
    const undoing = direction === 'undo'

    if (entry.kind === 'update') {
      const node = get(entry.id)
      if (!node) throw new Error('that block no longer exists.')
      // don't silently overwrite someone else's later edit
      if (entry.version !== null && node.version !== entry.version) {
        throw new Error('someone else has changed that block since.')
      }

      const values = undoing ? entry.before : entry.after
      replace(node, values)
      select(entry.id)
      entry.version = (await enqueue(entry.id, () => saveBlock(entry.id, values))) ?? node.version
    } else if (entry.kind === 'insert') {
      for (const id of undoing ? [...entry.ids].reverse() : entry.ids) {
        undoing ? await remove(id, { recordUndo: false }) : await restore(id)
      }
    } else if (entry.kind === 'delete') {
      undoing ? await restore(entry.id) : await remove(entry.id, { recordUndo: false })
    } else if (entry.kind === 'move') {
      await move(entry.id, undoing ? entry.from : entry.to, { recordUndo: false })
    } else if (entry.kind === 'replace') {
      // each swap makes new blocks, so the entry follows whichever ones are in the document now
      entry.ids = await replaceBlocks(entry.ids, undoing ? entry.before : entry.after)
    }
  }

  // ── changes the server made for this tab (accepted AI suggestions) ─────────

  /**
   * Applies a change made on this tab's behalf and makes it undoable. The tab skips its own live events,
   * so the change comes in the response instead: a block.update or block.replace revision event.
   */
  function applyChange(event) {
    if (event.op === 'block.update') {
      applyEvent(event)
      record({
        kind: 'update',
        id: event.blockId,
        before: snapshot(event.before),
        after: snapshot(event.after),
        version: event.after.version,
      })
    } else if (event.op === 'block.replace') {
      showReplacement(event.after)
      record({
        kind: 'replace',
        ids: event.after.blocks.map((block) => block.id),
        before: event.before.blocks.map(toTemplate),
        after: event.after.blocks.map(toTemplate),
      })
    }
  }

  /** Swaps neighbouring blocks for new nodes in one change; returns the new top-level ids. */
  async function replaceBlocks(ids, nodes) {
    await flushAll()
    const blocks = ids.map((id) => {
      const node = get(id)
      if (!node) throw new Error('those blocks no longer exist.')

      return { id, version: node.version }
    })
    const { data } = await request(http.post(`/api/documents/${document.value.slug}/blocks/replace`, { blocks, nodes }))
    showReplacement(data)

    return data.blocks.map((block) => block.id)
  }

  function showReplacement(after) {
    swapIn(after)
    reindex()
    select(after.blocks[0]?.id ?? null)
  }

  /** { removed: ids, blocks: new subtrees } from a block.replace revision. */
  function swapIn({ removed, blocks }) {
    removed.filter((id) => get(id)).forEach(unplace)
    blocks.filter((block) => !get(block.id)).forEach((block) => place(block.parentId, block))
  }

  // ── locks ──────────────────────────────────────────────────────────────────

  function lockOf(target) {
    const lock = locks.value.get(target)

    return lock && lock.expiresAt > clock.value ? lock : null
  }

  /** The lock on target if another tab holds it (null when it's free or ours). */
  function lockedByOther(target) {
    const lock = lockOf(target)

    return lock && lock.clientId !== clientId ? lock : null
  }

  /** First lock held by another tab on the block or anything inside it (moving/deleting needs all free). */
  function subtreeLockedByOther(id) {
    const node = get(id)
    if (!node) return null

    return (
      lockedByOther(`block:${id}`) ??
      node.children.reduce((lock, child) => lock ?? subtreeLockedByOther(child.id), null)
    )
  }

  function setLock(holder) {
    const next = new Map(locks.value)
    next.set(holder.target, { ...holder, expiresAt: Date.now() + holder.ttl * 1000 })
    locks.value = next
  }

  function dropLock(target, holderClientId) {
    if (locks.value.get(target)?.clientId !== holderClientId) return
    const next = new Map(locks.value)
    next.delete(target)
    locks.value = next
  }

  /** Makes target (or nothing) the one lock this tab wants; releases the previous one after saving it. */
  function claim(target) {
    if (wantedLock === target) return
    wantedLock = target

    if (heldLock && heldLock !== target) {
      const previous = heldLock
      heldLock = null
      release(previous)
    }
    if (target && !lockedByOther(target)) acquire(target)
  }

  async function acquire(target) {
    if (!document.value) return
    try {
      const { data } = await http.post(`/api/documents/${document.value.slug}/locks`, { target })
      if (wantedLock === target) {
        heldLock = target
        setLock(data.lock)
      } else {
        // the selection moved on while the request was out
        release(target)
      }
    } catch (error) {
      if (error.response?.status === 423) setLock(error.response.data.holder)
      // otherwise carry on without the lock: the server still refuses conflicting edits
    }
  }

  async function release(target) {
    // finish saving under the lock before letting go of it
    if (target.startsWith('block:')) await flush(target.slice(6)).catch(reported)
    if (target === 'settings') await flushDocument().catch(reported)

    dropLock(target, clientId)
    if (!document.value) return
    try {
      await http.delete(`/api/documents/${document.value.slug}/locks`, { data: { target } })
    } catch {
      // it expires on its own
    }
  }

  function onLockEvent(event) {
    if (event.clientId === clientId) return

    if (event.action === 'locked') {
      setLock(event)
    } else {
      dropLock(event.target, event.clientId)
      // the block we're looking at just came free
      if (wantedLock === event.target && heldLock !== event.target) acquire(event.target)
    }
  }

  // ── staying in sync ────────────────────────────────────────────────────────

  function connect() {
    leaveChannel = joinDocument(document.value.id, {
      here: (users) => (members.value = users),
      joining: (user) => (members.value = [...members.value.filter((member) => member.id !== user.id), user]),
      leaving: (user) => {
        members.value = members.value.filter((member) => member.id !== user.id)
        // everyone else's copy of their locks lapses with them
        locks.value = new Map([...locks.value].filter(([, lock]) => lock.userId !== user.id))
      },
      changed: (event) => {
        if (event.clientId !== clientId && event.seq > lastSeq) sync()
      },
      locks: onLockEvent,
      reconnected: () => sync(),
    })

    timers = [
      setInterval(() => (clock.value = Date.now()), CLOCK_TICK_MS),
      setInterval(() => {
        if (heldLock) acquire(heldLock)
        else if (wantedLock && !lockedByOther(wantedLock)) acquire(wantedLock)
      }, LOCK_RENEW_MS),
    ]
  }

  function disconnect() {
    leaveChannel?.()
    leaveChannel = null
    timers.forEach(clearInterval)
    timers = []
  }

  /**
   * Gives up this tab's locks and leaves the channel. Uses a keepalive request so it also works while
   * the page is closing.
   */
  function dispose() {
    const slug = document.value?.slug
    disconnect()
    wantedLock = null
    heldLock = null
    if (!slug) return

    fetch(`/api/documents/${encodeURIComponent(slug)}/locks/mine`, {
      method: 'DELETE',
      keepalive: true,
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Client-Id': clientId, 'X-XSRF-TOKEN': xsrfToken() },
    }).catch(reported)
  }

  /** Fetches and applies everyone else's changes since the last one applied. */
  function sync() {
    if (syncing) {
      syncAgain = true

      return syncing
    }

    syncing = (async () => {
      do {
        syncAgain = false
        const { data } = await http.get(`/api/documents/${document.value.slug}/events`, { params: { since: lastSeq } })
        if (data.reset) {
          await reload()
        } else {
          for (const event of data.events) {
            if (event.clientId !== clientId) applyEvent(event)
            lastSeq = event.seq
          }
        }
      } while (syncAgain)
    })()
      .catch(reported)
      .finally(() => (syncing = null))

    return syncing
  }

  /** Too far behind to replay: take the whole tree again, keeping the selection where possible. */
  async function reload() {
    const { data } = await http.get(`/api/documents/${document.value.slug}`)
    Object.assign(document.value, pickSettings(data.document))
    roots.value = data.blocks
    lastSeq = data.document.seq
    reindex()
    undoStack.value = []
    redoStack.value = []
    if (selectedId.value && !index.has(selectedId.value)) select(null)
  }

  function applyEvent(event) {
    const { op, blockId, after } = event

    if (op === 'block.update') {
      const node = get(blockId)
      // never overwrite what this tab is still typing
      if (node && !drafts.has(blockId)) {
        replace(node, after)
        node.version = after.version
      }
    } else if (op === 'block.create' || op === 'block.restore') {
      if (!get(after.id)) place(after.parentId, { children: [], ...after })
    } else if (op === 'block.insert') {
      after.blocks.filter((block) => !get(block.id)).forEach((block) => place(block.parentId, block))
    } else if (op === 'block.move') {
      const node = get(blockId)
      if (node) relocate(node, after)
    } else if (op === 'block.delete') {
      if (get(blockId)) unplace(blockId)
    } else if (op === 'block.replace') {
      swapIn(after)
    } else if (op === 'document.update') {
      if (!documentDirty.value) Object.assign(document.value, pickSettings(after))
    }

    reindex()
    if (selectedId.value && !index.has(selectedId.value)) {
      notice.value = { type: 'warning', text: 'The block you had selected was deleted by someone else.' }
      select(null)
    }
  }

  // ── helpers ────────────────────────────────────────────────────────────────

  function enqueue(id, task) {
    const run = (queues.get(id) ?? Promise.resolve()).then(task)
    const settled = run.catch(() => {})
    queues.set(id, settled)
    settled.then(() => queues.get(id) === settled && queues.delete(id))

    return run
  }

  /** Counts requests for the save indicator and turns failures into a notice. */
  async function request(promise) {
    inFlight.value++
    try {
      const response = await promise
      failed.value = false

      return response
    } catch (error) {
      const { status, data } = error.response ?? {}
      if (status === 423) {
        // refused because someone holds the block: not a failure of ours
        notice.value = { type: 'warning', text: data.message }
      } else if (status !== 409) {
        failed.value = true
        notice.value = { type: 'error', text: data?.message ?? error.message }
      }
      throw error
    } finally {
      inFlight.value--
    }
  }

  /** Inserts a node among its new siblings, ordered by position (byte order, as on the server). */
  function place(parentId, node) {
    node.parentId = parentId
    const siblings = childrenOf(parentId)
    const at = siblings.findIndex((sibling) => sibling.position > node.position)
    siblings.splice(at === -1 ? siblings.length : at, 0, node)
  }

  function unplace(id) {
    const siblings = childrenOf(parentIdOf(id))
    siblings.splice(
      siblings.findIndex((node) => node.id === id),
      1
    )
  }

  /** Moves an existing node to where the server says it now is. */
  function relocate(node, saved) {
    unplace(node.id)
    Object.assign(node, { version: saved.version, position: saved.position, parentId: saved.parentId })
    place(saved.parentId, node)
    reindex()
  }

  return {
    document,
    roots,
    selectedId,
    selected,
    collapsed,
    undoStack,
    redoStack,
    status,
    notice,
    members,
    othersLocks,
    load,
    reset,
    dispose,
    get,
    parentIdOf,
    childrenOf,
    ancestorsOf,
    rootOf,
    isInside,
    locationOf,
    select,
    reveal,
    toggle,
    edit,
    setBlock,
    flush,
    flushAll,
    insert,
    remove,
    restore,
    move,
    editDocument,
    flushDocument,
    undo,
    redo,
    applyChange,
    lockOf,
    lockedByOther,
    subtreeLockedByOther,
    sync,
  }
})

function snapshot(node) {
  return clone({ props: node.props ?? {}, options: node.options ?? {} })
}

/** Deep copy that also accepts Vue proxies (structuredClone does not). */
function clone(value) {
  return JSON.parse(JSON.stringify(value))
}

/** Partial edit from a form field: merges into props/options. */
function apply(node, values) {
  if (values.props) node.props = { ...node.props, ...values.props }
  if (values.options) {
    // undefined/'' clears an option instead of storing it
    node.options = Object.fromEntries(
      Object.entries({ ...node.options, ...values.options }).filter(([, value]) => value !== undefined && value !== '')
    )
  }
}

/** Whole props/options from a snapshot (undo) or the server (conflict, someone else's change). */
function replace(node, values) {
  node.props = clone(values.props ?? {})
  node.options = clone(values.options ?? {})
}

function pickSettings({ title, meta, titlePage, version }) {
  return { title, meta, titlePage, version }
}

/** The API takes either afterId or beforeId. */
function neighbour(where) {
  if (where.afterId) return { afterId: where.afterId }
  if (where.beforeId) return { beforeId: where.beforeId }

  return {}
}

function xsrfToken() {
  const cookie = globalThis.document?.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='))

  return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : ''
}
