import axios from 'axios'

/**
 * Same-origin client for the Laravel API. Auth is the session cookie (Sanctum SPA mode); axios echoes the
 * XSRF-TOKEN cookie back as the X-XSRF-TOKEN header on writes.
 */
/**
 * Identifies this browser tab. Locks belong to a tab, and every change the server records carries it, so
 * the tab can skip its own changes when it catches up with everyone else's.
 */
export const clientId = crypto.randomUUID()

export const http = axios.create({
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Client-Id': clientId },
})

export const csrf = () => http.get('/sanctum/csrf-cookie')

export const api = {
  user: () => http.get('/api/user').then((response) => response.data),
  documents: () => http.get('/api/documents').then((response) => response.data.data),
  document: (slug) => http.get(`/api/documents/${encodeURIComponent(slug)}`).then((response) => response.data),
  createDocument: (title) => http.post('/api/documents', { title }).then((response) => response.data.document),
  importDocument: (payload) => http.post('/api/documents/import', payload).then((response) => response.data.document),
}

/** Readable message for a failed request, including Laravel validation errors. */
export function errorMessage(error) {
  const data = error.response?.data
  if (data?.errors) return Object.values(data.errors).flat().join(' ')

  return data?.message ?? error.message
}
