/**
 * Reading and changing one cell or item of a list/table prop, for editing it in place on the page.
 *
 * An address is { path, field }:
 * - list (BulletList, NumberedList): path indexes into nested arrays ([1, 0] = list[1][0]), field null
 * - flat (Breadcrumbs): path [i], field null
 * - concepts (ConceptList): path through children ([1, 0] = list[1].children[0]), field 'id' | 'value'
 * - tree (FileTree): path through children, field 'name' | 'note'
 * - table: path [row], field = column key
 *
 * Every change returns a new value; the input is never mutated.
 */

const CHILDREN = { concepts: 'children', tree: 'children' }

const NEW_ITEM = {
  list: () => '',
  flat: () => '',
  concepts: () => ({ id: '', value: '' }),
  tree: () => ({ type: 'file', name: '' }),
}

/** The field a new item is typed into first. */
const FIRST_FIELD = { list: null, flat: null, concepts: 'id', tree: 'name' }

export function readAt(value, kind, { path, field }) {
  const item = locate(value ?? [], kind, path)

  return field == null ? item : item?.[field]
}

export function writeAt(value, kind, { path, field }, next) {
  const copy = clone(value)
  const { siblings, index } = parentOf(copy, kind, path)
  if (field == null) siblings[index] = next
  else siblings[index] = { ...siblings[index], [field]: next }

  return copy
}

/**
 * Inserts an empty item (or table row) after the addressed one.
 * @returns {{ value, address }} address of the new item, to move the caret there
 */
export function insertAfter(value, kind, address, { nullable = false } = {}) {
  const copy = clone(value)
  const { siblings, index } = parentOf(copy, kind, address.path)
  siblings.splice(index + 1, 0, newItem(kind, siblings[index], nullable))

  const path = [...address.path.slice(0, -1), index + 1]

  return { value: copy, address: { path, field: kind === 'table' ? address.field : FIRST_FIELD[kind] } }
}

/** Adds an empty item (or row) at the end of the top level. */
export function append(value, kind, { nullable = false, columns = null } = {}) {
  const copy = clone(value)
  const template =
    kind === 'table' ? (copy.at(-1) ?? Object.fromEntries((columns ?? []).map((key) => [key, '']))) : null
  copy.push(newItem(kind, template, nullable))

  return {
    value: copy,
    address: {
      path: [copy.length - 1],
      field: kind === 'table' ? (columns?.[0] ?? Object.keys(template ?? {})[0]) : FIRST_FIELD[kind],
    },
  }
}

/**
 * Removes the addressed item (or row), and any sub-items nested in it.
 * @returns {{ value, address }} where the caret should go next (the item before, or null)
 */
export function removeAt(value, kind, address) {
  const copy = clone(value)
  const { siblings, index } = parentOf(copy, kind, address.path)
  siblings.splice(index, 1)

  // a bullet item followed by its sub-list: the sub-list goes with it
  if (kind === 'list' && Array.isArray(siblings[index]) && !Array.isArray(siblings[index - 1]))
    siblings.splice(index, 1)

  const parentPath = address.path.slice(0, -1)
  const field = kind === 'table' ? address.field : FIRST_FIELD[kind]
  if (index > 0) {
    // step back over a nested list to the item it belongs to
    const before = kind === 'list' && Array.isArray(siblings[index - 1]) ? index - 2 : index - 1
    if (before >= 0) return { value: copy, address: { path: [...parentPath, before], field } }
  }
  if (siblings.length) return { value: copy, address: { path: [...parentPath, 0], field } }

  return { value: copy, address: parentPath.length ? { path: parentPath, field } : null }
}

export const addressKey = (address) => (address ? JSON.stringify([address.path, address.field ?? null]) : '')

function locate(value, kind, path) {
  let siblings = value
  for (const index of path.slice(0, -1)) {
    siblings = CHILDREN[kind] ? siblings?.[index]?.[CHILDREN[kind]] : siblings?.[index]
  }

  return siblings?.[path.at(-1)]
}

function parentOf(value, kind, path) {
  let siblings = value
  for (const index of path.slice(0, -1)) {
    siblings = CHILDREN[kind] ? (siblings[index][CHILDREN[kind]] ??= []) : siblings[index]
  }

  return { siblings, index: path.at(-1) }
}

function newItem(kind, like, nullable) {
  if (kind !== 'table') return NEW_ITEM[kind]()

  return Object.fromEntries(Object.keys(like ?? {}).map((key) => [key, nullable ? null : '']))
}

function clone(value) {
  return JSON.parse(JSON.stringify(value ?? []))
}
