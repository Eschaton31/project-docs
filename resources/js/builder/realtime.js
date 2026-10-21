import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

import { http } from './api.js'

let echo = null

/** One Reverb connection per tab, opened on first use. */
function connection() {
  echo ??= new Echo({
    broadcaster: 'reverb',
    Pusher,
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    // authorise channels with the session cookie, like every other request
    channelAuthorization: {
      customHandler: ({ socketId, channelName }, callback) =>
        http
          .post('/broadcasting/auth', { socket_id: socketId, channel_name: channelName })
          .then((response) => callback(null, response.data))
          .catch((error) => callback(error, null)),
    },
  })

  return echo
}

/**
 * Joins the presence channel of a document.
 *
 * handlers: here(members), joining(member), leaving(member), changed({ seq, op, blockId, userId, clientId }),
 * locks({ action, target, userId, name, color, clientId, ttl }), reconnected().
 *
 * @returns {() => void} leave
 */
export function joinDocument(documentId, handlers) {
  const name = `document.${documentId}`
  const echo = connection()

  echo
    .join(name)
    .here(handlers.here)
    .joining(handlers.joining)
    .leaving(handlers.leaving)
    .listen('.document.changed', handlers.changed)
    .listen('.locks.changed', handlers.locks)

  // after a dropped connection, events may have been missed: let the editor catch up
  const socket = echo.connector.pusher.connection
  let wasConnected = false
  const onStateChange = ({ current }) => {
    if (current === 'connected' && wasConnected) handlers.reconnected?.()
    if (current === 'connected') wasConnected = true
  }
  socket.bind('state_change', onStateChange)

  return () => {
    socket.unbind('state_change', onStateChange)
    echo.leave(name)
  }
}
