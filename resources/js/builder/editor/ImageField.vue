<script setup>
import { ref } from 'vue'

import ImagePicker from './ImagePicker.vue'

/** Image path with a picker (existing images under public/img, or upload). */
defineProps({
  modelValue: { type: String, default: '' },
  readonly: Boolean,
  id: String,
})
const emit = defineEmits(['update:modelValue'])

const picking = ref(false)

function choose(path) {
  emit('update:modelValue', path)
  picking.value = false
}
</script>

<template>
  <div class="space-y-1.5">
    <div class="flex gap-1.5">
      <input
        :id
        :readonly
        :value="modelValue"
        class="field-input"
        placeholder="/img/…"
        type="text"
        @input="emit('update:modelValue', $event.target.value)" />
      <button :disabled="readonly" class="toolbar-button shrink-0" type="button" @click="picking = true">
        Choose…
      </button>
    </div>
    <img
      v-if="modelValue"
      :src="modelValue"
      alt=""
      class="max-h-32 rounded border border-slate-200 bg-slate-50 object-contain" />
    <ImagePicker v-if="picking" :current="modelValue" @close="picking = false" @select="choose" />
  </div>
</template>
