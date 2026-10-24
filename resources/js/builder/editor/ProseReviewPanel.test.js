import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { useEditorStore } from '@/stores/editor.js'
import { useProseReviewStore } from '@/stores/proseReview.js'

import ProseReviewPanel from './ProseReviewPanel.vue'

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() }))
vi.mock('@/builder/api.js', () => ({
  http,
  clientId: 'my-tab',
  errorMessage: (error) => error.response?.data?.message ?? error.message,
}))
vi.mock('@/builder/realtime.js', () => ({ joinDocument: () => () => {}, listenToReviews: () => () => {} }))

const node = (id, type, props, children = []) => ({ id, type, props, options: {}, version: 1, position: id, children })

const text = (id, blockId, original, suggested, extra = {}) => ({
  id,
  kind: 'text',
  rule: 'hedging',
  severity: 'minor',
  rationale: 'Cut "in order to".',
  blockIds: [blockId],
  original,
  suggested,
  replacement: null,
  status: 'pending',
  ...extra,
})

const restructure = {
  id: 3,
  kind: 'restructure',
  rule: 'list',
  severity: 'major',
  rationale: 'Parallel items read better as a list.',
  blockIds: ['p1'],
  original: null,
  suggested: null,
  replacement: [{ type: 'BulletList', props: { list: ['PHP', 'A database'] }, options: {}, children: [] }],
  status: 'pending',
}

