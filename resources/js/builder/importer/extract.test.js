import DataManagement from '@/DataManagement.vue'
import Handover from '@/Handover.vue'
import IscervUserManual from '@/IscervUserManual.vue'
import OhasisLinelists from '@/OhasisLinelists.vue'
import ServerMigration2026 from '@/ServerMigration2026.vue'
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import { nextTick } from 'vue'

import DocumentRenderer from '../DocumentRenderer.vue'
import { extractDocument, toCssSize } from './extract.js'

const pages = {
  'server-migration-2026': ServerMigration2026,
  'data-management': DataManagement,
  handover: Handover,
  'iscerv-user-manual': IscervUserManual,
  'ohasis-linelists': OhasisLinelists,
}

async function convert(slug) {
  const legacy = mount(pages[slug], { attachTo: document.body })
  await nextTick()
  const result = extractDocument(legacy.vm.$, { slug })

  return { legacy, ...result }
}

async function render(document) {
  // what the API would hand back
  const stored = JSON.parse(JSON.stringify(document))
  const rendered = mount(DocumentRenderer, {
    props: { document: stored, blocks: stored.blocks },
    attachTo: window.document.body,
  })
  await nextTick()

  return rendered
}

/** Text a reader sees, ignoring whitespace and the <title> used for the print filename. */
function visibleText(element) {
  const copy = element.cloneNode(true)
  copy.querySelectorAll('title').forEach((title) => title.remove())

  return copy.textContent.replace(/\s+/g, ' ').trim()
}

/** Short excerpt around the first mismatch; whole documents are too long to diff in test output. */
function firstDifference(actual, expected) {
  if (actual === expected) return null

  let index = 0
  while (actual[index] === expected[index]) index++
  const excerpt = (text) => text.slice(Math.max(0, index - 80), index + 80)

  return { index, rendered: excerpt(actual), original: excerpt(expected) }
}

function find(blocks, predicate) {
  for (const block of blocks) {
    if (predicate(block)) return block
    const found = find(block.children, predicate)
    if (found) return found
  }
}

describe.each(Object.keys(pages))('importing %s', (slug) => {
  it('produces blocks the API will accept', async () => {
    const { document, errors } = await convert(slug)

    expect(errors).toEqual([])
    expect(document.blocks.length).toBeGreaterThan(0)
    expect(document.blocks.every((block) => block.type === 'PrimaryTopic')).toBe(true)
  })

  it('renders the same text as the hand-built page', async () => {
    const { legacy, document } = await convert(slug)
    const rendered = await render(document)

    expect(firstDifference(visibleText(rendered.element), visibleText(legacy.element))).toBeNull()
  })
})

describe('importing data-management', () => {
  it('reads the document settings', async () => {
    const { document } = await convert('data-management')

    expect(document.meta).toEqual({
      fileName: 'oh-pipeline-tech-manual_{yyyyMMdd}',
      runningTitle: { bold: 'OHASIS DATA PIPELINE', light: 'TECHNICAL MANUAL' },
      toc: true,
    })
    expect(document.titlePage).toMatchObject({
      title: 'OHASIS Data Pipeline',
      subtitle: 'Technical Manual',
      version: '1.0',
    })
    expect(document.title).toBe('OHASIS Data Pipeline – Technical Manual')
  })

  it('flattens section files into the tree and keeps manual page breaks', async () => {
    const { document } = await convert('data-management')
    const setup = document.blocks.find((block) => block.props.title === 'SET-UP')
    const subsections = setup.children.filter((block) => block.type === 'SecondaryTopic')

    expect(subsections.map((block) => block.props.title)).toEqual([
      'Infrastructure',
      'User Access Control',
      'Version Control',
    ])
    expect(subsections[2].options).toEqual({ pageBreakBefore: true })
  })

  it('keeps v-for content and inline markup', async () => {
    const { document } = await convert('data-management')
    const audience = find(document.blocks, (block) => block.type === 'ConceptList')

    expect(audience.props.list[0]).toEqual({ id: 'Primary', value: 'Data Management Team, NHSSS Unit' })
    expect(
      find(document.blocks, (block) => block.props.html?.includes('<codetext>.GlobalEnv</codetext>'))
    ).toBeDefined()
    expect(find(document.blocks, (block) => block.type === 'CodeBlock' && block.props.lang === 'ini')).toBeDefined()
  })
})

describe('importing ohasis-linelists', () => {
  it('turns <colgroup> slot content into SqlTable column widths', async () => {
    const { document } = await convert('ohasis-linelists')
    const table = find(document.blocks, (block) => block.type === 'SqlTable' && block.props.columnWidths)

    expect(table.props.columnWidths).toEqual(['13%', '7%', '15%', '14%', '14%', '15%', '23%'])
  })

  it('converts Tailwind image heights to CSS lengths', async () => {
    const { document } = await convert('ohasis-linelists')
    const heights = new Set()
    const collect = (blocks) =>
      blocks.forEach((block) => (block.type === 'Image' && heights.add(block.props.height), collect(block.children)))
    collect(document.blocks)

    expect([...heights].every((height) => /^\d+(\.\d+)?rem$/.test(height))).toBe(true)
  })
})

describe('toCssSize', () => {
  it.each([
    ['h-[15rem]', 'h-', '15rem'],
    ['h-48', 'h-', '12rem'],
    ['h-28', 'h-', '7rem'],
    ['w-[50%]', 'w-', '50%'],
    ['w-1/2', 'w-', '50%'],
    ['15rem', 'h-', '15rem'],
  ])('%s → %s', (value, prefix, expected) => {
    expect(toCssSize(value, prefix)).toBe(expected)
  })
})
