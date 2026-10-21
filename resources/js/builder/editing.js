import { isSublist, makeSublist, sublistBold, sublistItems } from '@/components/lists/listEntries.js'

import { registry } from './registry.js'

/**
 * Editor-only knowledge about each block type: the property form and what a new block starts as.
 * Kept out of registry.js so the print view doesn't load it.
 *
 * Field types: text, textarea, html, richtext, code, boolean, select, size, image, list (indented lines →
 * nested arrays), lines (one string per line), grid (table rows; fixed columns, or any columns for SQL
 * results), concepts / tree (indented text formats below), json.
 * Structured fields (grid, concepts, tree) can also be edited as JSON from the form.
 */
const text = (name, label, extra = {}) => ({ name, label, type: 'text', ...extra })
const bool = (name, label) => ({ name, label, type: 'boolean' })
const title = text('title', 'Title')

/** Grid columns: kind 'lines' cells hold a string, or several lines as an array (rendered one per line). */
const column = (key, label, kind = 'text') => ({ key, label, kind })
const grid = (name, label, columns) => ({ name, label, type: 'grid', columns })

/** Icon types the FileTree component knows (lists/FileTree.vue). */
export const FILE_TREE_TYPES = [
  'folder',
  'file',
  'text',
  'config',
  'html',
  'image',
  'r',
  'exe',
  'terminal',
  'binary',
  'server',
  'schema',
  'table',
  'environment',
  'object',
  'array',
  'function',
]

export const CODE_LANGUAGES = [
  'r',
  'sql',
  'clickhouse',
  'ini',
  'yaml',
  'xml',
  'caddyfile',
  'bash',
  'json',
  'javascript',
]

