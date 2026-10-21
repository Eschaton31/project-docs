import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { useEditorStore } from './editor.js'

/**
 * In-memory stand-in for the Laravel block API: versions, positions, soft deletes, restore, locks and the
 * event log. Positions are kept simple (append / after or before a sibling), which is all these tests use.
 * This tab is "my-tab"; other people's changes and locks are added straight to state.
 */
const server = vi.hoisted(() => {
  const state = {
    blocks: new Map(),
    deleted: new Set(),
    seq: 0,
    nextId: 1,
    calls: [],
    conflictOnce: null,
    events: [],
    locks: new Map(),
  }

  const node = (block) => ({ ...structuredClone(block), children: tree(block.id) })
  const tree = (parentId) =>
    [...state.blocks.values()]
      .filter((block) => block.parentId === parentId && !state.deleted.has(block.id))
      .sort((a, b) => (a.position < b.position ? -1 : 1))
      .map(node)
  const siblings = (parentId, except) =>
    [...state.blocks.values()].filter((b) => b.parentId === parentId && !state.deleted.has(b.id) && b.id !== except)
  const positionFor = (parentId, afterId, except, beforeId) => {
    if (afterId) return state.blocks.get(afterId).position + 'M'
    if (beforeId) {
      const before = state.blocks.get(beforeId).position
      const previous = siblings(parentId, except)
        .filter((b) => b.position < before)
        .sort((a, b) => (a.position < b.position ? -1 : 1))
        .at(-1)

      return previous ? previous.position + 'M' : '0' + before
    }
    const last = siblings(parentId, except)
      .sort((a, b) => (a.position < b.position ? -1 : 1))
      .at(-1)

    return last ? last.position + 'M' : 'V'
  }
  const create = (parentId, position, { type, props = {}, options = {}, children = [] }) => {
    const id = `b${state.nextId++}`
    state.blocks.set(id, { id, parentId, position, type, props, options, version: 1 })
    let previous = null
    children.forEach(
      (child) =>
        (previous =
          create(id, previous ? previous + 'M' : 'V', child) && state.blocks.get(`b${state.nextId - 1}`).position)
    )

    return id
  }
  const conflict = (block) => Promise.reject({ response: { status: 409, data: { current: structuredClone(block) } } })
  const lockedByOther = (target) => {
    const lock = state.locks.get(target)

    return lock && lock.clientId !== 'my-tab' ? lock : null
  }
  const refuse = (lock, current) =>
    Promise.reject({
      response: { status: 423, data: { message: `${lock.name} is editing this block.`, holder: lock, current } },
    })
  const stale = (block, version) => {
    if (state.conflictOnce === block.id) {
      state.conflictOnce = null
      block.version++
      block.props = { title: 'Changed elsewhere' }
    }

    return block.version !== version
  }

  const http = {
    get: vi.fn(async (url, config) => {
      if (url.endsWith('/events')) {
        return {
          data: { seq: state.seq, reset: false, events: state.events.filter((e) => e.seq > config.params.since) },
        }
      }

      return {
        data: {
          document: { id: 7, slug: 'doc', version: 1, seq: state.seq, title: 'Doc', meta: {}, titlePage: {} },
          blocks: tree(null),
          locks: [...state.locks.values()],
        },
      }
    }),
    post: vi.fn(async (url, body) => {
      state.calls.push(['POST', url, body])
      if (url.endsWith('/locks')) {
        const other = lockedByOther(body.target)
        if (other) return refuse(other)
        const lock = { target: body.target, userId: 1, name: 'Me', clientId: 'my-tab', ttl: 60 }
        state.locks.set(body.target, lock)

        return { data: { lock } }
      }
      if (url.endsWith('/blocks/tree')) {
        let afterId = body.afterId
        const ids = body.blocks.map(
          (nodeData) => (afterId = create(body.parentId ?? null, positionFor(body.parentId ?? null, afterId), nodeData))
        )

        return { data: { seq: ++state.seq, blocks: ids.map((id) => node(state.blocks.get(id))) } }
      }
      const id = url.split('/')[3]
      const block = state.blocks.get(id)
      const restore = (blockId) => {
        state.deleted.delete(blockId)
        ;[...state.blocks.values()].filter((b) => b.parentId === blockId).forEach((b) => restore(b.id))
      }
      restore(id)
      block.version++

      return { data: { seq: ++state.seq, block: node(block) } }
    }),
    patch: vi.fn(async (url, body) => {
      state.calls.push(['PATCH', url, body])
      const id = url.split('/')[3]
      const block = state.blocks.get(id)
      const other = lockedByOther(`block:${id}`)
      if (other) return refuse(other, structuredClone(block))
      if (stale(block, body.version)) return conflict(block)

      if (url.endsWith('/move')) {
        block.parentId = body.parentId
        block.position = positionFor(body.parentId, body.afterId, id, body.beforeId)
      } else {
        Object.assign(block, { props: body.props, options: body.options })
      }
      block.version++

      return { data: { seq: ++state.seq, block: { ...structuredClone(block) } } }
    }),
    delete: vi.fn(async (url, { data }) => {
      state.calls.push(['DELETE', url, data])
      if (url.endsWith('/locks')) {
        if (state.locks.get(data.target)?.clientId === 'my-tab') state.locks.delete(data.target)

        return { data: {} }
      }
      const id = url.split('/')[3]
      if (stale(state.blocks.get(id), data.version)) return conflict(state.blocks.get(id))
      const remove = (blockId) => {
        state.deleted.add(blockId)
        ;[...state.blocks.values()].filter((b) => b.parentId === blockId).forEach((b) => remove(b.id))
      }
      remove(id)

      return { data: { seq: ++state.seq } }
    }),
  }

  /** Someone else's change, as the events endpoint reports it. */
  const remote = (op, blockId, after, clientId = 'ana-tab') =>
    state.events.push({ seq: ++state.seq, op, blockId, userId: 2, clientId, before: null, after })

  return { state, http, create, tree, remote }
})

