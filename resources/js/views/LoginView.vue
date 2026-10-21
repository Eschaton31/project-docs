<script setup>
import { errorMessage } from '@/builder/api.js'
import { useRoute, useRouter } from 'vue-router'

import { ref } from 'vue'

import { useAuthStore } from '@/stores/auth.js'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const form = ref({ email: '', password: '', remember: true })
const error = ref(null)
const busy = ref(false)

async function submit() {
  busy.value = true
  error.value = null
  try {
    await auth.login(form.value)
    await router.replace(route.query.redirect || '/')
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 p-6">
    <form
      class="w-full max-w-sm space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
      @submit.prevent="submit">
      <h1 class="text-lg font-bold text-bene-teal">Project Docs</h1>

      <label class="block space-y-1 text-sm text-slate-700">
        <span>Email</span>
        <input
          v-model="form.email"
          autocomplete="username"
          class="w-full rounded border border-slate-300 px-3 py-2"
          required
          type="email" />
      </label>
      <label class="block space-y-1 text-sm text-slate-700">
        <span>Password</span>
        <input
          v-model="form.password"
          autocomplete="current-password"
          class="w-full rounded border border-slate-300 px-3 py-2"
          required
          type="password" />
      </label>
      <label class="flex items-center gap-2 text-sm text-slate-600">
        <input v-model="form.remember" type="checkbox" />
        Keep me signed in
      </label>

      <p v-if="error" class="text-sm text-rose-700">{{ error }}</p>

      <button
        :disabled="busy"
        class="w-full rounded bg-bene-teal px-3 py-2 text-sm font-semibold text-white disabled:opacity-50"
        type="submit">
        {{ busy ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>
  </div>
</template>
