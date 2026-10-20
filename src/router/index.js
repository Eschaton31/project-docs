import DataManagement from '@/DataManagement.vue'
import Handover from '@/Handover.vue'
import IscervUserManual from '@/IscervUserManual.vue'
import OhasisLinelists from '@/OhasisLinelists.vue'
import ServerMigration2026 from '@/ServerMigration2026.vue'
import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/handover', component: Handover },
    { path: '/data-management', component: DataManagement },
    { path: '/iscerv-user-manual', component: IscervUserManual },
    { path: '/ohasis-linelists', component: OhasisLinelists },
    { path: '/server-migration-2026', component: ServerMigration2026 },
  ],
})

export default router
