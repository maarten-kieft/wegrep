export interface Player {
  number: string
  name: string
}

export interface Team {
  id: string
  name: string
  icon?: string
  players: Player[]
}

export interface Game {
  id: string
  homeTeam: Team
  awayTeam: Team
  date: string
  time: string
  venue: string
  status: GameStatus
}

export interface League {
  id: string
  name: string
  games: Game[]
}

export interface Goal {
  id: string
  time: string
  team: "home" | "away"
  scorerNumber: string
  scorerName: string
  assist1Number?: string
  assist1Name?: string
  assist2Number?: string
  assist2Name?: string
  situation: "EV" | "PP" | "SH" | "EN" | "PS"
  type: "Wrist" | "Slap" | "Snap" | "Backhand" | "Deflection" | "Tip" | "Wrap"
  period: 1 | 2 | 3 | "OT"
}

export interface Penalty {
  id: string
  time: string
  team: "home" | "away"
  playerNumber: string
  playerName: string
  minutes: number
  type: string
  startTime: string
  endTime: string
  period: 1 | 2 | 3 | "OT"
}

export interface GameSheet {
  game: Game
  goals: Goal[]
  penalties: Penalty[]
  period: 1 | 2 | 3 | "OT"
}

export type GameStatus = "setup" | "active" | "ended"
