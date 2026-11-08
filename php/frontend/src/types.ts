export type Side = 'home' | 'away'

export interface League {
  id: number
  name: string
}

export interface GameListItem {
  id: number
  guid: string
  name: string
  division: string
  status: number
  location: string
  scheduled: string | null
  home: string
  away: string
  homeRosterPublic: boolean
  awayRosterPublic: boolean
  downloadable: boolean
}

export interface Player {
  id: string
  jersey: string
  lastname: string
  firstname: string
  licensenr: string
  birthdate: string
  captain: string // '', 'C' or 'A'
  position: string // 'G', 'D', 'F', 'TO' (team official) ...
  line: string
  starting: boolean
}

export interface Official {
  function: string
  lastname: string
  firstname: string
}

/** Times are seconds since the start of the game. */
export interface Goal {
  team: Side
  time: number
  scorer: string
  assist1: string
  assist2: string
  strength: number // 0 = even, 1/2 = power play, -1/-2 = short handed
  special: string // '', 'EN', 'PS', 'AG'
}

export interface Penalty {
  team: Side
  time: number
  player: string
  pim: number
  offence: string
  start: number
  end: number
}

export interface Shots {
  home: number
  away: number
}

/** The parts of the game report the app edits (same shape as PHP GameReport::parse). */
export interface ReportState {
  gamedata: Record<string, string>
  officials: Official[]
  players: Record<Side, Player[]>
  goals: Goal[]
  penalties: Penalty[]
  shots: Record<string, Shots>
  livetime: string
}

export interface GameRuleset {
  PeriodNum: number
  PeriodLengthSek: number
  OvertimeNum: number
  OvertimeLengthSek: number
  ShootoutShots: number
  Name: string
}

export interface GameResponse {
  templateXml: string
  report: ReportState
  gameRuleset: GameRuleset | null
}

export interface PenaltyType {
  code: string
  en: string
  de: string
}

export interface Meta {
  uploadEnabled: boolean
  passwordRequired: boolean
  penaltyTypes: PenaltyType[]
}

export interface LogoDict {
  season: string
  byId: Record<number, string>
  byName: Record<string, string>
  byGuid: Record<string, { home: string; away: string }>
  count: number
}

export interface PrecheckResult {
  GameDoesExist: boolean
  Status: number
  ExtendedStatus: number
  NumberOfOvertimes: number
  NumberOfShootoutShots: number
  BlueSignature: string | null
  RedSignature: string | null
}

export interface UploadResult {
  Status: string
  SmsReturnCode: string
}
