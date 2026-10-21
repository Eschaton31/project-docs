import { api, csrf, http } from '@/builder/api.js'
import { defineStore } from 'pinia'

import { ref } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const checked = ref(false)

  async function fetchUser() {
    try {
      user.value = await api.user()
    } catch (error) {
      if (error.response?.status !== 401) throw error
      user.value = null
    }
    checked.value = true

    return user.value
  }

  async function login(credentials) {
    await csrf()
    const { data } = await http.post('/login', credentials)
    user.value = data.user
    checked.value = true
  }

  async function logout() {
    await http.post('/logout')
    user.value = null
  }

  return { user, checked, fetchUser, login, logout }
})
