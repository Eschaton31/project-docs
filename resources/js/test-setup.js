import { vi } from 'vitest'

// CodeBlock (vue-code-block) fetches its theme CSS on mount; there is no server under test
vi.stubGlobal('fetch', async () => new Response('', { status: 200 }))
