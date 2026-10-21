<script setup>
import { api, errorMessage } from '@/builder/api.js'
import { useRouter } from 'vue-router'

import { onMounted, ref } from 'vue'

import { useAuthStore } from '@/stores/auth.js'

const auth = useAuthStore()
const router = useRouter()

const documents = ref(null)
const title = ref('')
const error = ref(null)
const creating = ref(false)
const importing = ref(false)

onMounted(async () => {
  try {
    documents.value = await api.documents()
  } catch (e) {
    error.value = errorMessage(e)
  }
})

async function create() {
  creating.value = true
  error.value = null
  try {
    const document = await api.createDocument(title.value.trim())
    await router.push(`/edit/${document.slug}`)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    creating.value = false
  }
}

/** A document exported as JSON (Export JSON, or a published version's JSON) becomes a new document. */
async function importFile(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return

  importing.value = true
  error.value = null
  try {
    const document = await api.importDocument(JSON.parse(await file.text()))
    await router.push(`/edit/${document.slug}`)
  } catch (e) {
    error.value = e instanceof SyntaxError ? `${file.name} is not valid JSON.` : errorMessage(e)
  } finally {
    importing.value = false
  }
}

async function logout() {
  await auth.logout()
  await router.push('/login')
}
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-8 p-8 text-sm text-slate-800">
    <header class="flex items-center justify-between">
      <h1 class="text-xl font-bold text-bene-teal">Documents</h1>
      <div class="flex items-center gap-4 text-slate-600">
        <label :class="['cursor-pointer underline', importing ? 'pointer-events-none opacity-50' : '']">
          {{ importing ? 'Importing…' : 'Import JSON…' }}
          <input accept="application/json,.json" class="hidden" type="file" @change="importFile" />
        </label>
        <span>{{ auth.user?.name }}</span>
        <button class="underline" type="button" @click="logout">Sign out</button>
      </div>
    </header>

    <form class="flex gap-2" @submit.prevent="create">
      <input v-model="title" class="field-input max-w-md" placeholder="Title of a new document" required type="text" />
      <button
        :disabled="creating || !title.trim()"
        class="rounded bg-bene-teal px-3 py-1.5 font-semibold text-white disabled:opacity-50"
        type="submit">
        {{ creating ? 'Creating…' : 'New document' }}
      </button>
    </form>

    <p v-if="error" class="text-rose-700">{{ error }}</p>
    <p v-if="documents && !documents.length" class="text-slate-500">
      No documents yet. Create one above or import a document's JSON.
    </p>

    <table v-if="documents?.length" class="w-full text-left">
      <thead class="text-xs uppercase text-slate-500">
        <tr>
          <th class="py-1">Title</th>
          <th class="py-1">Updated</th>
          <th class="py-1"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="doc in documents" :key="doc.id" class="border-t border-slate-100">
          <td class="py-2">
            <RouterLink :to="`/edit/${doc.slug}`" class="font-semibold hover:underline">{{ doc.title }}</RouterLink>
            <div class="text-xs text-slate-500">{{ doc.slug }}</div>
          </td>
          <td class="py-2 text-slate-500">{{ new Date(doc.updatedAt).toLocaleString() }}</td>
          <td class="space-x-3 py-2 text-right">
            <RouterLink :to="`/edit/${doc.slug}`" class="underline">Edit</RouterLink>
            <a :href="`/render/${doc.slug}`" class="underline" target="_blank">Print view</a>
            <a :href="`/api/documents/${doc.slug}/export`" class="underline">Export JSON</a>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
