<script setup>
defineProps({
  list: Array,
  emDash: Boolean,
  highlight: Boolean,
  // position of this (sub-)list within the top-level list, for editing items in place
  path: { type: Array, default: () => [] },
})
</script>

<template>
  <ul class="list-outside list-disc space-y-1.5 pl-6" role="list">
    <template v-for="({ id, value, children }, index) in list" :key="index">
      <li class="justify-start pl-2 text-bene-gray">
        <component :is="highlight ? 'codetext' : 'span'" class="font-bold">
          <slot name="term" :path="[...path, index]" :value="id"><span v-html="id" /></slot>
        </component>
        <span class="pl-1"
          >{{ emDash ? '— ' : ': '
          }}<slot name="definition" :path="[...path, index]" :value="value"><span v-html="value" /></slot
        ></span>
        <template v-if="children">
          <ConceptList :emDash :highlight :list="children" :path="[...path, index]" class="mt-1">
            <template v-if="$slots.term" #term="scope"><slot name="term" v-bind="scope" /></template>
            <template v-if="$slots.definition" #definition="scope"><slot name="definition" v-bind="scope" /></template>
          </ConceptList>
        </template>
      </li>
    </template>
  </ul>
</template>

<style scoped></style>
