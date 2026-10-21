import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import { reactive } from 'vue'

import InlineCells from './InlineCells.vue'
import { INLINE_EDITABLE, inlineEditCheck } from './inline.js'

/** Mounts a block for editing in place; saves are applied to it the way the store would. */
async function edit(type, props) {
  const node = reactive({ id: 'b1', type, props, options: {} })
  const rule = INLINE_EDITABLE[type]
  const saves = []
  const wrapper = mount(InlineCells, {
    props: {
      node,
      rule,
      onUpdate: (value) => {
        saves.push(value)
        node.props = { ...node.props, [rule.prop]: value }
      },
    },
    attachTo: document.body,
  })
  await flushPromises()

  return { wrapper, node, saves, cells: () => wrapper.findAll('[data-inline-cell]') }
}

async function type(cell, html) {
  cell.element.innerHTML = html
  await cell.trigger('input')
  await flushPromises()
}

describe('editing tables in place', () => {
  it('edits cells and keeps their formatting', async () => {
    const { cells, saves, node } = await edit('ItemDescriptionTable', {
      data: [{ item: 'September 7', description: 'Installation at <b>DOH-EB</b>' }],
    })

    expect(cells()).toHaveLength(2)
    expect(cells()[1].html()).toContain('<b>DOH-EB</b>')
    // the first cell has the caret to start with
    expect(document.activeElement).toBe(cells()[0].element)

    await type(cells()[0], 'September 7,&nbsp;2026')
    expect(saves.at(-1)).toEqual([{ item: 'September 7, 2026', description: 'Installation at <b>DOH-EB</b>' }])
    expect(node.props.data[0].item).toBe('September 7, 2026')
  })

  it('saves several lines in a lines column as an array', async () => {
    const { cells, saves } = await edit('ItemDescriptionTable', { data: [{ item: 'Sept 7', description: 'one' }] })

    await type(cells()[1], 'Installation<br>Initial <codetext>planning</codetext><br>')
    expect(saves.at(-1)[0].description).toEqual(['Installation', 'Initial <codetext>planning</codetext>'])
  })

  it('drops markup the pages do not use', async () => {
    const { cells, saves } = await edit('TechStack', {
      data: [{ component: 'API', tech: 'PHP', framework: 'Laravel' }],
    })

    await type(cells()[2], '<span style="color:red">Laravel</span> <font face="x">13</font>')
    expect(saves.at(-1)[0].framework).toBe('Laravel 13')
  })

  it('moves down a row on Enter, and adds and removes rows from the toolbar', async () => {
    const { wrapper, cells, saves } = await edit('KeyValueTable', {
      table: [
        { id: 'OS', value: 'Windows' },
        { id: 'RAM', value: '32 GB' },
      ],
    })

    await cells()[0].trigger('keydown', { key: 'Enter' })
    await flushPromises()
    expect(document.activeElement).toBe(cells()[2].element)

    await cells()[2].trigger('focus')
    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'Add row')
      .trigger('click')
    await flushPromises()
    expect(saves.at(-1)).toEqual([
      { id: 'OS', value: 'Windows' },
      { id: 'RAM', value: '32 GB' },
      { id: '', value: '' },
    ])
    expect(cells()).toHaveLength(6)

    await cells()[4].trigger('focus')
    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'Remove row')
      .trigger('click')
    await flushPromises()
    expect(saves.at(-1)).toHaveLength(2)
  })

  it('treats an emptied SQL result cell as NULL', async () => {
    const { cells, saves } = await edit('SqlTable', { data: [{ faci_id: '040001', faci_name: 'San Lazaro' }] })

    cells()[1].element.textContent = ''
    await cells()[1].trigger('input')
    expect(saves.at(-1)).toEqual([{ faci_id: '040001', faci_name: null }])
  })

  it('leaves cells with layout HTML to the panel', async () => {
    const { wrapper, cells } = await edit('DataDictionary', {
      data: [{ field: 'uic', description: '<div class="grid">x</div>', type: 'VARCHAR', values: '' }],
    })

    expect(cells()).toHaveLength(3)
    expect(wrapper.find('[title^="Contains layout HTML"]').exists()).toBe(true)
  })
})

