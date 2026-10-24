import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { useEditorStore } from '@/stores/editor.js'

import BlockCanvas from './BlockCanvas.vue'
import OutlineNode from './OutlineNode.vue'

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() }))
vi.mock('@/builder/api.js', () => ({ http, clientId: 'my-tab', errorMessage: (error) => error.message }))
vi.mock('@/builder/realtime.js', () => ({ joinDocument: () => () => {}, listenToReviews: () => () => {} }))

const node = (id, type, props, children = []) => ({ id, type, props, options: {}, version: 1, position: id, children })

/** Pointing at a block (hovering an AI suggestion) marks it in the canvas and the outline. */
describe('pointing at blocks', () => {
  let editor

  beforeEach(async () => {
    setActivePinia(createPinia())
    Element.prototype.scrollIntoView = vi.fn()
    http.get.mockResolvedValue({
      data: {
        document: { id: 7, slug: 'doc', seq: 1, version: 1, meta: {}, titlePage: {} },
        blocks: [
          node('t1', 'PrimaryTopic', { title: 'SETUP' }, [
            node('p1', 'Paragraph', {}, [node('r1', 'RichText', { html: 'In order to install it, you need PHP.' })]),
          ]),
        ],
        locks: [],
      },
    })
    editor = useEditorStore()
    await editor.load('doc')
  })

  it('outlines the pointed blocks on the canvas and scrolls them into view', async () => {
    const wrapper = mount(BlockCanvas, { attachTo: document.body })
    const css = () => [...document.head.querySelectorAll('style')].map((style) => style.textContent).join('\n')

    editor.point(['p1'], 'In order to install it')
    await flushPromises()

    expect(css()).toContain('[data-block-id="p1"]:not(.contents)')
    expect(css()).toContain('#f59e0b')
    expect(Element.prototype.scrollIntoView).toHaveBeenCalledWith({ block: 'nearest', behavior: 'smooth' })

    editor.point(null)
    await flushPromises()
    expect(css()).not.toContain('#f59e0b')
    wrapper.unmount()
  })

  it('marks the row in the outline, or the folded row hiding it', async () => {
    const outline = () =>
      mount(OutlineNode, { props: { node: editor.roots[0] }, global: { provide: { outlineDrag: { id: null } } } })
    const marked = (wrapper, id) => wrapper.find(`[data-outline-id="${id}"]`).classes().includes('bg-amber-100')

    // everything below the top level is folded on load: the paragraph's row stands in for the text inside it
    let wrapper = outline()
    editor.point(['r1'])
    await flushPromises()
    expect(marked(wrapper, 't1')).toBe(false)
    expect(marked(wrapper, 'p1')).toBe(true)
    wrapper.unmount()

    editor.toggle('p1')
    wrapper = outline()
    await flushPromises()
    expect(marked(wrapper, 'p1')).toBe(false)
    expect(marked(wrapper, 'r1')).toBe(true)
  })
})
