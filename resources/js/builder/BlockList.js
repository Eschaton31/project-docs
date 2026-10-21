import { defineComponent, h, inject } from 'vue'

import { registry } from './registry.js'

/**
 * Renders a list of stored blocks with the hand-built components.
 *
 * mode 'draft' shows everything except hidden blocks; 'final' also leaves out draft blocks and
 * topics with nothing left in them.
 *
 * The editor's canvas provides 'inlineEdit' (see editor/inline.js) to swap the block being edited in
 * place for an editor; the print view doesn't, so it never loads editor code.
 */
export const BlockList = defineComponent({
  name: 'BlockList',
  props: {
    nodes: { type: Array, required: true },
    mode: { type: String, default: 'draft' },
  },
  setup(props) {
    const inline = inject('inlineEdit', null)

    return () => renderNodes(props.nodes, props.mode, inline)
  },
})

export function visibleNodes(nodes, mode) {
  return nodes.filter((node) => {
    const options = node.options ?? {}
    if (options.hidden) return false
    if (mode !== 'final') return true
    if (options.draft) return false

    return !registry[node.type]?.topic || visibleNodes(node.children ?? [], mode).length > 0
  })
}

function renderNodes(nodes, mode, inline) {
  // numbering restarts in every parent, matching the hand-set start="1", start="2"… on the old pages
  let secondary = 0

  return visibleNodes(nodes, mode).map((node) =>
    renderNode(node, mode, inline, node.type === 'SecondaryTopic' ? String(++secondary) : undefined)
  )
}

function renderNode(node, mode, inline, start) {
  const definition = registry[node.type]
  if (!definition) {
    return h('div', { key: node.id, class: 'text-[10pt] text-rose-600' }, `Unknown block type “${node.type}”`)
  }

  const override = inline?.override(node)
  if (override?.element) return override.element

  const options = node.options ?? {}
  const attrs = { key: node.id, 'data-block-id': node.id }
  for (const prop of definition.props) {
    if (node.props?.[prop] !== undefined) attrs[prop] = node.props[prop]
  }
  if (start) attrs.start = start
  if (options.anchorId) attrs.id = options.anchorId
  if (options.pageBreakBefore) attrs.class = 'new-page'

  const slots = {}
  if (definition.slot === 'blocks') slots.default = () => renderNodes(node.children ?? [], mode, inline)
  if (definition.slot === 'text') slots.default = () => node.props?.text ?? ''
  Object.assign(slots, override?.slots)

  return h(definition.component, attrs, slots)
}
