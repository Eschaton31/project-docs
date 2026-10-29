import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { useAuthStore } from '@/stores/auth.js'
import { useEditorStore } from '@/stores/editor.js'
import { useProseReviewStore } from '@/stores/proseReview.js'

import ProseReviewPanel from './ProseReviewPanel.vue'

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() }))
vi.mock('@/builder/api.js', () => ({
  http,
  api: {},
  csrf: () => {},
  clientId: 'my-tab',
  errorMessage: (error) => error.response?.data?.message ?? error.message,
}))
vi.mock('@/builder/realtime.js', () => ({ joinDocument: () => () => {}, listenToReviews: () => () => {} }))

const node = (id, type, props, children = []) => ({ id, type, props, options: {}, version: 1, position: id, children })

const sources = [
  { id: 21, ref: 'F1', name: 'main.R', entry: true, lines: 4, redactions: [{ line: 3, kind: 'assignment' }] },
  { id: 22, ref: 'F2', name: 'R/clean.R', entry: false, lines: 3, redactions: [] },
]

const section = (id, rule, order, title, extra = {}) => ({
  id,
  kind: 'scaffold',
  rule,
  order,
  severity: 'minor',
  rationale: `The document has no ${title}.`,
  blockIds: ['t1'],
  replacement: [{ type: 'PrimaryTopic', props: { title }, options: {}, children: [] }],
  sources: ['code'],
  citations: [{ file: 'F1', from: 1, to: 2 }],
  status: 'pending',
  ...extra,
})

const scaffoldReview = {
  id: 30,
  mode: 'scaffold',
  status: 'done',
  model: 'sonnet',
  sources,
  createdAt: new Date().toISOString(),
}