export const blockEditing = {
  PrimaryTopic: { fields: [title], template: { props: { title: 'NEW TOPIC' } } },
  SecondaryTopic: { fields: [title], template: { props: { title: 'New section' } } },
  TertiaryTopic: { fields: [title], template: { props: { title: 'New subsection' } } },
  QuaternaryTopic: { fields: [title], template: { props: { title: 'New item' } } },

  Paragraph: { fields: [], template: { children: [{ type: 'RichText', props: { html: 'New paragraph.' } }] } },
  RichText: {
    fields: [{ name: 'html', label: 'Text', type: 'richtext' }],
    template: { props: { html: 'New text.' } },
  },
  TertiaryTitle: { fields: [text('text', 'Heading')], template: { props: { text: 'Heading' } } },
  Alert: {
    fields: [
      { name: 'type', label: 'Style', type: 'select', options: ['info', 'warn', 'success', 'error'] },
      text('title', 'Title'),
    ],
    template: {
      props: { type: 'info', title: 'Note' },
      children: [{ type: 'RichText', props: { html: 'Alert text.' } }],
    },
  },
  Placeholder: { fields: [text('text', 'What goes here')], template: { props: { text: 'to be written' } } },

  Image: {
    fields: [
      { name: 'src', label: 'Image', type: 'image' },
      text('description', 'Caption'),
      { name: 'height', label: 'Height', type: 'size', hint: 'CSS length, e.g. 15rem' },
    ],
    template: { props: { src: '', description: '', height: '15rem' } },
  },
  CodeBlock: {
    fields: [
      { name: 'lang', label: 'Language', type: 'text', suggestions: CODE_LANGUAGES },
      text('label', 'Label'),
      { name: 'code', label: 'Code', type: 'code', languageFrom: 'lang' },
    ],
    template: { props: { lang: 'sql', code: 'SELECT 1;' } },
  },
  Mermaid: {
    fields: [{ name: 'code', label: 'Mermaid', type: 'code', language: 'mermaid' }],
    template: { props: { code: 'flowchart LR\n  A --> B' } },
  },

  BulletList: {
    fields: [
      {
        name: 'list',
        label: 'Items',
        type: 'list',
        hint: 'One item per line; indent two spaces for a sub-list, and start its first line with ** to make it bold. HTML allowed.',
      },
      bool('bold', 'Bold (top-level items)'),
    ],
    template: { props: { list: ['First item', 'Second item'] } },
  },
  NumberedList: {
    fields: [
      {
        name: 'list',
        label: 'Items',
        type: 'list',
        hint: 'One item per line; indent two spaces for a sub-list, and start its first line with ** to make it bold. HTML allowed.',
      },
      bool('bold', 'Bold (top-level items)'),
    ],
    template: { props: { list: ['First step', 'Second step'] } },
  },
  ConceptList: {
    fields: [
      {
        name: 'list',
        label: 'Items',
        type: 'concepts',
        hint: 'One per line as “Term: definition”; indent two spaces to nest under the line above. HTML allowed.',
      },
      bool('emDash', 'Em dash instead of colon'),
      bool('highlight', 'Highlight terms as code'),
    ],
    template: { props: { list: [{ id: 'Term', value: 'Definition' }] } },
  },
  FileTree: {
    fields: [
      {
        name: 'list',
        label: 'Tree',
        type: 'tree',
        hint: `One entry per line as “type name | note”; indent two spaces for what's inside. Types: ${FILE_TREE_TYPES.join(', ')}.`,
      },
      text('description', 'Caption'),
    ],
    template: {
      props: { list: [{ type: 'folder', name: 'project', children: [{ type: 'file', name: 'README.md' }] }] },
    },
  },
  Breadcrumbs: {
    fields: [{ name: 'list', label: 'Steps', type: 'lines' }],
    template: { props: { list: ['Menu', 'Submenu'] } },
  },

  ItemDescriptionTable: {
    fields: [
      text('itemName', 'First column heading', { placeholder: 'Item' }),
      text('descriptionName', 'Second column heading', { placeholder: 'Description' }),
      grid('data', 'Rows', [column('item', 'Item'), column('description', 'Description', 'lines')]),
      bool('mono', 'Monospace first column'),
      bool('left', 'Left-align first column'),
      { name: 'widths', label: 'Column widths', type: 'lines', hint: 'One CSS width per line, e.g. 35%' },
    ],
    template: { props: { data: [{ item: 'Item', description: 'Description' }] } },
  },
  KeyValueTable: {
    fields: [grid('table', 'Rows', [column('id', 'Key'), column('value', 'Value', 'lines')])],
    template: { props: { table: [{ id: 'Key', value: 'Value' }] } },
  },
  ConversionDictionary: {
    fields: [
      grid('data', 'Rows', [
        column('item', 'Variable'),
        column('description', 'Description', 'lines'),
        column('formats', 'Formats', 'lines'),
      ]),
      bool('mono', 'Monospace first column'),
      { name: 'widths', label: 'Column widths', type: 'lines', hint: 'One CSS width per line' },
    ],
    template: { props: { data: [{ item: 'variable', description: 'Description', formats: 'Format' }] } },
  },
  DataDictionary: {
    fields: [
      grid('data', 'Rows', [
        column('field', 'Field'),
        column('description', 'Description'),
        column('type', 'Data type'),
        column('values', 'Possible values', 'lines'),
      ]),
    ],
    template: { props: { data: [{ field: 'field', description: 'Description', type: 'VARCHAR', values: '' }] } },
  },
  SqlTable: {
    fields: [
      {
        name: 'data',
        label: 'Result rows',
        type: 'grid',
        hint: 'Paste a result from your SQL client; an empty cell is NULL.',
      },
      text('description', 'Caption'),
      bool('dark', 'Dark theme'),
      { name: 'columnWidths', label: 'Column widths', type: 'lines', hint: 'One CSS width per line' },
    ],
    template: { props: { data: [{ id: 1, name: 'example' }] } },
  },
  TechStack: {
    fields: [
      grid('data', 'Rows', [
        column('component', 'Component'),
        column('tech', 'Technology', 'lines'),
        column('framework', 'Framework', 'lines'),
      ]),
    ],
    template: { props: { data: [{ component: 'Component', tech: 'Technology', framework: 'Framework' }] } },
  },
}

