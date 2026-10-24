import { formatDistanceToNow } from 'date-fns'

import { registry } from '../registry.js'

/** "Ana edited Paragraph “…”" wording for revision entries (block history, activity feed). */
const VERBS = {
  'block.create': 'added',
  'block.insert': 'added',
  'block.update': 'edited',
  'block.move': 'moved',
  'block.delete': 'deleted',
  'block.restore': 'restored',
  'block.replace': 'restructured into',
  'document.update': 'changed the document settings',
  'document.create': 'created the document',
  'document.import': 'imported the document',
}

export function describe(entry) {
  const verb = VERBS[entry.op] ?? entry.op
  if (!entry.block) return verb

  const type = registry[entry.block.type]?.label ?? entry.block.type
  const more = entry.block.count > 1 ? ` (+${entry.block.count - 1} more)` : ''

  return `${verb} ${type}${entry.block.label ? ` “${entry.block.label}”` : ''}${more}`
}

export function ago(iso) {
  return iso ? formatDistanceToNow(new Date(iso), { addSuffix: true }) : ''
}

/** Prop names whose values differ between two versions of a block. */
export function changedFields(before, after) {
  const a = { ...(before?.props ?? {}), ...prefix(before?.options) }
  const b = { ...(after?.props ?? {}), ...prefix(after?.options) }

  return [...new Set([...Object.keys(a), ...Object.keys(b)])].filter(
    (key) => JSON.stringify(a[key]) !== JSON.stringify(b[key])
  )
}

function prefix(options) {
  return Object.fromEntries(Object.entries(options ?? {}).map(([key, value]) => [`option: ${key}`, value]))
}
