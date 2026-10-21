import { describe, expect, it } from 'vitest'

import { addressKey, append, insertAfter, readAt, removeAt, writeAt } from './inlineData.js'

describe('nested bullet lists', () => {
  const list = ['To provide a framework', ['first detail', 'second detail'], 'Link to resources']

  it('reads and writes items at any depth without touching the input', () => {
    expect(readAt(list, 'list', { path: [1, 0], field: null })).toBe('first detail')
    expect(readAt(list, 'list', { path: [2], field: null })).toBe('Link to resources')

    const next = writeAt(list, 'list', { path: [1, 1], field: null }, 'second <b>detail</b>')
    expect(next).toEqual(['To provide a framework', ['first detail', 'second <b>detail</b>'], 'Link to resources'])
    expect(list[1][1]).toBe('second detail')
  })

  it('inserts after an item and points at the new one', () => {
    expect(insertAfter(list, 'list', { path: [1, 0], field: null })).toEqual({
      value: ['To provide a framework', ['first detail', '', 'second detail'], 'Link to resources'],
      address: { path: [1, 1], field: null },
    })
  })

  it('removes an item together with its sub-list, landing on the item before', () => {
    expect(removeAt(list, 'list', { path: [0], field: null })).toEqual({
      value: ['Link to resources'],
      address: { path: [0], field: null },
    })
    expect(removeAt(list, 'list', { path: [2], field: null })).toEqual({
      value: ['To provide a framework', ['first detail', 'second detail']],
      address: { path: [0], field: null },
    })
  })
})

describe('concept lists and file trees', () => {
  const concepts = [
    { id: 'Primary', value: 'DMT', children: [{ id: 'Lead', value: 'Ana' }] },
    { id: 'Secondary', value: 'Analysts' },
  ]

  it('address terms and definitions through children', () => {
    expect(readAt(concepts, 'concepts', { path: [0, 0], field: 'value' })).toBe('Ana')
    expect(writeAt(concepts, 'concepts', { path: [0, 0], field: 'id' }, 'Head')[0].children[0]).toEqual({
      id: 'Head',
      value: 'Ana',
    })
  })

  it('inserts an empty concept and starts at its term', () => {
    const { value, address } = insertAfter(concepts, 'concepts', { path: [0], field: 'value' })
    expect(value[1]).toEqual({ id: '', value: '' })
    expect(address).toEqual({ path: [1], field: 'id' })
  })

  it('inserts file tree entries as files', () => {
    const tree = [{ type: 'folder', name: 'src', children: [{ type: 'r', name: 'autoload.R' }] }]
    const { value, address } = insertAfter(tree, 'tree', { path: [0, 0], field: 'note' })

    expect(value[0].children).toEqual([
      { type: 'r', name: 'autoload.R' },
      { type: 'file', name: '' },
    ])
    expect(address).toEqual({ path: [0, 1], field: 'name' })
  })
})

describe('tables', () => {
  const rows = [
    { item: 'September 7', description: ['Installation', 'Planning'] },
    { item: 'September 16', description: 'Servers off' },
  ]

  it('writes a cell, inserts and appends rows with the same columns', () => {
    expect(writeAt(rows, 'table', { path: [1], field: 'item' }, 'Sept 16')[1]).toEqual({
      item: 'Sept 16',
      description: 'Servers off',
    })

    const inserted = insertAfter(rows, 'table', { path: [0], field: 'description' })
    expect(inserted.value[1]).toEqual({ item: '', description: '' })
    expect(inserted.address).toEqual({ path: [1], field: 'description' })

    expect(append([], 'table', { columns: ['item', 'description'] })).toEqual({
      value: [{ item: '', description: '' }],
      address: { path: [0], field: 'item' },
    })
  })

  it('uses NULL for new SQL result cells', () => {
    expect(
      insertAfter([{ id: '1', name: 'x' }], 'table', { path: [0], field: 'id' }, { nullable: true }).value[1]
    ).toEqual({ id: null, name: null })
  })

  it('removes a row and lands on the one before', () => {
    expect(removeAt(rows, 'table', { path: [1], field: 'item' })).toEqual({
      value: [rows[0]],
      address: { path: [0], field: 'item' },
    })
    expect(removeAt([rows[0]], 'table', { path: [0], field: 'item' })).toEqual({ value: [], address: null })
  })
})

it('gives addresses a stable key', () => {
  expect(addressKey({ path: [1, 0], field: 'id' })).toBe(addressKey({ path: [1, 0], field: 'id' }))
  expect(addressKey({ path: [1], field: null })).not.toBe(addressKey({ path: [1], field: 'id' }))
})
