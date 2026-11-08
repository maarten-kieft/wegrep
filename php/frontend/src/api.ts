import type {
  GameListItem, GameResponse, League, Meta, PrecheckResult, ReportState, UploadResult,
} from './types'
import { storage } from './storage'

export class ApiError extends Error {
  constructor(message: string, public status: number) {
    super(message)
  }
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const res = await fetch(`${import.meta.env.BASE_URL}api${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      'X-App-Password': storage.get('password') ?? '',
      ...init.headers,
    },
  })
  const body = await res.json().catch(() => ({ error: `HTTP ${res.status}` }))
  if (!res.ok) {
    throw new ApiError(body.error ?? `HTTP ${res.status}`, res.status)
  }
  return body as T
}

const post = <T>(path: string, data: unknown) =>
  request<T>(path, { method: 'POST', body: JSON.stringify(data) })

export const api = {
  meta: () => request<Meta>('/meta'),
  leagues: () => request<League[]>('/leagues'),
  games: (leagueId: number, selection: string) =>
    request<GameListItem[]>(`/leagues/${leagueId}/games?selection=${selection}`),
  game: (gameId: number) => request<GameResponse>(`/games/${gameId}`),
  precheck: (gameId: number, homeId: string, awayId: string) =>
    request<PrecheckResult>(`/games/${gameId}/precheck?homeId=${homeId}&awayId=${awayId}`),
  preview: (gameId: number, templateXml: string, state: ReportState) =>
    post<{ xml: string; payload: string }>(`/games/${gameId}/preview`, { templateXml, state }),
  upload: (gameId: number, templateXml: string, state: ReportState, status: 1 | 3,
    username: string, blueCode = '', redCode = '') =>
    post<UploadResult>(`/games/${gameId}/upload`, { templateXml, state, status, username, blueCode, redCode }),
}
