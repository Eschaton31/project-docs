<script setup>
import { defineAsyncComponent, ref, useId } from 'vue'

import FormattedTextField from './FormattedTextField.vue'
import GridField from './GridField.vue'
import ImageField from './ImageField.vue'
import JsonField from './JsonField.vue'
import RichTextField from './RichTextField.vue'

// CodeMirror is large; load it when a code field is first shown
const CodeField = defineAsyncComponent(() => import('./CodeField.vue'))

/** One form field from the editing schema (see editing.js for the field types). */
const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { default: undefined },
  // the block's other props (e.g. a code field reads its language from 'lang')
  context: { type: Object, default: () => ({}) },
  // someone else is editing the block; native inputs are also disabled by the surrounding fieldset
  readonly: Boolean,
})
const emit = defineEmits(['update:modelValue'])

const id = useId()
const update = (value) => emit('update:modelValue', value)

// structured fields can drop down to JSON for anything their editor doesn't cover
const STRUCTURED = ['grid', 'concepts', 'tree', 'list']
const asJson = ref(false)
</script>

<template>
  <div class="space-y-1">
    <label v-if="field.type === 'boolean'" class="flex items-center gap-2 text-xs text-slate-700">
      <input :checked="!!modelValue" type="checkbox" @change="update($event.target.checked)" />
      {{ field.label }}
    </label>
    <div v-else class="flex items-baseline justify-between">
      <label :for="id" class="block text-xs font-semibold text-slate-600">{{ field.label }}</label>
      <button
        v-if="STRUCTURED.includes(field.type)"
        class="text-[11px] text-slate-500 underline"
        type="button"
        @click="asJson = !asJson">
        {{ asJson ? 'Back to editor' : 'Edit as JSON' }}
      </button>
    </div>

    <JsonField v-if="asJson" :id :model-value="modelValue" @update:model-value="update" />
    <template v-else-if="field.type === 'text' || field.type === 'size'">
      <input
        :id
        :list="field.suggestions ? `${id}-suggestions` : undefined"
        :placeholder="field.placeholder"
        :value="modelValue ?? ''"
        class="field-input"
        type="text"
        @input="update($event.target.value)" />
      <datalist v-if="field.suggestions" :id="`${id}-suggestions`">
        <option v-for="suggestion in field.suggestions" :key="suggestion" :value="suggestion" />
      </datalist>
    </template>
    <textarea
      v-else-if="field.type === 'html' || field.type === 'textarea'"
      :id
      :class="['field-input', field.type === 'textarea' ? 'min-h-20' : 'min-h-40 font-mono text-xs']"
      :value="modelValue ?? ''"
      @input="update($event.target.value)" />
    <RichTextField
      v-else-if="field.type === 'richtext'"
      :id
      :model-value="modelValue"
      :readonly
      @update:model-value="update" />
    <CodeField
      v-else-if="field.type === 'code'"
      :language="field.language ?? props.context?.[field.languageFrom]"
      :model-value="modelValue"
      :readonly
      @update:model-value="update" />
    <select
      v-else-if="field.type === 'select'"
      :id
      :value="modelValue ?? ''"
      class="field-input"
      @change="update($event.target.value)">
      <option v-for="option in field.options" :key="option" :value="option">{{ option || '—' }}</option>
    </select>
    <FormattedTextField
      v-else-if="['list', 'lines', 'concepts', 'tree'].includes(field.type)"
      :id
      :format="field.type"
      :model-value="modelValue"
      :readonly
      @update:model-value="update" />
    <GridField
      v-else-if="field.type === 'grid'"
      :columns="field.columns ?? null"
      :model-value="modelValue"
      :readonly
      @update:model-value="update" />
    <ImageField
      v-else-if="field.type === 'image'"
      :id
      :model-value="modelValue"
      :readonly
      @update:model-value="update" />
    <JsonField v-else-if="field.type === 'json'" :id :model-value="modelValue" @update:model-value="update" />

    <p v-if="field.hint" class="text-[11px] text-slate-500">{{ field.hint }}</p>
  </div>
</template>
