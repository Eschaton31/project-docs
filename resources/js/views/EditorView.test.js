import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import EditorView from './EditorView.vue'

const http = vi.hoisted(() => ({
  get: vi.fn(async () => ({
    data: {
      document: {
        slug: 'doc',
        title: 'Server Migration',
        version: 1,
        meta: { fileName: 'doc_{yyyyMMdd}', runningTitle: { bold: 'OHASIS', light: 'PLAN' }, toc: true },
        titlePage: { title: 'OHASIS Servers' },
      },
      // Ana is editing the topic
      locks: [{ target: 'block:topic', userId: 2, name: 'Ana Santos', color: '#c2410c', clientId: 'ana-tab', ttl: 60 }],
      blocks: [
        {
          id: 'topic',
          parentId: null,
          position: 'V',
          type: 'PrimaryTopic',
          version: 1,
          props: { title: 'OVERVIEW' },
          options: {},
          children: [
            {
              id: 'para',
              parentId: 'topic',
              position: 'V',
              type: 'Paragraph',
              version: 1,
              props: {},
              options: {},
              children: [
                {
                  id: 'text',
                  parentId: 'para',
                  position: 'V',
                  type: 'RichText',
                  version: 4,
                  props: { html: 'The servers were <u>installed</u>.' },
                  options: {},
                  children: [],
                },
              ],
            },
          ],
        },
      ],
    },
  })),
  patch: vi.fn(async (url, body) => ({ data: { seq: 2, block: { ...body, version: body.version + 1 } } })),
  post: vi.fn(async (url, body) => ({
    data: { lock: { target: body.target, userId: 1, name: 'Me', color: '#0b6374', clientId: 'my-tab', ttl: 60 } },
  })),
  delete: vi.fn(async () => ({ data: {} })),
}))

vi.mock('@/builder/api.js', () => ({ http, clientId: 'my-tab', errorMessage: (error) => error.message }))
// presence: Ana is here too
vi.mock('@/builder/realtime.js', () => ({
  joinDocument: (documentId, handlers) => {
    handlers.here([
      { id: 1, name: 'Me', color: '#0b6374' },
      { id: 2, name: 'Ana Santos', color: '#c2410c' },
    ])

    return () => {}
  },
}))

async function mountEditor() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/edit/:slug', component: EditorView },
    ],
  })
  await router.push('/edit/doc')
  const wrapper = mount(EditorView, { global: { plugins: [createPinia(), router] }, attachTo: document.body })
  await flushPromises()

  return wrapper
}

describe('EditorView', () => {
  it('shows the outline and the selected topic on the page', async () => {
    const wrapper = await mountEditor()

    expect(wrapper.find('nav').text()).toContain('OVERVIEW')
    expect(wrapper.find('nav').text()).toContain('Paragraph')
    expect(wrapper.find('.block-canvas').text()).toContain('The servers were installed.')
    expect(wrapper.find('aside').text()).toContain('Document')
  })

  it('selects a block from the page and edits it live', async () => {
    const wrapper = await mountEditor()

    await wrapper.find('.block-canvas u').trigger('click')
    expect(wrapper.find('aside h2').text()).toBe('Text')

    // the formatter shows the text; switch to its HTML view to type markup directly
    expect(wrapper.find('aside .ProseMirror').html()).toContain('<u>installed</u>')
    await wrapper
      .findAll('aside button')
      .find((button) => button.text() === 'Edit HTML')
      .trigger('click')
    await wrapper.find('aside textarea').setValue('The servers were <b>moved</b>.')
    expect(wrapper.find('.block-canvas b').text()).toBe('moved')
    expect(wrapper.text()).toContain('Saving…')

    await vi.waitFor(() => expect(http.patch).toHaveBeenCalled(), { timeout: 2000 })
    expect(http.patch).toHaveBeenCalledWith('/api/blocks/text', {
      version: 4,
      props: { html: 'The servers were <b>moved</b>.' },
      options: {},
    })
    await vi.waitFor(() => expect(wrapper.text()).toContain('All changes saved'))
    expect(wrapper.find('button[title^="Undo"]').attributes('disabled')).toBeUndefined()
  })

  it('opens document settings when nothing is selected', async () => {
    const wrapper = await mountEditor()

    await wrapper.find('nav [data-outline-id="topic"]').trigger('click')
    await wrapper.find('aside button.underline').trigger('click')

    expect(wrapper.find('aside').text()).toContain('Title page')
    expect(wrapper.find('aside').text()).toContain('doc_2')
  })

  it('shows who is here and what they are editing', async () => {
    const wrapper = await mountEditor()

    expect(wrapper.find('header').text()).toContain('AS')
    expect(wrapper.find('nav [data-outline-id="topic"]').text()).toContain('AS')

    await wrapper.find('nav [data-outline-id="topic"]').trigger('click')
    expect(wrapper.find('aside').text()).toContain('Ana Santos is editing this block')
    expect(wrapper.find('aside fieldset').attributes('disabled')).toBeDefined()
    expect(wrapper.find('aside button.text-rose-700').attributes('disabled')).toBeDefined()

    // only the topic itself is held; the paragraph inside it is free to edit
    await wrapper.find('nav [data-outline-id="para"]').trigger('click')
    expect(wrapper.find('aside fieldset').attributes('disabled')).toBeUndefined()
  })
})

describe('editing in place on the page', () => {
  it('turns a selected text block into an editor that saves like the panel', async () => {
    const wrapper = await mountEditor()
    const canvas = () => wrapper.find('.block-canvas')

    await canvas().find('u').trigger('click')
    expect(canvas().find('[data-inline-editor]').exists()).toBe(false)

    // a second click on the selected block starts editing it where it is
    await canvas().find('u').trigger('click')
    await flushPromises()
    const editable = canvas().find('[data-inline-editor] .ProseMirror')
    expect(editable.exists()).toBe(true)
    expect(editable.html()).toContain('<u>installed</u>')
    expect(canvas().text()).toContain('Done')

    // typing (through the editor API, as happy-dom can't type into contenteditable) saves through the store
    editable.element.editor.commands.insertContent(' Yesterday.')
    await flushPromises()
    expect(wrapper.find('aside .ProseMirror').html()).toContain('Yesterday.')
    await vi.waitFor(() =>
      expect(http.patch).toHaveBeenCalledWith(
        '/api/blocks/text',
        expect.objectContaining({ props: { html: expect.stringContaining('Yesterday.') } })
      )
    )

    await editable.trigger('keydown', { key: 'Escape' })
    await flushPromises()
    expect(canvas().find('[data-inline-editor]').exists()).toBe(false)
    expect(canvas().text()).toContain('Yesterday.')
  })

  it("won't edit a block someone else holds", async () => {
    const wrapper = await mountEditor()
    const title = () => wrapper.find('.block-canvas .primary-topic')

    // Ana is editing the topic
    await title().trigger('click')
    await title().trigger('dblclick')
    await flushPromises()

    expect(wrapper.find('.block-canvas [data-inline-editor]').exists()).toBe(false)
  })
})