describe('ProseReviewPanel', () => {
  let editor
  let reviews

  beforeEach(async () => {
    vi.clearAllMocks()
    setActivePinia(createPinia())

    http.get.mockImplementation(async (url) => {
      if (url === '/api/documents/doc') {
        return {
          data: {
            document: { id: 7, slug: 'doc', seq: 1, version: 1, meta: {}, titlePage: {} },
            blocks: [
              node('t1', 'PrimaryTopic', { title: 'SETUP' }, [
                node('p1', 'Paragraph', {}, [
                  node('r1', 'RichText', { html: 'In order to install it, you need PHP.' }),
                ]),
              ]),
              node('t2', 'PrimaryTopic', { title: 'USAGE' }),
            ],
            locks: [],
          },
        }
      }
      if (url === '/api/documents/doc/reviews') {
        return { data: { reviews: [{ id: 9, status: 'done', model: 'sonnet', createdAt: new Date().toISOString() }] } }
      }

      return {
        data: {
          review: { id: 9, status: 'done', model: 'sonnet', createdAt: new Date().toISOString() },
          suggestions: [
            text(2, 't2', 'USAGE', 'USING IT', { rule: 'heading' }),
            text(1, 'r1', 'In order to install it', 'To install it'),
            restructure,
            text(4, 'r1', 'you need', 'you must have', { status: 'stale' }),
          ],
        },
      }
    })
    http.post.mockImplementation(async (url, body) =>
      url.endsWith('/locks')
        ? { data: { lock: { target: body.target, userId: 1, name: 'Me', clientId: 'my-tab', ttl: 60 } } }
        : { data: {} }
    )

    editor = useEditorStore()
    await editor.load('doc')
    reviews = useProseReviewStore()
  })

  async function mountPanel() {
    const wrapper = mount(ProseReviewPanel)
    await flushPromises()

    return wrapper
  }

  it('lists pending suggestions by block in document order, with the wording as a diff', async () => {
    const wrapper = await mountPanel()

    const articles = wrapper.findAll('[data-suggestion]').map((article) => article.attributes('data-suggestion'))
    // the paragraph (p1), then the text inside it (r1), then USAGE; the stale one is hidden
    expect(articles).toEqual(['3', '1', '2'])

    const first = wrapper.find('[data-suggestion="1"]')
    expect(first.find('del').text()).toContain('In order to')
    expect(first.find('ins').text()).toContain('To')
  })

  it('shows resolved suggestions on request, saying why they no longer apply', async () => {
    const wrapper = await mountPanel()
    const showResolved = wrapper.findAll('label').find((label) => label.text() === 'Show resolved')
    await showResolved.find('input').setValue(true)

    expect(wrapper.find('[data-suggestion="4"]').text()).toContain('Text changed since the review')
  })

  it('previews a restructure with the real block components', async () => {
    const wrapper = await mountPanel()
    const preview = wrapper.find('[data-suggestion="3"]')

    expect(preview.text()).toContain('In order to install it, you need PHP.')
    expect(preview.findAll('li').map((item) => item.text())).toEqual(['PHP', 'A database'])
  })

  it('accepting hands the change to the editor and updates the suggestion', async () => {
    const wrapper = await mountPanel()
    const change = {
      seq: 2,
      op: 'block.update',
      blockId: 'r1',
      before: node('r1', 'RichText', { html: 'In order to install it, you need PHP.' }),
      after: { ...node('r1', 'RichText', { html: 'To install it, you need PHP.' }), version: 2 },
    }
    http.post.mockResolvedValueOnce({
      data: { suggestion: { ...text(1, 'r1', 'In order to install it', 'To install it'), status: 'accepted' }, change },
    })

    await wrapper.find('[data-suggestion="1"] button').trigger('click')
    await flushPromises()

    expect(http.post).toHaveBeenCalledWith('/api/suggestions/1/accept')
    expect(editor.get('r1').props.html).toBe('To install it, you need PHP.')
    expect(editor.undoStack.at(-1)).toMatchObject({ kind: 'update', id: 'r1' })
    expect(wrapper.find('[data-suggestion="1"]').exists()).toBe(false)
  })

  it('a stale suggestion says so instead of changing anything', async () => {
    const wrapper = await mountPanel()
    http.post.mockRejectedValueOnce({
      response: {
        status: 409,
        data: {
          message: 'The text changed since the review.',
          suggestion: { ...text(1, 'r1', 'x', 'y'), status: 'stale' },
        },
      },
    })

    await wrapper.find('[data-suggestion="1"] button').trigger('click')
    await flushPromises()

    expect(reviews.suggestions.find((suggestion) => suggestion.id === 1).status).toBe('stale')
    expect(wrapper.text()).toContain('The text changed since the review.')
    expect(editor.get('r1').props.html).toBe('In order to install it, you need PHP.')
  })

  it('with the Claude Code runner, shows the command to run there instead of starting one', async () => {
    const list = http.get.getMockImplementation()
    http.get.mockImplementation(async (url) =>
      url === '/api/documents/doc/reviews'
        ? { data: { runner: 'claude-code', reviews: [{ id: 11, status: 'awaiting', model: 'claude-code' }] } }
        : url === '/api/reviews/11'
          ? { data: { review: { id: 11, status: 'awaiting', model: 'claude-code' }, suggestions: [] } }
          : list(url)
    )
    const wrapper = await mountPanel()

    expect(wrapper.find('[data-claude-code] code').text()).toBe('/prose-review doc')
    expect(wrapper.find('input[type="radio"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Claude Code is reviewing (review 11)')

    await wrapper.find('select[aria-label="What to review"]').setValue('document')
    const wordingOnly = wrapper.findAll('label').find((label) => label.text() === 'Structure')
    await wordingOnly.find('input').setValue(false)
    expect(wrapper.find('[data-claude-code] code').text()).toBe('/prose-review doc --kinds=text')

    await wrapper.find('form').trigger('submit')
    expect(http.post.mock.calls.filter(([url]) => url.endsWith('/reviews'))).toEqual([])
    wrapper.unmount()
  })

  it("gives the Claude plan's owner the Review button", async () => {
    const list = http.get.getMockImplementation()
    http.get.mockImplementation(async (url) =>
      url === '/api/documents/doc/reviews'
        ? { data: { runner: 'claude-code', canStart: true, reviews: [] } }
        : list(url)
    )
    const wrapper = await mountPanel()

    expect(wrapper.find('[data-claude-code]').exists()).toBe(false)
    expect(wrapper.find('button[type="submit"]').text()).toBe('Review')
    expect(wrapper.text()).toContain('Runs in Claude Code with your plan')
    expect(wrapper.find('input[type="radio"][value="opus"]').exists()).toBe(true)
  })

  it('starts a review of the chosen section with the chosen model', async () => {
    editor.select('r1')
    const wrapper = await mountPanel()
    http.post.mockResolvedValueOnce({
      data: { review: { id: 10, status: 'queued', createdAt: new Date().toISOString() } },
    })

    await wrapper.find('select[aria-label="What to review"]').setValue('t1')
    await wrapper.find('input[type="radio"][value="opus"]').setValue(true)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(http.post).toHaveBeenCalledWith('/api/documents/doc/reviews', {
      blockId: 't1',
      kinds: ['text', 'restructure'],
      model: 'opus',
    })
  })
})
