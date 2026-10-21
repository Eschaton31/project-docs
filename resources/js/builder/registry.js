import ConversionDictionary from '@/components/tables/ConversionDictionary.vue'
import DataDictionary from '@/components/tables/DataDictionary.vue'
import ItemDescriptionTable from '@/components/tables/ItemDescriptionTable.vue'
import KeyValueTable from '@/components/tables/KeyValueTable.vue'
import SqlTable from '@/components/tables/SqlTable.vue'
import TechStack from '@/components/tables/TechStack.vue'

import Alert from '@/components/Alert.vue'
import CodeBlock from '@/components/CodeBlock.vue'
import Image from '@/components/Image.vue'
import Mermaid from '@/components/Mermaid.vue'
import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import QuaternaryTopic from '@/components/QuaternaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTitle from '@/components/TertiaryTitle.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'
import Breadcrumbs from '@/components/lists/Breadcrumbs.vue'
import BulletList from '@/components/lists/BulletList.vue'
import ConceptList from '@/components/lists/ConceptList.vue'
import FileTree from '@/components/lists/FileTree.vue'
import NumberedList from '@/components/lists/NumberedList.vue'

import blockTypes from './block-types.json'
import Placeholder from './blocks/Placeholder.vue'
import RichText from './blocks/RichText.vue'

/**
 * Block type → component and the props it reads from the stored JSON.
 *
 * slot: 'blocks' renders the block's children into the default slot; 'text' renders props.text.
 * Nesting rules live in block-types.json, which the Laravel API enforces too.
 */
export const registry = {
  PrimaryTopic: {
    component: PrimaryTopic,
    label: 'Primary Topic',
    category: 'Structure',
    props: ['title'],
    slot: 'blocks',
    topic: true,
  },
  SecondaryTopic: {
    component: SecondaryTopic,
    label: 'Secondary Topic',
    category: 'Structure',
    props: ['title'],
    slot: 'blocks',
    topic: true,
  },
  TertiaryTopic: {
    component: TertiaryTopic,
    label: 'Tertiary Topic',
    category: 'Structure',
    props: ['title'],
    slot: 'blocks',
    topic: true,
  },
  QuaternaryTopic: {
    component: QuaternaryTopic,
    label: 'Quaternary Topic',
    category: 'Structure',
    props: ['title'],
    slot: 'blocks',
    topic: true,
  },

  Paragraph: { component: Paragraph, label: 'Paragraph', category: 'Text', props: [], slot: 'blocks' },
  RichText: { component: RichText, label: 'Text', category: 'Text', props: ['html'] },
  TertiaryTitle: {
    component: TertiaryTitle,
    label: 'Heading (inline)',
    category: 'Text',
    props: ['text'],
    slot: 'text',
  },
  Alert: { component: Alert, label: 'Alert', category: 'Text', props: ['type', 'title'], slot: 'blocks' },
  Placeholder: { component: Placeholder, label: 'Placeholder', category: 'Text', props: ['text'] },

  Image: { component: Image, label: 'Image', category: 'Media', props: ['src', 'description', 'height'] },
  CodeBlock: { component: CodeBlock, label: 'Code', category: 'Media', props: ['code', 'label', 'lang'] },
  Mermaid: { component: Mermaid, label: 'Diagram (Mermaid)', category: 'Media', props: ['code'] },

  BulletList: { component: BulletList, label: 'Bullet List', category: 'Lists', props: ['list'] },
  NumberedList: { component: NumberedList, label: 'Numbered List', category: 'Lists', props: ['list'] },
  ConceptList: {
    component: ConceptList,
    label: 'Concept List',
    category: 'Lists',
    props: ['list', 'emDash', 'highlight'],
  },
  FileTree: { component: FileTree, label: 'File Tree', category: 'Lists', props: ['list', 'description'] },
  Breadcrumbs: { component: Breadcrumbs, label: 'Breadcrumbs', category: 'Lists', props: ['list'] },

  ItemDescriptionTable: {
    component: ItemDescriptionTable,
    label: 'Item / Description Table',
    category: 'Tables',
    props: ['data', 'itemName', 'descriptionName', 'mono', 'left', 'widths'],
  },
  KeyValueTable: { component: KeyValueTable, label: 'Key / Value Table', category: 'Tables', props: ['table'] },
  ConversionDictionary: {
    component: ConversionDictionary,
    label: 'Conversion Dictionary',
    category: 'Tables',
    props: ['data', 'mono', 'widths'],
  },
  DataDictionary: { component: DataDictionary, label: 'Data Dictionary', category: 'Tables', props: ['data'] },
  SqlTable: {
    component: SqlTable,
    label: 'SQL Result',
    category: 'Tables',
    props: ['data', 'description', 'dark', 'columnWidths'],
  },
  TechStack: { component: TechStack, label: 'Tech Stack', category: 'Tables', props: ['data'] },
}

/** Types allowed directly under parentType (null = document root), expanding @groups. */
export function allowedChildren(parentType) {
  const entries = parentType === null ? blockTypes.root : (blockTypes.types[parentType]?.children ?? [])

  return [...new Set(entries.flatMap((entry) => (entry.startsWith('@') ? blockTypes.groups[entry.slice(1)] : [entry])))]
}

export function canContain(parentType, childType) {
  return allowedChildren(parentType).includes(childType)
}