const channel = vi.hoisted(() => ({ handlers: null, leave: vi.fn() }))

vi.mock('@/builder/api.js', () => ({ http: server.http, clientId: 'my-tab' }))
vi.mock('@/builder/realtime.js', () => ({
  joinDocument: (documentId, handlers) => {
    channel.handlers = handlers

    return channel.leave
  },
}))

const titles = (nodes) => nodes.map((node) => node.props.title)

describe('editor store', () => {
  let store

  beforeEach(async () => {
    vi.useFakeTimers()
    Object.assign(server.state, {
      blocks: new Map(),
      deleted: new Set(),
      seq: 0,
      nextId: 1,
      calls: [],
      conflictOnce: null,
      events: [],
      locks: new Map(),
    })
    server.create(null, 'A', { type: 'PrimaryTopic', props: { title: 'OVERVIEW' } })
    server.create(null, 'B', {
      type: 'PrimaryTopic',
      props: { title: 'SET-UP' },
      children: [{ type: 'SecondaryTopic', props: { title: 'Infrastructure' } }],
    })

    setActivePinia(createPinia())
    store = useEditorStore()
    await store.load('doc')
  })

  it('debounces edits into one save and chains versions', async () => {
    store.edit('b1', { props: { title: 'OVER' } })
    store.edit('b1', { props: { title: 'OVERVIEW!' } })
    expect(store.status).toBe('saving')

    await vi.advanceTimersByTimeAsync(700)
    store.edit('b1', { props: { title: 'OVERVIEW!!' } })
    await store.flushAll()

    const patches = server.state.calls.filter(([method]) => method === 'PATCH')
    expect(patches.map(([, , body]) => [body.version, body.props.title])).toEqual([
      [1, 'OVERVIEW!'],
      [2, 'OVERVIEW!!'],
    ])
    expect(store.get('b1').version).toBe(3)
    expect(store.status).toBe('saved')
  })

  it('undoes and redoes a property edit', async () => {
    store.edit('b1', { props: { title: 'CHANGED' }, options: { draft: true } })
    await store.flushAll()

    await store.undo()
    expect(store.get('b1').props.title).toBe('OVERVIEW')
    expect(store.get('b1').options).toEqual({})
    expect(server.state.blocks.get('b1').props.title).toBe('OVERVIEW')

    await store.redo()
    expect(server.state.blocks.get('b1').props.title).toBe('CHANGED')
    expect(store.get('b1').options).toEqual({ draft: true })
  })

  it('inserts after a sibling, and undo deletes it again', async () => {
    await store.insert({ parentId: null, afterId: 'b1' }, [{ type: 'PrimaryTopic', props: { title: 'NEW' } }])
    expect(titles(store.roots)).toEqual(['OVERVIEW', 'NEW', 'SET-UP'])
    expect(store.selected.props.title).toBe('NEW')

    await store.undo()
    expect(titles(store.roots)).toEqual(['OVERVIEW', 'SET-UP'])

    await store.redo()
    expect(titles(store.roots)).toEqual(['OVERVIEW', 'NEW', 'SET-UP'])
  })

  it('deletes a subtree and undo restores it with its children', async () => {
    store.select('b3')
    await store.remove('b2')
    expect(titles(store.roots)).toEqual(['OVERVIEW'])
    expect(store.selectedId).toBe(null)

    await store.undo()
    expect(titles(store.roots)).toEqual(['OVERVIEW', 'SET-UP'])
    expect(titles(store.get('b2').children)).toEqual(['Infrastructure'])
  })

  it('moves a block to another parent and undo puts it back', async () => {
    await store.move('b1', { parentId: 'b2', afterId: 'b3' })
    expect(titles(store.roots)).toEqual(['SET-UP'])
    expect(titles(store.get('b2').children)).toEqual(['Infrastructure', 'OVERVIEW'])
    expect(store.parentIdOf('b1')).toBe('b2')

    await store.undo()
    expect(titles(store.roots)).toEqual(['OVERVIEW', 'SET-UP'])
    expect(store.parentIdOf('b1')).toBe(null)
  })

  it('shows the other version when a save conflicts', async () => {
    server.state.conflictOnce = 'b1'
    store.edit('b1', { props: { title: 'Mine' } })
    await store.flushAll()

    expect(store.get('b1').props.title).toBe('Changed elsewhere')
    expect(store.get('b1').version).toBe(2)
    expect(store.notice.type).toBe('warning')
  })

  it('folds everything below the top level on load and reveals a selection', () => {
    expect(store.collapsed.has('b2')).toBe(false)
    expect(store.collapsed.has('b3')).toBe(true)

    store.collapsed = new Set(['b2', 'b3'])
    store.select('b3')
    expect(store.collapsed.has('b2')).toBe(false)
    expect(store.rootOf('b3').id).toBe('b2')
  })

  describe('collaboration', () => {
    const lockCalls = () =>
      server.state.calls
        .filter(([, url]) => url.endsWith('/locks') || url.startsWith('/api/blocks/'))
        .map(([method, url, body]) => (url.endsWith('/locks') ? `${method} lock ${body.target}` : `${method} ${url}`))

    it('locks the selected block and releases it after its edits are saved', async () => {
      store.select('b1')
      await vi.waitFor(() => expect(server.state.locks.has('block:b1')).toBe(true))
      store.edit('b1', { props: { title: 'Edited' } })

      store.select('b3')
      await vi.waitFor(() => expect(server.state.locks.has('block:b3')).toBe(true))
      await vi.waitFor(() => expect(server.state.locks.has('block:b1')).toBe(false))

      expect(lockCalls()).toEqual([
        'POST lock block:b1',
        'POST lock block:b3',
        'PATCH /api/blocks/b1',
        'DELETE lock block:b1',
      ])
      expect(store.lockedByOther('block:b3')).toBe(null)
    })

    it('shows who holds a block and takes it over once they let go', async () => {
      server.state.locks.set('block:b1', { target: 'block:b1', userId: 2, name: 'Ana', clientId: 'ana-tab', ttl: 60 })

      store.select('b1')
      await vi.waitFor(() => expect(store.lockedByOther('block:b1')?.name).toBe('Ana'))
      expect(store.subtreeLockedByOther('b1')?.name).toBe('Ana')

      server.state.locks.delete('block:b1')
      channel.handlers.locks({ action: 'released', target: 'block:b1', userId: 2, clientId: 'ana-tab', ttl: 0 })

      await vi.waitFor(() => expect(server.state.locks.get('block:b1')?.clientId).toBe('my-tab'))
      expect(store.lockedByOther('block:b1')).toBe(null)
    })

    it('treats a lock as gone once it expires', async () => {
      channel.handlers.locks({
        action: 'locked',
        target: 'block:b3',
        userId: 2,
        name: 'Ana',
        clientId: 'ana-tab',
        ttl: 60,
      })
      expect(store.subtreeLockedByOther('b2')?.name).toBe('Ana')

      await vi.advanceTimersByTimeAsync(65_000)
      expect(store.subtreeLockedByOther('b2')).toBe(null)
    })

    it("applies other people's changes and skips its own", async () => {
      store.select('b3')
      server.remote('block.update', 'b1', { id: 'b1', props: { title: 'OVERVIEW (Ana)' }, options: {}, version: 2 })
      server.remote(
        'block.update',
        'b1',
        { id: 'b1', props: { title: 'my own echo' }, options: {}, version: 3 },
        'my-tab'
      )
      server.remote('block.insert', 'n1', {
        blocks: [
          {
            id: 'n1',
            parentId: null,
            position: 'W',
            type: 'PrimaryTopic',
            props: { title: 'NEW BY ANA' },
            options: {},
            version: 1,
            children: [],
          },
        ],
      })
      server.remote('block.delete', 'b3', null)

      channel.handlers.changed({ seq: server.state.seq, clientId: 'ana-tab' })
      await store.sync()

      expect(titles(store.roots)).toEqual(['OVERVIEW (Ana)', 'SET-UP', 'NEW BY ANA'])
      expect(store.get('b1').version).toBe(2)
      expect(store.get('b2').children).toEqual([])
      // the selected block was deleted under us
      expect(store.selectedId).toBe(null)
      expect(store.notice.text).toMatch(/deleted by someone else/)
    })

    it("refuses to undo over someone else's later edit", async () => {
      store.edit('b1', { props: { title: 'Mine' } })
      await store.flushAll()
      server.remote('block.update', 'b1', { id: 'b1', props: { title: 'Ana after me' }, options: {}, version: 3 })
      await store.sync()

      await store.undo()

      expect(store.get('b1').props.title).toBe('Ana after me')
      expect(store.notice.text).toMatch(/someone else has changed that block since/)
    })

    it('tracks who is here and forgets the locks of people who leave', () => {
      channel.handlers.here([
        { id: 1, name: 'Me' },
        { id: 2, name: 'Ana' },
      ])
      channel.handlers.locks({
        action: 'locked',
        target: 'block:b1',
        userId: 2,
        name: 'Ana',
        clientId: 'ana-tab',
        ttl: 60,
      })
      expect(store.members.map((member) => member.name)).toEqual(['Me', 'Ana'])

      channel.handlers.leaving({ id: 2, name: 'Ana' })

      expect(store.members.map((member) => member.name)).toEqual(['Me'])
      expect(store.lockedByOther('block:b1')).toBe(null)
    })

    it('gives up its locks and the channel when the editor closes', () => {
      const fetch = vi.spyOn(globalThis, 'fetch')

      store.dispose()

      expect(channel.leave).toHaveBeenCalled()
      expect(fetch).toHaveBeenCalledWith(
        '/api/documents/doc/locks/mine',
        expect.objectContaining({ method: 'DELETE', keepalive: true })
      )
    })
  })
})
