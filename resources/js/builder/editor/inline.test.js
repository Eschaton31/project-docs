import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import ImagePicker from './ImagePicker.vue'
import InlineText from './InlineText.vue'
import { inlineEditCheck } from './inline.js'

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('@/builder/api.js', () => ({ http, errorMessage: (error) => error.message }))

const element = (html) => {
  const template = document.createElement('template')
  template.innerHTML = html

  return template.content.firstElementChild
}

describe('inlineEditCheck', () => {
  it('edits a topic only from its heading', () => {
    const topic = element('<ol><li class="secondary-topic">Installation</li><li><p>body</p></li></ol>')
    const node = { type: 'SecondaryTopic', props: { title: 'Installation' } }

    expect(inlineEditCheck(node, topic.querySelector('.secondary-topic')).ok).toBe(true)
    expect(inlineEditCheck(node, topic.querySelector('p')).ok).toBe(false)
  })

  it('sends layout HTML and markup titles to the panel instead', () => {
    const anywhere = element('<div></div>')

    expect(inlineEditCheck({ type: 'RichText', props: { html: 'plain <b>bold</b>' } }, anywhere).ok).toBe(true)
    expect(
      inlineEditCheck({ type: 'RichText', props: { html: '<div class="text-center"><img></div>' } }, anywhere)
    ).toMatchObject({
      ok: false,
      reason: expect.stringMatching(/panel/),
    })
    const alert = element('<span class="alert-title">x</span>')
    expect(inlineEditCheck({ type: 'Alert', props: { title: '<b>Note</b>' } }, alert)).toMatchObject({ ok: false })
    expect(inlineEditCheck({ type: 'CodeBlock', props: {} }, anywhere).ok).toBe(false)
  })
})

describe('InlineText', () => {
  it('reports typing and finishes on Enter or Escape', async () => {
    const wrapper = mount(InlineText, { props: { value: 'Installation' }, attachTo: document.body })
    const span = wrapper.find('[contenteditable]')

    expect(span.text()).toBe('Installation')
    span.element.textContent = 'Installation & set-up'
    await span.trigger('input')
    expect(wrapper.emitted('update').at(-1)).toEqual(['Installation & set-up'])

    await span.trigger('keydown', { key: 'Enter' })
    await span.trigger('keydown', { key: 'Escape' })
    expect(wrapper.emitted('done')).toHaveLength(2)
    wrapper.unmount()
  })
})

describe('ImagePicker uploads', () => {
  beforeEach(() => {
    http.get.mockReset().mockResolvedValue({
      data: {
        data: [{ path: '/img/diagrams/a.png', name: 'a.png', folder: 'diagrams', size: 1 }],
        folders: ['diagrams', 'screenshots', 'screenshots/ohasis-linelists'],
      },
    })
    http.post.mockReset().mockImplementation(async (url, form) => ({
      data: {
        image: {
          path: `/img/${form.get('folder') ?? 'uploads/2026-10'}/${form.get('file').name}`,
          name: form.get('file').name,
        },
      },
    }))
  })

  const choose = async (wrapper, files) => {
    const input = wrapper.find('input[type=file]')
    Object.defineProperty(input.element, 'files', { value: files, configurable: true })
    await input.trigger('change')
    await flushPromises()
  }
  const png = (name) => new File(['x'], name, { type: 'image/png' })

  it('uploads into the folder of the image being replaced and picks the upload', async () => {
    const wrapper = mount(ImagePicker, { props: { current: '/img/screenshots/ohasis-linelists/old.png' } })
    await flushPromises()

    expect(wrapper.find('input[type=text]').element.value).toBe('screenshots/ohasis-linelists')
    expect(wrapper.findAll('datalist option').map((option) => option.attributes('value'))).toContain('diagrams')

    await choose(wrapper, [png('confirmatory.png')])

    expect(http.post.mock.calls[0][1].get('folder')).toBe('screenshots/ohasis-linelists')
    expect(wrapper.emitted('select')).toEqual([['/img/screenshots/ohasis-linelists/confirmatory.png']])
  })

  it('uploads several files into a new folder and lists them instead of picking one', async () => {
    const wrapper = mount(ImagePicker)
    await flushPromises()

    await wrapper.find('input[type=text]').setValue('/diagrams/server-migration/')
    await choose(wrapper, [png('one.png'), png('two.png')])

    expect(http.post.mock.calls.map(([, form]) => form.get('folder'))).toEqual([
      'diagrams/server-migration',
      'diagrams/server-migration',
    ])
    expect(wrapper.emitted('select')).toBeUndefined()
    expect(http.get).toHaveBeenCalledTimes(2)
  })

  it('leaves the folder empty for the dated uploads folder, and can switch to a listed folder', async () => {
    const wrapper = mount(ImagePicker)
    await flushPromises()

    await choose(wrapper, [png('x.png')])
    expect(http.post.mock.calls[0][1].has('folder')).toBe(false)

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'upload here')
      .trigger('click')
    expect(wrapper.find('input[type=text]').element.value).toBe('diagrams')
  })
})
