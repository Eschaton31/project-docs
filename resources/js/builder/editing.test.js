import { describe, expect, it } from 'vitest'

import {
  blockEditing,
  conceptsToText,
  listToText,
  newBlock,
  outlineToNodes,
  parseDelimited,
  textToConcepts,
  textToList,
  textToTree,
  treeToText,
} from './editing.js'
import { canContain, registry } from './registry.js'

describe('outlineToNodes', () => {
  const shape = (nodes) => nodes.map((node) => [node.type, node.props.title, shape(node.children)])

  it('turns indented headings into nested topics', () => {
    const outline = [
      'HIV TREATMENT REGISTRY',
      '  Data Catalog',
      '    Correction Tables',
      '      corr_reg',
      '  Set-up',
      'MORTALITY REGISTRY',
    ].join('\n')

    expect(shape(outlineToNodes(outline, 'PrimaryTopic'))).toEqual([
      [
        'PrimaryTopic',
        'HIV TREATMENT REGISTRY',
        [
          [
            'SecondaryTopic',
            'Data Catalog',
            [['TertiaryTopic', 'Correction Tables', [['QuaternaryTopic', 'corr_reg', []]]]],
          ],
          ['SecondaryTopic', 'Set-up', []],
        ],
      ],
      ['PrimaryTopic', 'MORTALITY REGISTRY', []],
    ])
  })

  it('starts lower on the ladder, never skips a level, and stops at QuaternaryTopic', () => {
    const nodes = outlineToNodes('Data Catalog\n\t\t\tskipped two levels\n\t\t\t\t\tway too deep', 'TertiaryTopic')

    expect(shape(nodes)).toEqual([
      [
        'TertiaryTopic',
        'Data Catalog',
        [
          ['QuaternaryTopic', 'skipped two levels', []],
          ['QuaternaryTopic', 'way too deep', []],
        ],
      ],
    ])
  })
})

describe('list text', () => {
  it('round-trips nested lists through indented lines', () => {
    const list = ['To provide a framework', ['first <b>detail</b>', 'second', ['deeper']], 'Link to resources']

    expect(listToText(list)).toBe(
      'To provide a framework\n  first <b>detail</b>\n  second\n    deeper\nLink to resources'
    )
    expect(textToList(listToText(list))).toEqual(list)
  })
})

describe('concept list text', () => {
  it('round-trips terms, definitions and nesting', () => {
    const list = [
      { id: 'Primary', value: 'Data Management Team, NHSSS Unit' },
      {
        id: '<codetext>autoload.R</codetext>',
        value: 'Central autoloader: discovers components',
        children: [{ id: 'Stage', value: '' }],
      },
    ]
    const text = conceptsToText(list)

    expect(text).toBe(
      'Primary: Data Management Team, NHSSS Unit\n<codetext>autoload.R</codetext>: Central autoloader: discovers components\n  Stage'
    )
    expect(textToConcepts(text)).toEqual(list)
  })
})

describe('file tree text', () => {
  it('round-trips types, names with spaces, notes and children', () => {
    const tree = [
      {
        type: 'folder',
        name: 'runbook system',
        note: 'main scripts',
        children: [{ type: 'r', name: 'autoload.R' }, { name: 'README' }],
      },
    ]
    const text = treeToText(tree)

    expect(text).toBe('folder runbook system | main scripts\n  r autoload.R\n  README')
    expect(textToTree(text)).toEqual(tree)
  })

  it('treats a lone type word as a name', () => {
    expect(textToTree('server')).toEqual([{ name: 'server' }])
  })
})

describe('parseDelimited', () => {
  it('reads tab-separated rows from a spreadsheet or SQL client', () => {
    expect(parseDelimited('faci_id\tfaci_name\r\n040001\tSan Lazaro Hospital\r\n')).toEqual([
      ['faci_id', 'faci_name'],
      ['040001', 'San Lazaro Hospital'],
    ])
  })

  it('reads CSV with quoted commas, quotes and line breaks', () => {
    expect(parseDelimited('id,name\n1,"Smith, ""Jo"""\n2,"two\nlines"')).toEqual([
      ['id', 'name'],
      ['1', 'Smith, "Jo"'],
      ['2', 'two\nlines'],
    ])
  })
})

describe('block templates', () => {
  it('exist for every block type and only contain allowed children', () => {
    for (const type of Object.keys(registry)) {
      expect(blockEditing[type], type).toBeDefined()
      const block = newBlock(type)
      block.children.forEach((child) => expect(canContain(type, child.type), `${child.type} in ${type}`).toBe(true))
    }
  })
})
