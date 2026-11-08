import { ref } from 'vue'
import { api } from './api'
import { storage } from './storage'
import type { LogoDict } from './types'

// Club logos (from ijshockey.nl, via our /api/logos). Fetched once, cached in the
// browser for a day. Optional: if it never loads, the lettered badges stay.
const CACHE_KEY = 'logos.v2' // bump when the dictionary shape changes, to drop stale caches
const TTL = 24 * 60 * 60 * 1000

const dict = ref<LogoDict | null>(null)
let loading: Promise<unknown> | null = null

// Must match TeamLogos::normalize() on the server (lowercase, collapse whitespace).
const normalize = (name: string) => name.toLowerCase().replace(/\s+/g, ' ').trim()

/** Logo url for a team, by hockeydata id first, then by name. Reactive. */
export function logoFor(name: string, teamId?: number | string | null): string | null {
  const d = dict.value
  if (!d) return null
  const id = typeof teamId === 'string' ? parseInt(teamId, 10) : teamId
  if (id && d.byId[id]) return d.byId[id]
  return d.byName[normalize(name)] ?? null
}

/**
 * Logo for a team in a specific game. Prefers the exact game-guid match (works
 * even when hockeydata shows a short code like "GRO-2"), then falls back to name.
 */
export function gameLogo(guid: string | null | undefined, side: 'home' | 'away', name: string): string | null {
  const d = dict.value
  if (!d) return null
  const byGuid = guid && d.byGuid ? d.byGuid[guid] : undefined
  if (byGuid && byGuid[side]) return byGuid[side]
  return d.byName[normalize(name)] ?? null
}

/** Load the dictionary once (cache first, then network in the background). */
export function ensureLogos(): void {
  if (dict.value || loading) return
  const cached = storage.getJson<{ at: number; dict: LogoDict }>(CACHE_KEY)
  if (cached && Date.now() - cached.at < TTL) {
    dict.value = cached.dict
    return
  }
  loading = api.logos()
    .then((d) => {
      dict.value = d
      storage.setJson(CACHE_KEY, { at: Date.now(), dict: d })
    })
    .catch(() => { /* logos are optional */ })
    .finally(() => { loading = null })
}