describe('editing lists in place', () => {
  it('adds an item on Enter and removes an emptied one on Backspace', async () => {
    const { cells, saves } = await edit('BulletList', {
      list: ['To provide a framework', ['first detail'], 'Link to resources'],
    })

    expect(cells().map((cell) => cell.text())).toEqual(['To provide a framework', 'first detail', 'Link to resources'])

    // Enter in the nested item adds a sibling inside the sub-list
    await cells()[1].trigger('keydown', { key: 'Enter' })
    await flushPromises()
    expect(saves.at(-1)).toEqual(['To provide a framework', ['first detail', ''], 'Link to resources'])
    expect(document.activeElement).toBe(cells()[2].element)

    await cells()[2].trigger('keydown', { key: 'Backspace' })
    await flushPromises()
    expect(saves.at(-1)).toEqual(['To provide a framework', ['first detail'], 'Link to resources'])
    expect(document.activeElement).toBe(cells()[1].element)
  })

  it('nests an item with Tab and moves it back out with Shift+Tab', async () => {
    const { cells, saves } = await edit('NumberedList', { list: ['Offline', 'Migrate', 'Verify'] })

    await cells()[1].trigger('keydown', { key: 'Tab' })
    await flushPromises()
    expect(saves.at(-1)).toEqual(['Offline', ['Migrate'], 'Verify'])
    expect(document.activeElement).toBe(cells()[1].element)
    expect(cells().map((cell) => cell.text())).toEqual(['Offline', 'Migrate', 'Verify'])

    await cells()[1].trigger('keydown', { key: 'Tab', shiftKey: true })
    await flushPromises()
    expect(saves.at(-1)).toEqual(['Offline', 'Migrate', 'Verify'])

    // the first item has nothing to nest under
    await cells()[0].trigger('keydown', { key: 'Tab' })
    expect(saves).toHaveLength(2)
  })

  it('bolds the list holding the caret: the top level as a prop, a sub-list on its own', async () => {
    const { wrapper, cells, saves } = await edit('BulletList', { list: ['Phases', ['Planning'], 'Done'] })
    const boldButton = () => wrapper.findAll('button').find((b) => /^Bold/.test(b.text()))

    await cells()[0].trigger('focus')
    expect(boldButton().text()).toBe('Bold list')
    await boldButton().trigger('click')
    expect(wrapper.emitted('update-prop')).toEqual([['bold', true]])
    expect(saves).toEqual([])

    await cells()[1].trigger('focus')
    expect(boldButton().text()).toBe('Bold sub-list')
    await boldButton().trigger('click')
    await flushPromises()
    expect(saves.at(-1)).toEqual(['Phases', { bold: true, list: ['Planning'] }, 'Done'])
    expect(boldButton().attributes('aria-pressed')).toBe('true')
  })

  it('goes from a term to its definition, then to a new concept', async () => {
    const { cells, saves } = await edit('ConceptList', { list: [{ id: 'Primary', value: 'Data Management Team' }] })

    await cells()[0].trigger('keydown', { key: 'Enter' })
    await flushPromises()
    expect(document.activeElement).toBe(cells()[1].element)
    expect(saves).toEqual([])

    await cells()[1].trigger('keydown', { key: 'Enter' })
    await flushPromises()
    expect(saves.at(-1)).toEqual([
      { id: 'Primary', value: 'Data Management Team' },
      { id: '', value: '' },
    ])
    expect(document.activeElement).toBe(cells()[2].element)
  })

  it('edits file tree names and notes as plain text', async () => {
    const { cells, saves } = await edit('FileTree', {
      list: [{ type: 'folder', name: 'src', note: 'scripts', children: [{ type: 'r', name: 'autoload.R' }] }],
    })

    expect(cells().map((cell) => cell.text())).toEqual(['src', 'scripts', 'autoload.R'])
    cells()[2].element.textContent = 'autoload_v2.R'
    await cells()[2].trigger('input')
    expect(saves.at(-1)[0].children[0]).toEqual({ type: 'r', name: 'autoload_v2.R' })
  })
})

it('lets every table and list start editing in place', () => {
  const anywhere = document.createElement('div')
  for (const type of [
    'BulletList',
    'NumberedList',
    'ConceptList',
    'Breadcrumbs',
    'FileTree',
    'ItemDescriptionTable',
    'KeyValueTable',
    'ConversionDictionary',
    'DataDictionary',
    'TechStack',
    'SqlTable',
  ]) {
    expect(inlineEditCheck({ type, props: {} }, anywhere).ok, type).toBe(true)
  }
})
