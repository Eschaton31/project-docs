import { h } from 'vue'

import InlineCells from './InlineCells.vue'
import InlineRichText from './InlineRichText.vue'
import InlineText from './InlineText.vue'
import { fitsRichText } from './richText.js'

/**
 * What can be edited in place on the page, and where.
 *
 * kind 'rich': the whole block becomes a formatter (RichText). kind 'text': one plain-text prop is
 * edited inside the component's named slot. kind 'cells': every cell/item of a table or list becomes
 * editable through the component's scoped slots (see InlineCells and inlineData.js for `shape`).
 * hit: the part of the block that has to be clicked (a topic's heading, not its body).
 *
 * For cells: lines = columns that take several lines; plain / plainFields = text the component renders
 * as text rather than HTML; nullable = an emptied cell is NULL; columns = for a first row in an empty table.
 */
const TITLE = { kind: 'text', prop: 'title', slot: 'title' }
const LIST = { kind: 'cells', prop: 'list', shape: 'list' }
const TABLE = { kind: 'cells', prop: 'data', shape: 'table' }

export const INLINE_EDITABLE = {
  RichText: { kind: 'rich', prop: 'html' },
  PrimaryTopic: { ...TITLE, hit: '.primary-topic' },
  SecondaryTopic: { ...TITLE, hit: '.secondary-topic' },
  TertiaryTopic: { ...TITLE, hit: '.tertiary-topic' },
  QuaternaryTopic: { ...TITLE, hit: '.quaternary-topic' },
  TertiaryTitle: { kind: 'text', prop: 'text', slot: 'default' },
  Placeholder: { kind: 'text', prop: 'text', slot: 'default' },
  Alert: { kind: 'text', prop: 'title', slot: 'title', hit: '.alert-title' },
  Image: { kind: 'text', prop: 'description', slot: 'description', hit: '.image-caption' },

  BulletList: LIST,
  NumberedList: LIST,
  ConceptList: { ...LIST, shape: 'concepts' },
  Breadcrumbs: { ...LIST, shape: 'flat', plain: true },
  FileTree: { ...LIST, shape: 'tree', plain: true },

  ItemDescriptionTable: { ...TABLE, lines: ['description'], columns: ['item', 'description'] },
  KeyValueTable: { ...TABLE, prop: 'table', lines: ['value'], plainFields: ['id'], columns: ['id', 'value'] },
  ConversionDictionary: { ...TABLE, lines: ['description', 'formats'], columns: ['item', 'description', 'formats'] },
  DataDictionary: { ...TABLE, lines: ['values'], columns: ['field', 'description', 'type', 'values'] },
  TechStack: { ...TABLE, lines: ['tech', 'framework'], columns: ['component', 'tech', 'framework'] },
  SqlTable: { ...TABLE, plain: true, nullable: true },
}

/**
 * Whether clicking `target` inside this block should start editing it in place.
 * @returns {{ ok: true } | { ok: false, reason?: string }}
 */
export function inlineEditCheck(node, target) {
  const rule = INLINE_EDITABLE[node?.type]
  if (!rule) return { ok: false }
  if (rule.hit && !target.closest(rule.hit)) return { ok: false }

  const value = node.props?.[rule.prop] ?? ''
  if (rule.kind === 'rich' && !fitsRichText(value)) {
    return { ok: false, reason: 'This text contains layout HTML; edit it in the panel on the right.' }
  }
  // e.g. an alert title written with markup: plain-text editing would flatten it
  if (rule.kind === 'text' && /<[a-z]/i.test(value)) {
    return { ok: false, reason: 'This contains HTML; edit it in the panel on the right.' }
  }

  return { ok: true }
}

/**
 * Renderer hook for BlockList (provided as 'inlineEdit'): swaps the block being edited for an in-place
 * editor. editing() returns { id, coords } or null; update(id, prop, value) saves; stop() ends editing.
 */
export function createInlineEdit({ editing, update, stop }) {
  return {
    override(node) {
      const current = editing()
      const rule = INLINE_EDITABLE[node.type]
      if (!rule || current?.id !== node.id) return null

      const props = {
        coords: current.coords,
        onUpdate: (value) => update(node.id, rule.prop, value),
        onDone: stop,
      }

      if (rule.kind === 'cells') {
        return {
          element: h(InlineCells, {
            key: node.id,
            'data-block-id': node.id,
            node,
            rule,
            coords: current.coords,
            onUpdate: (value) => update(node.id, rule.prop, value),
            onUpdateProp: (prop, value) => update(node.id, prop, value),
            onDone: stop,
          }),
        }
      }

      if (rule.kind === 'rich') {
        return {
          element: h(InlineRichText, {
            key: node.id,
            'data-block-id': node.id,
            html: node.props?.html ?? '',
            ...props,
          }),
        }
      }

      return { slots: { [rule.slot]: () => h(InlineText, { value: node.props?.[rule.prop] ?? '', ...props }) } }
    },
  }
}