describe('Scaffold from code', () => {
  let reviews
  let latest

  beforeEach(async () => {
    vi.clearAllMocks()
    setActivePinia(createPinia())
    latest = null

    http.get.mockImplementation(async (url) => {
      if (url === '/api/documents/doc') {
        return {
          data: {
            document: { id: 7, slug: 'doc', seq: 1, version: 1, meta: {}, titlePage: {} },
            blocks: [
              node('t1', 'PrimaryTopic', { title: 'OVERVIEW' }, [
                node('p1', 'Paragraph', {}, [node('r1', 'RichText', { html: 'Builds the report.' })]),
              ]),
            ],
            locks: [],
          },
        }
      }
      if (url === '/api/documents/doc/reviews')
        return { data: { runner: 'api', canStart: true, reviews: latest ? [latest] : [] } }
      if (url === '/api/reviews/30') {
        return {
          data: {
            review: scaffoldReview,
            suggestions: [
              section(42, 'troubleshooting', 9, 'TROUBLESHOOTING', { sources: ['run_notes'], citations: [] }),
              section(41, 'dependencies', 5, 'DEPENDENCIES'),
              {
                ...section(43, 'overview', 0, ''),
                kind: 'expand',
                position: 'end',
                replacement: [
                  { type: 'Placeholder', props: { text: 'Who uses the report?' }, options: {}, children: [] },
                ],
                sources: ['general'],
                citations: [],
              },
            ],
          },
        }
      }
      if (url === '/api/reviews/30/sources/21')
        return { data: { text: 'library(dplyr)\nsource("R/clean.R")\ntoken <- "<redacted>"\nx <- 1\n' } }

      return { data: {} }
    })
    http.post.mockResolvedValue({ data: {} })

    useAuthStore().user = { id: 1, abilities: { aiReview: true, aiScaffold: true } }
    await useEditorStore().load('doc')
    reviews = useProseReviewStore()
  })

  async function mountPanel() {
    const wrapper = mount(ProseReviewPanel)
    await flushPromises()

    return wrapper
  }

  async function openScaffold(wrapper) {
    await wrapper
      .findAll('[role="tab"]')
      .find((tab) => tab.text() === 'Scaffold from code')
      .trigger('click')
  }

  async function pick(wrapper, files) {
    const input = wrapper.find('[data-scaffold-form] input[type="file"][multiple]:not([webkitdirectory])')
    Object.defineProperty(input.element, 'files', { value: files, configurable: true })
    await input.trigger('change')
  }

  const file = (name, path = null, content = 'x <- 1') => {
    const created = new File([content], name)
    if (path) Object.defineProperty(created, 'webkitRelativePath', { value: path })

    return created
  }

  it('is offered only to admins who may scaffold', async () => {
    useAuthStore().user = { id: 1, abilities: { aiReview: true, aiScaffold: false } }
    const wrapper = await mountPanel()

    expect(wrapper.find('[role="tab"]').exists()).toBe(false)
    expect(wrapper.find('[data-scaffold-form]').exists()).toBe(false)
  })

  it('takes .R files only, defaults the script opened first, and uploads them with the run notes', async () => {
    const wrapper = await mountPanel()
    await openScaffold(wrapper)
    const submit = wrapper.find('[data-scaffold-form] button[type="submit"]')
    expect(submit.attributes('disabled')).toBeDefined()

    await pick(wrapper, [file('clean.R', 'scripts/R/clean.R'), file('main.R', 'scripts/main.R'), file('notes.txt')])
    expect(wrapper.findAll('[data-files] li').map((item) => item.find('.font-mono').text())).toEqual([
      'scripts/R/clean.R',
      'scripts/main.R',
    ])
    expect(wrapper.text()).toContain('Skipped (not .R): notes.txt')
    expect(wrapper.find('[data-files] input[value="scripts/main.R"]').element.checked).toBe(true)

    await wrapper.find('textarea[aria-label="How it\'s run"]').setValue('Open main.R and run it.')
    http.post.mockResolvedValueOnce({ data: { review: { ...scaffoldReview, status: 'queued' }, command: null } })
    await wrapper.find('[data-scaffold-form]').trigger('submit')
    await flushPromises()

    const [url, form] = http.post.mock.calls.find(([called]) => called.endsWith('/scaffolds'))
    expect(url).toBe('/api/documents/doc/scaffolds')
    expect(form.getAll('files[]').map((each) => each.name)).toEqual(['clean.R', 'main.R'])
    expect(form.getAll('paths[]')).toEqual(['scripts/R/clean.R', 'scripts/main.R'])
    expect(form.get('entry')).toBe('scripts/main.R')
    expect(form.get('runNotes')).toBe('Open main.R and run it.')
    expect(form.get('model')).toBe('sonnet')
    expect(form.has('blockId')).toBe(false)
    // the list is cleared once uploaded, and the redactions are shown
    expect(wrapper.find('[data-files]').exists()).toBe(false)
    expect(wrapper.find('[data-redactions]').text()).toContain('main.R: line 3')
  })

  it('without the editor runner, uploads for Claude Code and shows the command to run', async () => {
    http.get.mockImplementation(
      ((list) => async (url) =>
        url === '/api/documents/doc/reviews'
          ? { data: { runner: 'claude-code', canStart: false, reviews: [] } }
          : list(url))(http.get.getMockImplementation())
    )
    const wrapper = await mountPanel()
    await openScaffold(wrapper)
    await pick(wrapper, [file('main.R')])

    const submit = wrapper.find('[data-scaffold-form] button[type="submit"]')
    expect(submit.text()).toBe('Upload for Claude Code')
    expect(wrapper.find('[data-scaffold-form] input[name="scaffold-model"]').exists()).toBe(false)

    http.post.mockResolvedValueOnce({
      data: { review: { ...scaffoldReview, id: 31, status: 'awaiting' }, command: '/doc-scaffold --review=31' },
    })
    http.get.mockImplementation(
      ((list) => async (url) =>
        url === '/api/reviews/31'
          ? { data: { review: { ...scaffoldReview, id: 31, status: 'awaiting' }, suggestions: [] } }
          : list(url))(http.get.getMockImplementation())
    )
    await wrapper.find('[data-scaffold-form]').trigger('submit')
    await flushPromises()

    expect(http.post.mock.calls.find(([url]) => url.endsWith('/scaffolds'))[1].has('model')).toBe(false)
    expect(wrapper.find('[data-scaffold-form] [data-claude-code] code').text()).toBe('/doc-scaffold --review=31')
    expect(wrapper.text()).toContain('Waiting for Claude Code')
    wrapper.unmount()
  })

  it('lists new sections first in template order, with their sources and citations', async () => {
    latest = scaffoldReview
    const wrapper = await mountPanel()

    // the panel opens on the form that matches the latest review
    expect(wrapper.find('[data-scaffold-form]').exists()).toBe(true)
    expect(wrapper.findAll('[data-suggestion]').map((article) => article.attributes('data-suggestion'))).toEqual([
      '41',
      '42',
      '43',
    ])
    const dependencies = wrapper.find('[data-suggestion="41"]')
    expect(dependencies.text()).toContain('new section')
    expect(dependencies.text()).toContain('from the scripts')
    expect(dependencies.text()).toContain('Goes after “OVERVIEW”, in the template’s order')
    expect(dependencies.find('[data-citations]').text()).toBe('main.R 1–2')
    expect(wrapper.find('[data-suggestion="42"]').text()).toContain('from your run notes')
    expect(wrapper.find('[data-suggestion="43"] [data-fact-check]').exists()).toBe(true)
  })

  it('a citation opens the script with the cited lines marked', async () => {
    latest = scaffoldReview
    const wrapper = await mountPanel()

    await wrapper.find('[data-suggestion="41"] [data-citations] button').trigger('click')
    await flushPromises()

    const viewer = wrapper.find('[data-source-viewer]')
    expect(http.get).toHaveBeenCalledWith('/api/reviews/30/sources/21')
    expect(viewer.text()).toContain('main.R')
    expect(viewer.text()).toContain('lines 1–2')
    const lines = viewer.findAll('pre > span')
    expect(lines).toHaveLength(4)
    expect(lines.map((line) => line.classes().includes('bg-amber-50'))).toEqual([true, true, false, false])
    expect(lines[2].text()).toContain('token <- "<redacted>"')
  })

  it('accept all accepts every pending suggestion of the scaffold', async () => {
    latest = scaffoldReview
    const wrapper = await mountPanel()
    http.post.mockResolvedValueOnce({ data: { accepted: 3, stale: 0, locked: 0, changes: [], suggestions: [] } })

    await wrapper.find('[data-accept-all]').trigger('click')
    await flushPromises()

    expect(http.post).toHaveBeenCalledWith('/api/reviews/30/accept-all', {})
    expect(reviews.notice).toBe('Accepted 3.')
  })
})
