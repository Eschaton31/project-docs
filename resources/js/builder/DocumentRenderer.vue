<script setup>
import { computed } from 'vue'

import TitlePage from '@/components/TitlePage.vue'

import { BlockList } from './BlockList.js'

const props = defineProps({
  document: { type: Object, required: true },
  blocks: { type: Array, required: true },
  mode: { type: String, default: 'draft' },
})

const runningTitle = computed(() => {
  const { bold = '', light = '' } = props.document.meta?.runningTitle ?? {}

  return { bold: light ? `${bold} | ` : bold, light }
})
</script>

<template>
  <!-- same skeleton as the hand-built pages, so app.css (running title, TOC) and the Paged.js handlers apply -->
  <div class="flex h-full flex-col">
    <span class="title">
      <span class="text-[12pt] font-bold text-[#dcdcdc]">{{ runningTitle.bold }}</span>
      <span class="text-[12pt] font-medium text-[#a1a1a1]">{{ runningTitle.light }}</span>
    </span>
    <TitlePage v-bind="document.titlePage" />

    <div v-if="document.meta?.toc !== false" id="toc-page" class="toc-page flex h-full w-full flex-col gap-4">
      <div class="flex shrink-0 text-[14pt] font-bold text-bene-teal">TABLE OF CONTENTS</div>
    </div>

    <BlockList :mode :nodes="blocks" />
  </div>
</template>