export const OPTION_FIELDS = [
  { name: 'pageBreakBefore', label: 'Start on a new page', type: 'boolean' },
  { name: 'draft', label: 'Draft (left out of the final version)', type: 'boolean' },
  { name: 'hidden', label: 'Hidden', type: 'boolean' },
  { name: 'anchorId', label: 'Anchor id (for links)', type: 'text' },
  { name: 'status', label: 'Status', type: 'select', options: ['', 'todo', 'in_progress', 'review', 'done'] },
]

export const TITLE_PAGE_FIELDS = [
  text('title', 'Title'),
  text('subtitle', 'Subtitle'),
  text('client', 'Client'),
  text('project', 'Project'),
  text('version', 'Version'),
  text('dateSubmitted', 'Date submitted'),
  text('submittedBy', 'Submitted by'),
  text('submittedTo', 'Submitted to'),
]

export const TOPIC_LADDER = ['PrimaryTopic', 'SecondaryTopic', 'TertiaryTopic', 'QuaternaryTopic']

/** A new block of the given type, as nodes for the insert-tree endpoint. */
export function newBlock(type) {
  const template = structuredClone(blockEditing[type]?.template ?? {})

  return { type, props: template.props ?? {}, options: {}, children: template.children ?? [] }
}

/** Short label for the outline. */
export function blockLabel(node) {
  const props = node.props ?? {}
  const label = registry[node.type]?.label ?? node.type
  const detail = props.title ?? props.text ?? props.description ?? props.label ?? stripHtml(props.html ?? '')

  return { label, detail: detail ? truncate(detail, 70) : '' }
}

/** Copy of a block subtree without ids/versions, ready to insert again (duplicate, snippets). */
export function toTemplate(node) {
  return {
    type: node.type,
    props: toPlain(node.props ?? {}),
    options: toPlain(node.options ?? {}),
    children: (node.children ?? []).map(toTemplate),
  }
}

/**
 * Indented headings → topic nodes. The first indentation level becomes topicType; deeper levels step
 * down the topic ladder (two spaces or one tab per level).
 */
export function outlineToNodes(text, topicType) {
  const start = TOPIC_LADDER.indexOf(topicType)
  const last = TOPIC_LADDER.length - 1
  // past QuaternaryTopic, deeper lines stay quaternary siblings
  const convert = (nodes, level) =>
    nodes.flatMap(({ text: line, children }) => {
      const node = { type: TOPIC_LADDER[level], props: { title: line }, options: {}, children: [] }
      if (level === last) return [node, ...convert(children, level)]
      node.children = convert(children, level + 1)

      return [node]
    })

  return convert(parseIndented(text), start)
}

/** A bold sub-list's first line starts with this. */
const BOLD_SUBLIST = '** '

export function listToText(list, depth = 0, bold = false) {
  return (list ?? [])
    .map((item, index) => {
      if (isSublist(item)) return listToText(sublistItems(item), depth + 1, sublistBold(item))

      return `${'  '.repeat(depth)}${bold && index === 0 ? BOLD_SUBLIST : ''}${item}`
    })
    .join('\n')
}

/**
 * Inverse of listToText: indentation becomes nested arrays, which the list components render as
 * sub-lists; one whose first line starts with “** ” is bold.
 */
export function textToList(text) {
  const root = []
  const stack = [{ depth: 0, items: root }]

  for (const line of text.split(/\r?\n/)) {
    if (!line.trim()) continue

    let item = line.trim()
    const depth = Math.floor(line.match(/^[\t ]*/)[0].replace(/\t/g, '  ').length / 2)
    while (depth < stack[stack.length - 1].depth) stack.pop()
    if (depth > stack[stack.length - 1].depth) {
      const bold = item.startsWith(BOLD_SUBLIST)
      if (bold) item = item.slice(BOLD_SUBLIST.length)
      const nested = []
      stack[stack.length - 1].items.push(makeSublist(nested, bold))
      stack.push({ depth, items: nested })
    }
    stack[stack.length - 1].items.push(item)
  }

  return root
}

