import { Editor } from '@tiptap/core'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import FormattedTextField from './FormattedTextField.vue'
import GridField from './GridField.vue'
import RichTextField from './RichTextField.vue'
import { fitsRichText, richTextExtensions } from './richText.js'
import { useExport } from './useExport.js'

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('@/builder/api.js', () => ({ http, errorMessage: (error) => error.message }))

const lastEmitted = (wrapper) => wrapper.emitted('update:modelValue')?.at(-1)?.[0]

describe('RichTextField', () => {
  it('keeps the tags the hand-built pages use, without adding paragraphs', async () => {
    const html =
      'Domain <codetext>ohasis.doh.gov.ph</codetext> replaces <b>ebreports</b>, see <a class="text-link" href="https://doh.gov.ph">DOH</a>.'
    const wrapper = mount(RichTextField, { props: { modelValue: html }, attachTo: document.body })
    // the editor is created on mount
    await flushPromises()

    const editor = wrapper.find('.ProseMirror')
    expect(editor.html()).toContain('<codetext>ohasis.doh.gov.ph</codetext>')
    expect(editor.html()).toContain('<b>ebreports</b>')
    expect(editor.find('p').exists()).toBe(false)

    wrapper.unmount()
  })

  it('saves the same HTML it was given', () => {
    const html =
      'Domain <codetext>ohasis.doh.gov.ph</codetext> replaces <b>ebreports</b>,<br><i>see</i> <u>also</u> <a class="text-link" href="https://doh.gov.ph">DOH</a>.'
    const editor = new Editor({ extensions: richTextExtensions(), content: html })

    expect(editor.getHTML()).toBe(html)
    editor.destroy()
  })

  it('opens layout HTML in the HTML view so nothing is dropped', async () => {
    const html = 'The servers are named: <div class="w-full text-center"><img src="/img/x.png" class="h-48"></div>'
    const wrapper = mount(RichTextField, { props: { modelValue: html } })

    expect(wrapper.find('.ProseMirror').exists()).toBe(false)
    expect(wrapper.find('textarea').element.value).toBe(html)
    expect(wrapper.text()).toContain("layout HTML the formatter can't keep")
  })

  it.each([
    ['plain text', true],
    ['<b>bold</b>, <i>it</i>, <u>u</u>, <codetext>x</codetext><br>', true],
    ['<a href="#x" class="text-link">link</a>', true],
    ['<span>wrapper from v-html</span>', true],
    ['<span class="font-bold">styled</span>', false],
    ['<b class="text-rose-700">red</b>', false],
    ['<div>block</div>', false],
  ])('fitsRichText(%s) is %s', (html, expected) => {
    expect(fitsRichText(html)).toBe(expected)
  })
})

describe('GridField', () => {
  const columns = [
    { key: 'item', label: 'Item', kind: 'text' },
    { key: 'description', label: 'Description', kind: 'lines' },
  ]

  it('edits cells, turning multi-line cells into arrays', async () => {
    const wrapper = mount(GridField, { props: { columns, modelValue: [{ item: 'Dates', description: 'one' }] } })

    await wrapper.findAll('tbody textarea')[1].setValue('first line\nsecond line')

    expect(lastEmitted(wrapper)).toEqual([{ item: 'Dates', description: ['first line', 'second line'] }])
  })

  it('pastes spreadsheet rows, skipping a header row that matches the columns', async () => {
    const wrapper = mount(GridField, { props: { columns, modelValue: [{ item: 'old', description: 'row' }] } })

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Paste rows…')
      .trigger('click')
    await wrapper
      .find('div.bg-slate-50 textarea')
      .setValue('Item\tDescription\nSeptember 7\tInstallation\nSeptember 16\tServers off\n')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Add to rows')
      .trigger('click')

    expect(lastEmitted(wrapper)).toEqual([
      { item: 'old', description: 'row' },
      { item: 'September 7', description: 'Installation' },
      { item: 'September 16', description: 'Servers off' },
    ])
  })

  it('takes SQL results with their own columns, empty cells as NULL', async () => {
    const wrapper = mount(GridField, { props: { modelValue: [] } })

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Paste rows…')
      .trigger('click')
    await wrapper.find('div.bg-slate-50 textarea').setValue('faci_id\tfaci_name\n040001\tSan Lazaro\n040002\t')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Replace rows')
      .trigger('click')

    expect(lastEmitted(wrapper)).toEqual([
      { faci_id: '040001', faci_name: 'San Lazaro' },
      { faci_id: '040002', faci_name: null },
    ])
  })
})

describe('FormattedTextField', () => {
  it('emits parsed concept lists', async () => {
    const wrapper = mount(FormattedTextField, { props: { format: 'concepts', modelValue: [] } })

    await wrapper.find('textarea').setValue('Primary: Data Management Team\n  Lead: Ana')

    expect(lastEmitted(wrapper)).toEqual([
      { id: 'Primary', value: 'Data Management Team', children: [{ id: 'Lead', value: 'Ana' }] },
    ])
  })
})

describe('useExport', () => {
  let clicked

  beforeEach(() => {
    vi.useFakeTimers()
    http.get.mockReset()
    http.post.mockReset()
    clicked = []
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function () {
      clicked.push(this.getAttribute('href'))
    })
  })

  it('waits for the queued render and then downloads the file', async () => {
    http.post.mockResolvedValue({ data: { export: { id: 5, status: 'queued' } } })
    http.get
      .mockResolvedValueOnce({ data: { export: { id: 5, status: 'running' } } })
      .mockResolvedValueOnce({ data: { export: { id: 5, status: 'done', downloadUrl: '/api/exports/5/download' } } })

    const pdf = useExport()
    const done = pdf.downloadPdf('migration-plan', 'final')
    expect(pdf.busy.value).toBe(true)
    await vi.advanceTimersByTimeAsync(3500)
    await done

    expect(http.post).toHaveBeenCalledWith('/api/documents/migration-plan/exports', { mode: 'final' })
    expect(clicked).toEqual(['/api/exports/5/download'])
    expect(pdf.busy.value).toBe(false)
    expect(pdf.error.value).toBe(null)
  })

  it('reports a failed render', async () => {
    http.post.mockResolvedValue({ data: { export: { id: 6, status: 'failed', error: 'Chrome crashed' } } })

    const pdf = useExport()
    await pdf.downloadPdf('doc')
    await flushPromises()

    expect(pdf.error.value).toBe('Chrome crashed')
    expect(clicked).toEqual([])
  })

  it('says when the queue worker seems to be missing', async () => {
    http.post.mockResolvedValue({ data: { export: { id: 7, status: 'queued' } } })
    http.get.mockResolvedValue({ data: { export: { id: 7, status: 'queued' } } })

    const pdf = useExport()
    pdf.downloadPdf('doc')
    await vi.advanceTimersByTimeAsync(25_000)

    expect(pdf.slow.value).toBe(true)
    expect(pdf.busy.value).toBe(true)
  })
})
