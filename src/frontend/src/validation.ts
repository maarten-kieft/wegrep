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

/** What stops the game from being started: nothing on the sheet for a team, or numbers that cannot be right. */
export function validateSetup(report: ReportState, teamName: (side: Side) => string): string[] {
  const problems: string[] = []
  for (const side of ['home', 'away'] as const) {
    const team = teamName(side) || (side === 'home' ? 'Home team' : 'Away team')
    const players = report.players[side]
    if (!players.some(isDressed)) {
      problems.push(`${team}: no players are dressed. Give the players a jersey number, or add them with "Add player or official".`)
    }
    const duplicates = duplicateJerseys(players)
    if (duplicates.size) problems.push(`${team}: jersey number ${[...duplicates].join(', ')} is used more than once.`)
    for (const p of players) {
      const problem = jerseyProblem(p)
      if (problem) problems.push(`${team}: the number of ${p.lastname} ${p.firstname} ${problem}.`)
    }
  }
  return problems
}

/** Things worth a look but not blocking: players without a position, as the manual asks to fill it in. */
export function setupWarnings(report: ReportState, teamName: (side: Side) => string): string[] {
  const warnings: string[] = []
  for (const side of ['home', 'away'] as const) {
    const missing = report.players[side].filter((p) => isDressed(p) && p.position === '').length
    if (missing) warnings.push(`${teamName(side) || side}: ${missing} dressed ${missing === 1 ? 'player has' : 'players have'} no position (G, D or F).`)
  }
  return warnings
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
