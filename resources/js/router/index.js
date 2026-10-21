import DataManagement from '@/DataManagement.vue'
import Handover from '@/Handover.vue'
import IscervUserManual from '@/IscervUserManual.vue'
import OhasisLinelists from '@/OhasisLinelists.vue'
import ServerMigration2026 from '@/ServerMigration2026.vue'
import { createRouter, createWebHistory } from 'vue-router'

import { useAuthStore } from '@/stores/auth.js'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // hand-built documents; meta.legacy lists them in the importer
    { path: '/handover', component: Handover, meta: { legacy: true } },
    { path: '/data-management', component: DataManagement, meta: { legacy: true } },
    { path: '/iscerv-user-manual', component: IscervUserManual, meta: { legacy: true } },
    { path: '/ohasis-linelists', component: OhasisLinelists, meta: { legacy: true } },
    { path: '/server-migration-2026', component: ServerMigration2026, meta: { legacy: true } },

    { path: '/', component: () => import('@/views/DocumentsView.vue'), meta: { auth: true, title: 'Documents' } },
    { path: '/edit/:slug', component: () => import('@/views/EditorView.vue'), meta: { auth: true } },
    { path: '/login', component: () => import('@/views/LoginView.vue'), meta: { title: 'Sign in' } },
    { path: '/import', component: () => import('@/views/ImportView.vue'), meta: { auth: true, title: 'Import' } },
    { path: '/render/:slug', component: () => import('@/views/RenderView.vue'), meta: { auth: true, signedSource: true } },
  ],
})

router.beforeEach(async (to) => {
  if (!to.meta.auth) return
  // headless Chrome (PDF export) has no session; the print view then reads a signed URL instead
  if (to.meta.signedSource && to.query.source) return

  const auth = useAuthStore()
  if (!auth.checked) await auth.fetchUser()
  if (!auth.user) return { path: '/login', query: { redirect: to.fullPath } }
})

router.afterEach((to) => {
  if (to.meta.title) document.title = `${to.meta.title} · Project Docs`
})

export default router
