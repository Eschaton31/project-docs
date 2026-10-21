<script setup>
import { faHtml5, faRProject } from '@fortawesome/free-brands-svg-icons'
import {
  faDatabase,
  faFile,
  faFileCode,
  faFileImage,
  faFileLines,
  faFolder,
  faFolderOpen,
  faServer,
  faTable,
  faTerminal,
  faWindowMaximize,
} from '@fortawesome/free-solid-svg-icons'
import { faDiagramProject } from '@fortawesome/pro-regular-svg-icons'
import { faBinary, faBracketsCurly, faBracketsSquare, faFunction } from '@fortawesome/pro-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

defineProps({
  list: Array,
  description: String,
  // position of this (sub-)tree within the top-level list, for editing entries in place
  path: { type: Array, default: () => [] },
})

const components = {
  array: faBracketsSquare,
  binary: faBinary,
  config: faFileCode,
  environment: faDiagramProject,
  exe: faWindowMaximize,
  file: faFile,
  folder: faFolder,
  folderOpen: faFolderOpen,
  function: faFunction,
  html: faHtml5,
  image: faFileImage,
  object: faBracketsCurly,
  r: faRProject,
  schema: faDatabase,
  server: faServer,
  table: faTable,
  terminal: faTerminal,
  text: faFileLines,
}

const outline = (type) => {
  if (type === 'folder') return 'text-[#f2c342]'
  if (type === 'server') return 'text-[#00b200]'
  if (type === 'schema') return 'text-[#b489eb]'
  if (type === 'table') return 'text-[#4973c7]'
  if (type === 'r') return 'text-[#2385a8]'
  if (type === 'environment') return 'text-[#d6ae58]'
  if (type === 'object') return 'text-[#c77dba]'
  if (type === 'array') return 'text-[#4973c7]'
  if (type === 'function') return 'text-[#db5c5c]'

  return 'text-bene-gray'
}
</script>

<template>
  <div class="space-y-1 text-center">
    <div class="rounded-md bg-gray-100 pt-2">
      <ul class="tree px-2.5">
        <li v-for="({ type, name, children, note }, index) in list" :key="index">
          <div class="flex items-center gap-2">
            <FontAwesomeIcon
              :class="['inline-block size-4', outline(type)]"
              :icon="components[type === 'folder' && children?.length > 0 ? 'folderOpen' : (type ?? 'file')]" />
            <slot name="name" :path="[...path, index]" :value="name">{{ name }}</slot>
            <span v-if="note"
              ><slot name="note" :path="[...path, index]" :value="note">{{ note }}</slot></span
            >
          </div>
          <template v-if="children?.length > 0">
            <FileTree :list="children" :path="[...path, index]">
              <template v-if="$slots.name" #name="scope"><slot name="name" v-bind="scope" /></template>
              <template v-if="$slots.note" #note="scope"><slot name="note" v-bind="scope" /></template>
            </FileTree>
          </template>
        </li>
      </ul>
    </div>
    <div class="italic">{{ description }}</div>
  </div>
</template>
