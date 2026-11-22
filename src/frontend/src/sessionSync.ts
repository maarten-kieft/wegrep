import { ref } from 'vue'
import { api, ApiError } from './api'
import { isSignedIn } from './auth'
import { readLocal, summarize } from './localGames'
import { storage } from './storage'

// Keeps the server's copy of a started game in step with the browser's. The browser copy is the one the
// app works with, so a dead connection never stops anything: a game that could not be uploaded is
// remembered ("dirty") and uploaded later, when the connection is back. Newest save wins on the server.
const DIRTY_KEY = 'sync-dirty'
const DEBOUNCE_MS = 2000

/** Set when the server refused an upload because it holds a newer save of this game (from another device). */
export const syncConflict = ref<number | null>(null)

const timers = new Map<number, ReturnType<typeof setTimeout>>()

/** Whether the browser thinks it has a connection (a hint: a request can still fail). */
export const online = ref(navigator.onLine)
window.addEventListener('online', () => (online.value = true))
window.addEventListener('offline', () => (online.value = false))

const dirtyIds = () => storage.getJson<number[]>(DIRTY_KEY) ?? []
/** How many games have changes that are not on the server yet. */
export const waitingToSync = ref(dirtyIds().length)
const setDirty = (ids: number[]) => {
  storage.setJson(DIRTY_KEY, ids)
  waitingToSync.value = ids.length
}
export const markDirty = (id: number) => { if (!dirtyIds().includes(id)) setDirty([...dirtyIds(), id]) }
export const clearDirty = (id: number) => setDirty(dirtyIds().filter((x) => x !== id))

type Outcome = 'ok' | 'conflict' | 'offline'

/** Upload the browser's copy of a game now. */
export async function pushNow(gameId: number): Promise<Outcome> {
  clearTimeout(timers.get(gameId))
  timers.delete(gameId)
  const saved = readLocal(gameId)
  if (!saved || saved.phase === 'setup' || !saved.leagueId) {
    clearDirty(gameId)
    return 'ok'
  }
  try {
    await api.putSession(gameId, summarize(gameId, saved), saved)
    clearDirty(gameId)
    if (syncConflict.value === gameId) syncConflict.value = null
    return 'ok'
  } catch (e) {
    if (e instanceof ApiError && e.status === 409) {
      clearDirty(gameId)
      syncConflict.value = gameId
      return 'conflict'
    }
    // No connection, server trouble, expired login: keep it dirty and try again later.
    return 'offline'
  }
}

/** Upload soon (a burst of edits becomes one upload). Remembers the game as dirty until it went through. */
export function schedulePush(gameId: number) {
  markDirty(gameId)
  clearTimeout(timers.get(gameId))
  timers.set(gameId, setTimeout(() => pushNow(gameId), DEBOUNCE_MS))
}

/** Upload everything that is still waiting. */
export async function flushDirty() {
  if (!isSignedIn.value || navigator.onLine === false) return
  for (const id of dirtyIds()) await pushNow(id)
}

/** Retry waiting uploads at start-up, when the connection returns and once a minute. Call once. */
export function startSync() {
  flushDirty()
  window.addEventListener('online', flushDirty)
  setInterval(flushDirty, 60_000)
}
