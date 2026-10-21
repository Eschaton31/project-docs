<script setup>
defineProps({
  list: Array,
  emDash: Boolean,
  highlight: Boolean,
})
</script>

<template>
  <ul class="list-outside list-disc space-y-1.5 pl-6" role="list">
    <template v-for="{ id, value, children } in list" :key="id">
      <li class="justify-start pl-2 text-bene-gray">
        <component :is="highlight ? 'codetext' : 'span'" class="font-bold" v-html="id" />
        <span class="pl-1" v-html="(emDash ? '— ' : ': ') + value" />
        <template v-if="children">
          <ConceptList :emDash :highlight :list="children" class="mt-1" />
        </template>
      </li>
    </template>
  </ul>
</template>

<style scoped></style>
