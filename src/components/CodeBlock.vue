<script setup>
import VCodeBlock from '@wdns/vue-code-block'

defineProps({
  code: String,
  label: String,
  lang: String,
})
</script>

<template>
  <VCodeBlock
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
