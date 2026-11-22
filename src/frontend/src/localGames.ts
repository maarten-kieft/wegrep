import { currentUser } from './auth'
import { storage } from './storage'
import type { SavedGame, SessionSummary } from './types'

// Games in progress live in localStorage, one entry per game. They belong to the user who started them:
// on a shared tablet somebody else's game is not offered to you.
const KEY = 'game:'

export const localKey = (gameId: number) => `${KEY}${gameId}`

function isMine(saved: SavedGame) {
  return saved.userId === undefined || saved.userId === currentUser.value?.id
}

export function readLocal(gameId: number): SavedGame | null {
  const saved = storage.getJson<SavedGame>(localKey(gameId))
  return saved && isMine(saved) ? saved : null
}

export function writeLocal(gameId: number, saved: SavedGame) {
  storage.setJson(localKey(gameId), saved)
}

export function removeLocal(gameId: number) {
  storage.remove(localKey(gameId))
}

/** The started games of the signed-in user that are in this browser. */
export function listLocal(): SessionSummary[] {
  const list: SessionSummary[] = []
  for (const key of storage.keys(KEY)) {
    const saved = storage.getJson<SavedGame>(key)
    const gameId = Number(key.slice(KEY.length))
    if (saved && gameId && saved.leagueId && saved.phase !== 'setup' && isMine(saved)) list.push(summarize(gameId, saved))
  }
  return list.sort((a, b) => b.savedAt - a.savedAt)
}

export function summarize(gameId: number, saved: SavedGame): SessionSummary {
  const gd = saved.report.gamedata
  const goals = saved.report.goals
  return {
    gameId,
    leagueId: saved.leagueId ?? 0,
    title: `${gd.teama ?? ''} - ${gd.teamb ?? ''}`,
    phase: saved.phase,
    scoreHome: goals.filter((g) => g.team === 'home').length,
    scoreAway: goals.filter((g) => g.team === 'away').length,
    savedAt: saved.savedAt,
  }
}
