import { Comment, Fragment, Static, Text, toRaw } from 'vue'

import TitlePage from '@/components/TitlePage.vue'

import { canContain, registry, typeOfComponent } from '../registry.js'

/**
 * Converts a mounted hand-built page into the stored document format.
 *
 * Walks the rendered component tree rather than parsing the SFC source, so v-for loops and data from
 * <script setup> arrive already evaluated:
 * - registry components become blocks with their resolved props;
 * - plain HTML/text between them becomes RichText (rendered outerHTML);
 * - other components (the section files under pages/) are flattened into their parent.
 *
 * @returns {{ document: object, warnings: string[], errors: string[] }}
 */
export function extractDocument(rootInstance, { slug }) {
  const ctx = new Context()
  const document = {
    schemaVersion: 1,
    slug,
    title: slug,
    meta: { fileName: `${slug}_{yyyyMMdd}`, runningTitle: { bold: '', light: '' }, toc: false },
    titlePage: {},
    blocks: [],
  }

  const content = []
  for (const vnode of flatten(childrenOf(rootInstance.subTree))) {
    if (vnode.type === 'title') {
      // the date was baked in when the page rendered; store the pattern instead
      document.meta.fileName = vnode.el.textContent.trim().replace(/_\d{8}$/, '_{yyyyMMdd}')
    } else if (vnode.type === 'span' && vnode.el.classList.contains('title')) {
      const [bold = '', light = ''] = [...vnode.el.children].map((span) => span.textContent.trim())
      document.meta.runningTitle = { bold: bold.replace(/\s*\|$/, ''), light }
    } else if (vnode.type === TitlePage) {
      document.titlePage = plainProps(vnode.component.props)
    } else if (vnode.type === 'div' && vnode.el.id === 'toc-page') {
      document.meta.toc = true
    } else {
      content.push(vnode)
    }
  }

  document.blocks = contentList(content, ctx)
  document.title = [document.titlePage.title, document.titlePage.subtitle].filter(Boolean).join(' – ') || slug
  validate(document.blocks, null, 'blocks', ctx)

  return { document, warnings: ctx.warnings, errors: ctx.errors }
}

/** 'h-[15rem]' → '15rem', 'h-48' → '12rem', 'w-1/2' → '50%'; anything else is returned unchanged. */
export function toCssSize(value, prefix) {
  if (typeof value !== 'string' || !value.startsWith(prefix)) return value

  const rest = value.slice(prefix.length)
  const arbitrary = rest.match(/^\[(.+)]$/)
  if (arbitrary) return arbitrary[1]
  if (/^\d+(\.\d+)?$/.test(rest)) return `${Number(rest) / 4}rem`
  const fraction = rest.match(/^(\d+)\/(\d+)$/)
  if (fraction) return `${+((fraction[1] / fraction[2]) * 100).toFixed(4)}%`

  return value
}

class Context {
  warnings = []
  errors = []
  trail = []

  warn(message) {
    this.warnings.push(this.at(message))
  }

  error(message) {
    this.errors.push(this.at(message))
  }

  at(message) {
    return this.trail.length ? `${this.trail.join(' › ')}: ${message}` : message
  }
}

function contentList(vnodes, ctx) {
  const blocks = []
  let run = []

  // consecutive text/HTML nodes become one RichText block; surrounding whitespace is kept because it
  // matters next to inline components (e.g. text either side of <Breadcrumbs>)
  const flush = () => {
    const html = run.map(nodeHtml).join('')
    if (html.trim()) blocks.push(block('RichText', { html }))
    run = []
  }

  for (const vnode of flatten(vnodes)) {
    if (vnode.type === Text) {
      run.push(vnode.el)
    } else if (vnode.type === Static) {
      // compiler-stringified static HTML spans el…anchor
      for (let node = vnode.el; node; node = node.nextSibling) {
        run.push(node)
        if (node === vnode.anchor) break
      }
    } else if (typeof vnode.type === 'string') {
      if (containsBlocks(vnode)) {
        flush()
        ctx.warn(`unwrapped a <${vnode.type}> that contained components; its own markup was dropped`)
        blocks.push(...contentList(childrenOf(vnode), ctx))
      } else {
        run.push(vnode.el)
      }
    } else if (vnode.component) {
      flush()
      const type = typeOfComponent.get(vnode.type)
      blocks.push(...(type ? [toBlock(vnode, type, ctx)] : contentList([vnode.component.subTree], ctx)))
    }
  }

  flush()

  return blocks
}

