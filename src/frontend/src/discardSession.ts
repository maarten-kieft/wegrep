import { api } from './api'
import { removeLocal } from './localGames'
import { clearDirty } from './sessionSync'

/**
 * Throw a started game away, in this browser and on the server. Needs a connection: without it the
 * server copy would simply come back, so then nothing is removed and the error says why.
 */
export async function discardSession(gameId: number) {
  try {
    await api.deleteSession(gameId)
  } catch (e) {
    throw new Error(e instanceof TypeError ? 'Geen verbinding: weggooien kan alleen online.' : (e as Error).message)
  }
  removeLocal(gameId)
  clearDirty(gameId)
}