/**
 * Indented lines → [{ text, children }]: two spaces or a tab per level, and a line can't skip a level.
 */
export function parseIndented(text) {
  const roots = []
  const stack = []

  for (const raw of text.split(/\r?\n/)) {
    if (!raw.trim()) continue

    const indent = raw.match(/^[\t ]*/)[0].replace(/\t/g, '  ').length
    const depth = Math.min(Math.floor(indent / 2), stack.length)
    const node = { text: raw.trim(), children: [] }

    stack.length = depth
    ;(depth === 0 ? roots : stack[depth - 1].children).push(node)
    stack.push(node)
  }

  return roots
}

function indentedText(nodes, line, depth = 0) {
  return nodes
    .flatMap((node) => [
      `${'  '.repeat(depth)}${line(node)}`,
      ...(node.children?.length ? [indentedText(node.children, line, depth + 1)] : []),
    ])
    .join('\n')
}

/** ConceptList items ↔ “Term: definition” lines (the first “: ” splits). */
export function conceptsToText(list) {
  return indentedText(list ?? [], (item) => (item.value ? `${item.id}: ${item.value}` : item.id))
}

export function textToConcepts(text) {
  const convert = (nodes) =>
    nodes.map(({ text: line, children }) => {
      const at = line.indexOf(': ')
      const item = at === -1 ? { id: line, value: '' } : { id: line.slice(0, at), value: line.slice(at + 2) }

      return children.length ? { ...item, children: convert(children) } : item
    })

  return convert(parseIndented(text))
}

/** FileTree entries ↔ “type name | note” lines (type optional). */
export function treeToText(list) {
  return indentedText(
    list ?? [],
    (entry) => [entry.type, entry.name].filter(Boolean).join(' ') + (entry.note ? ` | ${entry.note}` : '')
  )
}

export function textToTree(text) {
  const convert = (nodes) =>
    nodes.map(({ text: line, children }) => {
      const [main, ...note] = line.split(' | ')
      const [first, ...rest] = main.split(' ')
      const typed = FILE_TREE_TYPES.includes(first) && rest.length > 0
      const entry = typed ? { type: first, name: rest.join(' ') } : { name: main }
      if (note.length) entry.note = note.join(' | ')

      return children.length ? { ...entry, children: convert(children) } : entry
    })

  return convert(parseIndented(text))
}

/**
 * Rows pasted from a spreadsheet or SQL client: tab-separated if any line has a tab, otherwise CSV
 * (with "quoted, fields" and "" escapes).
 *
 * @returns {string[][]}
 */
export function parseDelimited(text) {
  const input = text.replace(/\r\n?/g, '\n').replace(/\n+$/, '')
  if (!input.trim()) return []
  if (input.includes('\t')) return input.split('\n').map((line) => line.split('\t'))

  const rows = [[]]
  let field = ''
  let quoted = false
  for (let i = 0; i < input.length; i++) {
    const char = input[i]
    if (quoted) {
      if (char === '"' && input[i + 1] === '"') {
        field += '"'
        i++
      } else if (char === '"') {
        quoted = false
      } else {
        field += char
      }
    } else if (char === '"' && field === '') {
      quoted = true
    } else if (char === ',') {
      rows.at(-1).push(field)
      field = ''
    } else if (char === '\n') {
      rows.at(-1).push(field)
      rows.push([])
      field = ''
    } else {
      field += char
    }
  }
  rows.at(-1).push(field)

  return rows
}

function stripHtml(html) {
  return html
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

function truncate(value, length) {
  const text = String(value)

  return text.length > length ? `${text.slice(0, length - 1)}…` : text
}

function toPlain(value) {
  return JSON.parse(JSON.stringify(value))
}