function toBlock(vnode, type, ctx) {
  const definition = registry[type]
  const instance = vnode.component
  const props = {}
  const options = {}

  for (const name of definition.props) {
    const value = instance.props[name]
    // absent Boolean props resolve to false; leave them out
    if (value !== undefined && value !== null && value !== false) props[name] = plain(value)
  }

  if (definition.topic) {
    if (instance.props.id) options.anchorId = instance.props.id
    ctx.trail.push(instance.props.title ?? type)
  }

  readAttrs(type, instance.attrs, options, ctx)
  normaliseSizes(type, props, ctx)

  let children = []
  if (definition.slot === 'blocks') {
    // <Paragraph v-html="…" /> replaces the slot with innerHTML
    children =
      instance.attrs.innerHTML != null
        ? [block('RichText', { html: String(instance.attrs.innerHTML) })]
        : contentList(slotContent(instance.subTree), ctx)
  } else if (definition.slot === 'text') {
    props.text = instance.subTree.el.textContent.trim()
  } else if (type === 'SqlTable') {
    const widths = [...flatten(slotContent(instance.subTree))]
      .filter((child) => child.type === 'colgroup')
      .map((child) => toCssSize(child.el.className, 'w-'))
    if (widths.length) props.columnWidths = widths
  }

  if (definition.topic) ctx.trail.pop()

  return block(type, props, children, options)
}

function readAttrs(type, attrs, options, ctx) {
  for (const [name, value] of Object.entries(attrs)) {
    if (name === 'innerHTML') continue
    if (name === 'start') continue // numbering is automatic now

    if (name === 'class') {
      const classes = String(value).split(/\s+/).filter(Boolean)
      for (const cls of classes) {
        if (cls === 'new-page') {
          // PrimaryTopic always starts a new page already
          if (type !== 'PrimaryTopic') options.pageBreakBefore = true
        } else {
          ctx.warn(`dropped class "${cls}" on ${type}`)
        }
      }
      continue
    }

    ctx.warn(`dropped attribute ${name}="${value}" on ${type}`)
  }
}

function normaliseSizes(type, props, ctx) {
  if (props.height) {
    props.height = toCssSize(props.height, 'h-')
    if (props.height.startsWith('h-')) ctx.warn(`${type} height "${props.height}" is not a fixed size; kept as a class`)
  }
  if (props.widths) {
    props.widths = props.widths.map((width) => toCssSize(width, 'w-'))
  }
}

function validate(blocks, parentType, path, ctx) {
  blocks.forEach((node, index) => {
    if (!canContain(parentType, node.type)) {
      ctx.errors.push(
        `${path}.${index}: a ${node.type} cannot be placed in ${parentType ?? 'the top level of a document'}`
      )
    }
    validate(node.children, node.type, `${path}.${index}.children`, ctx)
  })
}

/** Children of the slot fragment Vue renders for <slot name="…">, without entering child components. */
function slotContent(subTree, name = 'default') {
  const queue = [subTree]

  while (queue.length) {
    const vnode = queue.shift()
    if (vnode.type === Fragment && vnode.key === `_${name}`) return childrenOf(vnode)
    if (vnode.component) continue
    queue.push(...childrenOf(vnode))
  }

  return []
}

function containsBlocks(vnode) {
  return childrenOf(vnode).some(
    (child) => (child.component && typeOfComponent.has(child.type)) || (!child.component && containsBlocks(child))
  )
}

function* flatten(vnodes) {
  for (const vnode of vnodes) {
    if (!vnode || typeof vnode !== 'object' || vnode.type === Comment) continue

    if (vnode.type === Fragment) {
      yield* flatten(childrenOf(vnode))
    } else {
      yield vnode
    }
  }
}

function childrenOf(vnode) {
  return Array.isArray(vnode?.children) ? vnode.children : []
}

function nodeHtml(node) {
  if (node.nodeType === 1) return node.outerHTML
  if (node.nodeType === 3) return escapeHtml(node.textContent)

  return ''
}

function escapeHtml(text) {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

function block(type, props = {}, children = [], options = {}) {
  return { type, props, options, children }
}

function plain(value) {
  return JSON.parse(JSON.stringify(toRaw(value)))
}

function plainProps(props) {
  return Object.fromEntries(Object.entries(plain(props)).filter(([, value]) => value != null))
}
