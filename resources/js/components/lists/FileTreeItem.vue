<script setup>
import {
  AppWindowIcon,
  DatabaseIcon,
  FileCodeCornerIcon,
  FileImageIcon,
  FilesIcon,
  FolderIcon,
  FolderOpenIcon,
  ServerIcon,
  SheetIcon,
  SquareTerminalIcon,
} from 'lucide-vue-next'

import { computed } from 'vue'

const { type } = defineProps({
  type: String,
  name: String,
  note: String,
  children: Array,
})

const components = {
  exe: AppWindowIcon,
  file: FilesIcon,
  folder: FolderIcon,
  folderOpen: FolderOpenIcon,
  html: FileCodeCornerIcon,
  image: FileImageIcon,
  schema: DatabaseIcon,
  server: ServerIcon,
  table: SheetIcon,
  terminal: SquareTerminalIcon,
}

const fill = computed(() => {
  if (type === 'folder') return '#f2c342'

  return '#ffffff'
})
const outline = computed(() => {
  if (type === 'server') return 'text-[#00b200]'
  if (type === 'schema') return 'text-[#b489eb]'
  if (type === 'table') return 'text-[#4973c7]'

  return 'text-bene-gray'
})
</script>

<template>
  <li>
    <div class="flex items-center gap-2">
      <component
        :is="components[type === 'folder' && children?.length > 0 ? 'folderOpen' : (type ?? 'file')]"
        :class="['inline-block size-4', outline]"
        :fill />
      {{ name }}
      <span v-if="note">{{ note }}</span>
    </div>
    <template v-if="children?.length > 0">
      <ul class="list-disc">
        <FileTreeItem v-for="{ type, name, children, note } in children" :key="name" :children :name :note :type />
      </ul>
    </template>
  </li>
</template>
