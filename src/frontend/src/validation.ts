import type { Player, ReportState, Side } from './types'

// The checks before a game may be started and before it may be submitted. Kept apart from the screens so
// they can be tested. Each check says what is wrong in words the scorer can act on.

/** A player is "dressed" (on the game sheet) when he has a jersey number. Team officials are not players. */
export const isDressed = (p: Player) => p.position !== 'TO' && p.jersey.trim() !== ''

/** Players wear a number from 0 to 99; team officials are HC (head coach) or T1 to T7, never AC or TM. */
export function jerseyProblem(p: Player): string | null {
  const jersey = p.jersey.trim()
  if (jersey === '') return null
  if (p.position === 'TO') return /^(HC|T[1-7])$/i.test(jersey) ? null : 'must be HC or T1 to T7'
  return /^\d{1,2}$/.test(jersey) ? null : 'must be a number from 0 to 99'
}

export const duplicateJerseys = (players: Player[]): Set<string> => {
  const count = new Map<string, number>()
  for (const p of players) {
    const j = p.jersey.trim().toUpperCase()
    if (j !== '') count.set(j, (count.get(j) ?? 0) + 1)
  }
  return new Set([...count].filter(([, n]) => n > 1).map(([j]) => j))
}

const fullName = (p: Player) => `${p.lastname} ${p.firstname}`.trim()

/** The manual allows up to three assistant captains from season 26/27. */
export const MAX_ASSISTANTS = 3

/** A team needs at least one player (team officials do not count). */
export const hasNoPlayers = (players: Player[]) => !players.some((p) => p.position !== 'TO')

/** How many captains (C) the dressed players of a team have: it has to be exactly one. */
export const captainCount = (players: Player[]) => players.filter((p) => isDressed(p) && p.captain === 'C').length

/** What is wrong with the lineup of one team. Empty = fine. */
export function validateTeam(players: Player[]): string[] {
  const playing = players.filter((p) => p.position !== 'TO')
  if (!playing.length) return ['There are no players yet. Add them with "Add player or official".']

  const problems: string[] = []
  for (const p of playing) {
    if (p.jersey.trim() === '') problems.push(`${fullName(p)} has no number. Give a number, or remove the player.`)
  }
  const duplicates = duplicateJerseys(players)
  if (duplicates.size) problems.push(`Number ${[...duplicates].join(', ')} is used more than once.`)
  for (const p of players) {
    const problem = jerseyProblem(p)
    if (problem) problems.push(`The number of ${fullName(p)} ${problem}.`)
  }
  const captains = captainCount(players)
  if (captains === 0) problems.push('Choose a captain (C).')
  if (captains > 1) problems.push('Only one captain (C) is allowed.')
  if (playing.filter((p) => isDressed(p) && p.captain === 'A').length > MAX_ASSISTANTS) {
    problems.push(`At most ${MAX_ASSISTANTS} assistant captains (A) are allowed.`)
  }
  return problems
}

/** Worth a look but not blocking: players without a position, as the manual asks to fill it in. */
export function teamWarnings(players: Player[]): string[] {
  const missing = players.filter((p) => isDressed(p) && p.position === '').length
  return missing ? [`${missing} ${missing === 1 ? 'player has' : 'players have'} no position (G, D or F).`] : []
}

/** What stops the game from being started: every team needs a valid lineup (see validateTeam). */
export function validateSetup(report: ReportState, teamName: (side: Side) => string): string[] {
  return (['home', 'away'] as const).flatMap((side) => {
    const team = teamName(side) || (side === 'home' ? 'Home team' : 'Away team')
    return validateTeam(report.players[side]).map((problem) => `${team}: ${problem}`)
  })
}

const named = (report: ReportState, fn: string) =>
  report.officials.some((o) => o.function.toLowerCase() === fn && `${o.lastname}${o.firstname}`.trim() !== '')

/** Whether a referee, the scorekeeper and the timekeeper are filled in. */
export const officialsMissing = (report: ReportState) =>
  (!named(report, 'referee1') && !named(report, 'referee2')) || !named(report, 'scorekeeper') || !named(report, 'timekeeper')

/** What the submit needs, like the status check in the Windows EGREP: referee, scorekeeper, timekeeper and times. */
export function validateSubmit(report: ReportState): string[] {
  const problems: string[] = []
  if (!named(report, 'referee1') && !named(report, 'referee2')) problems.push('Fill in at least one referee (under Officials).')
  if (!named(report, 'scorekeeper')) problems.push('Fill in the scorekeeper (under Officials): EGREP always sends the name with the upload.')
  if (!named(report, 'timekeeper')) problems.push('Fill in the timekeeper (under Officials).')
  if (!report.gamestart || !report.gameend) problems.push('Fill in the start and end time of the game (under Game times).')
  return problems
}
