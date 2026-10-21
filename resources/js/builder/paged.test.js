// the app loads Prism core and then this plugin in main.js
import Prism from 'prismjs'
import 'prismjs/plugins/line-numbers/prism-line-numbers.js'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createRenderTracker, paginate, printFileName } from './paged.js'

const preview = vi.fn(async () => {})

vi.mock('pagedjs', () => ({
  Previewer: class {
    registerHandlers() {}
    preview = preview
  },
  Handler: class {},
}))

describe('paginate', () => {
  // happy-dom has no font loading API
  Object.defineProperty(document, 'fonts', { value: { ready: Promise.resolve() }, configurable: true })

  beforeEach(() => {
    preview.mockClear()
    document.body.innerHTML = ''
    delete window.PAGED_READY
  })

  it('runs Paged.js on a page with line-numbered code blocks', async () => {
    expect(Prism.plugins.lineNumbers).toBeDefined()
    document.body.innerHTML = '<pre class="line-numbers language-r"><code class="language-r">x &lt;- 1</code></pre>'

    await paginate(createRenderTracker())

    expect(preview).toHaveBeenCalledOnce()
    expect(window.PAGED_READY).toBe(true)
  })

  it('waits for tracked async rendering first', async () => {
    const tracker = createRenderTracker()
    let finished = false
    tracker.track(new Promise((resolve) => setTimeout(() => resolve((finished = true)), 20)))

    preview.mockImplementationOnce(async () => expect(finished).toBe(true))
    await paginate(tracker)

    expect(preview).toHaveBeenCalledOnce()
  })
})

describe('printFileName', () => {
  it('fills date-fns patterns in braces', () => {
    expect(printFileName('oh-pipeline-tech-manual_{yyyyMMdd}', new Date(2026, 9, 21))).toBe(
      'oh-pipeline-tech-manual_20261021'
    )
  })
})
