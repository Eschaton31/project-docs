import { createRouter, createWebHistory } from 'vue-router'

import { useAuthStore } from '@/stores/auth.js'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', component: () => import('@/views/DocumentsView.vue'), meta: { auth: true, title: 'Documents' } },
    { path: '/edit/:slug', component: () => import('@/views/EditorView.vue'), meta: { auth: true } },
    { path: '/login', component: () => import('@/views/LoginView.vue'), meta: { title: 'Sign in' } },
    {
      path: '/render/:slug',
      component: () => import('@/views/RenderView.vue'),
      meta: { auth: true, signedSource: true },
    },
    // old links (e.g. to the hand-built pages that used to live here) go to the document list
    { path: '/:pathMatch(.*)*', redirect: '/' },
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
