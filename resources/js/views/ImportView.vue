<script setup>
import { api, errorMessage } from '@/builder/api.js'
import { extractDocument } from '@/builder/importer/extract.js'
import { useRouter } from 'vue-router'

import { markRaw, nextTick, onMounted, reactive, ref, shallowRef } from 'vue'

import { useAuthStore } from '@/stores/auth.js'

/**
 * Converts the hand-built pages into stored documents. Each page is mounted off-screen, its rendered
 * component tree is read (see importer/extract.js), and the result can be checked before importing.
 */
const auth = useAuthStore()
const router = useRouter()

const pages = reactive(
  router
    .getRoutes()
    .filter((route) => route.meta.legacy)
    .map((route) => ({ slug: route.path.slice(1), path: route.path, component: markRaw(route.components.default) }))
    .map((page) => ({ ...page, state: 'idle', result: null, imported: null, error: null, showDetails: false }))
)

const documents = ref([])
const mounting = shallowRef(null)
const legacy = ref(null)

onMounted(loadDocuments)

async function loadDocuments() {
  documents.value = await api.documents()
}

async function convert(page) {
  page.state = 'converting'
  page.error = null
  const title = document.title

  try {
    mounting.value = page.component
    await nextTick()
    await nextTick()
    page.result = extractDocument(legacy.value.$, { slug: page.slug })
    page.state = 'converted'
  } catch (e) {
    page.error = e.message
    page.state = 'idle'
  } finally {
    mounting.value = null
    // the hand-built pages carry a <title> for the print file name
    document.title = title
  }
}

async function importPage(page) {
  page.state = 'importing'
  page.error = null
  try {
    page.imported = await api.importDocument(page.result.document)
    page.state = 'imported'
    await loadDocuments()
  } catch (e) {
    page.error = errorMessage(e)
    page.state = 'converted'
  }
}

function download(page) {
  const blob = new Blob([JSON.stringify(page.result.document, null, 2)], { type: 'application/json' })
  const link = Object.assign(document.createElement('a'), {
    href: URL.createObjectURL(blob),
    download: `${page.slug}.json`,
  })
  link.click()
  URL.revokeObjectURL(link.href)
}

function countBlocks(blocks) {
  return blocks.reduce((count, block) => count + 1 + countBlocks(block.children), 0)
}

async function logout() {
  await auth.logout()
  await router.push('/login')
}
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-8 p-8 text-sm text-slate-800">
    <header class="flex items-center justify-between">
      <h1 class="text-xl font-bold text-bene-teal">Import hand-built documents</h1>
      <div class="flex items-center gap-3 text-slate-600">
        <RouterLink class="underline" to="/">Documents</RouterLink>
        <span>{{ auth.user?.name }}</span>
        <button class="underline" type="button" @click="logout">Sign out</button>
      </div>
    </header>

    <section class="space-y-3">
      <p class="text-slate-600">
        Converting reads the page as it renders today, including data from loops and section files. Check the result in
        the print view against the original before relying on it; importing again creates a separate copy.
      </p>

      <div v-for="page in pages" :key="page.slug" class="space-y-3 rounded-lg border border-slate-200 p-4">
        <div class="flex flex-wrap items-center gap-3">
          <div class="grow">
            <div class="font-semibold">{{ page.result?.document.title ?? page.slug }}</div>
            <a :href="page.path" class="text-xs text-slate-500 underline" target="_blank">{{ page.path }}</a>
          </div>

          <button
            :disabled="page.state === 'converting' || page.state === 'importing'"
            class="rounded border border-slate-300 px-3 py-1.5 disabled:opacity-50"
            type="button"
            @click="convert(page)">
            {{ page.state === 'converting' ? 'Converting…' : page.result ? 'Convert again' : 'Convert' }}
          </button>
          <template v-if="page.result">
            <button class="rounded border border-slate-300 px-3 py-1.5" type="button" @click="download(page)">
              Download JSON
            </button>
            <button
              :disabled="page.result.errors.length > 0 || page.state === 'importing'"
              class="rounded bg-bene-teal px-3 py-1.5 font-semibold text-white disabled:opacity-50"
              type="button"
              @click="importPage(page)">
              {{ page.state === 'importing' ? 'Importing…' : 'Import' }}
            </button>
          </template>
        </div>

        <div v-if="page.result" class="space-y-2">
          <div class="text-slate-600">
            {{ countBlocks(page.result.document.blocks) }} blocks · {{ page.result.warnings.length }} warnings ·
            <span :class="page.result.errors.length ? 'font-semibold text-rose-700' : ''">
              {{ page.result.errors.length }} errors
            </span>
            <button
              v-if="page.result.warnings.length || page.result.errors.length"
              class="ml-2 underline"
              type="button"
              @click="page.showDetails = !page.showDetails">
              {{ page.showDetails ? 'hide' : 'show' }} details
            </button>
          </div>
          <ul v-if="page.showDetails" class="list-disc space-y-1 pl-6 text-xs">
            <li v-for="message in page.result.errors" :key="message" class="text-rose-700">{{ message }}</li>
            <li v-for="message in page.result.warnings" :key="message" class="text-amber-700">{{ message }}</li>
          </ul>
        </div>

        <p v-if="page.error" class="text-rose-700">{{ page.error }}</p>
        <p v-if="page.imported" class="text-green-700">
          Imported as
          <a :href="`/render/${page.imported.slug}`" class="font-semibold underline" target="_blank">
            {{ page.imported.slug }}
          </a>
          — open it next to <a :href="page.path" class="underline" target="_blank">the original</a> to compare.
        </p>
      </div>
    </section>

    <section class="space-y-3">
      <h2 class="font-semibold">Documents in the database</h2>
      <p v-if="!documents.length" class="text-slate-500">None yet.</p>
      <table v-else class="w-full text-left">
        <thead class="text-xs uppercase text-slate-500">
          <tr>
            <th class="py-1">Title</th>
            <th class="py-1">Slug</th>
            <th class="py-1">Updated</th>
            <th class="py-1"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="doc in documents" :key="doc.id" class="border-t border-slate-100">
            <td class="py-1.5">{{ doc.title }}</td>
            <td class="py-1.5 text-slate-500">{{ doc.slug }}</td>
            <td class="py-1.5 text-slate-500">{{ new Date(doc.updatedAt).toLocaleString() }}</td>
            <td class="space-x-3 py-1.5 text-right">
              <a :href="`/render/${doc.slug}`" class="underline" target="_blank">Print view</a>
              <a :href="`/render/${doc.slug}?mode=final`" class="underline" target="_blank">Final</a>
              <a :href="`/api/documents/${doc.slug}/export`" class="underline">Export JSON</a>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- hand-built pages are mounted here while converting; off-screen but laid out at A4 width -->
    <div aria-hidden="true" class="pointer-events-none fixed left-[-10000px] top-0 w-[8.27in]">
      <component :is="mounting" v-if="mounting" ref="legacy" />
    </div>
  </div>
</template>
