<script setup>
import VCodeBlock from '@wdns/vue-code-block'
import Prism from 'prismjs'
import { nextTick, onMounted, useTemplateRef, watch } from 'vue'

const props = defineProps({
  code: String,
  label: String,
  lang: String,
})

// with prism-plugin, vue-code-block only writes plain text and leaves highlighting to Prism; paginate()
// covers the preview/PDF, but the editor canvas never runs it, so each block highlights itself
const block = useTemplateRef('block')

function highlight() {
  const code = block.value?.$el?.querySelector?.('code')
  if (code) Prism.highlightElement(code)
}

// vue-code-block only fills in its text in its own onMounted, which renders on the next tick
onMounted(() => nextTick(highlight))
// post flush: after vue-code-block has re-rendered the new text, which also wipes the old highlighting
watch(() => [props.code, props.lang], highlight, { flush: 'post' })
</script>

<template>
  <VCodeBlock
    ref="block"
    :code
    :filename="label"
    :lang
    class="line-numbers"
    css-path="/prism-vs-dark-plus.css"
    prism-plugin
    prismjs
    theme="vs-dark-plus"
    width="80" />
</template>
