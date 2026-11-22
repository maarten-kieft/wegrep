import type {
  GameListItem, GameResponse, League, LogoDict, Meta, PrecheckResult, ReportState, UploadResult, User,
} from './types'
import { getToken, renewToken, signOut } from './auth'

export class ApiError extends Error {
  constructor(message: string, public status: number) {
    super(message)
  }
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = getToken()
  const res = await fetch(`${import.meta.env.BASE_URL}api${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...init.headers,
    },
  })
  const renewed = res.headers.get('X-Renewed-Token')
  if (renewed) renewToken(renewed)
  const body = await res.json().catch(() => ({ error: `HTTP ${res.status}` }))
  if (!res.ok) {
    // The server says our token is no good (expired, account removed): sign out. A wrong login
    // code is also a 401, but then there is no token yet. No answer at all (offline) never ends up here.
    if (res.status === 401 && token) signOut()
    throw new ApiError(body.error ?? `HTTP ${res.status}`, res.status)
  }
  return body as T
}

const post = <T>(path: string, data: unknown) =>
  request<T>(path, { method: 'POST', body: JSON.stringify(data) })

export const api = {
  meta: () => request<Meta>('/meta'),
  register: (email: string, firstname: string, lastname: string) =>
    post<{ ok: true }>('/auth/register', { email, firstname, lastname }),
  requestCode: (email: string) => post<{ ok: true }>('/auth/code', { email }),
  verifyCode: (email: string, code: string) => post<{ token: string; user: User }>('/auth/verify', { email, code }),
  me: () => request<{ user: User }>('/auth/me'),
  leagues: () => request<League[]>('/leagues'),
  logos: () => request<LogoDict>('/logos'),
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
