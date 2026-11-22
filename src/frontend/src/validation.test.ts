import { describe, expect, it } from 'vitest'
import type { Player, ReportState } from './types'
import { duplicateJerseys, jerseyProblem, teamWarnings, validateSetup, validateSubmit, validateTeam } from './validation'

const player = (over: Partial<Player> = {}): Player => ({
  id: '1', jersey: '9', lastname: 'Doe', firstname: 'Jane', licensenr: '', birthdate: '', captain: '', position: 'F', line: '1', starting: false, ...over,
})
/** A lineup that is fine: numbered players with one captain. */
const goodTeam = (): Player[] => [player({ captain: 'C' }), player({ id: '2', jersey: '10' })]

const report = (home: Player[], away: Player[]): ReportState => ({
  gamedata: {}, officials: [], players: { home, away }, goals: [], penalties: [], shots: {}, livetime: '',
})
const names = (side: 'home' | 'away') => (side === 'home' ? 'Cougars' : 'Bulldogs')

describe('jerseyProblem', () => {
  it('accepts numbers from 0 to 99 for players', () => {
    for (const j of ['0', '7', '10', '99']) expect(jerseyProblem(player({ jersey: j }))).toBeNull()
  })
  it('refuses letters and three digits for players', () => {
    expect(jerseyProblem(player({ jersey: 'A1' }))).not.toBeNull()
    expect(jerseyProblem(player({ jersey: '100' }))).not.toBeNull()
  })
  it('accepts HC and T1 to T7 for team officials and nothing else', () => {
    for (const j of ['HC', 'hc', 'T1', 'T7']) expect(jerseyProblem(player({ position: 'TO', jersey: j }))).toBeNull()
    for (const j of ['AC', 'TM', 'T8', '5']) expect(jerseyProblem(player({ position: 'TO', jersey: j }))).not.toBeNull()
  })
  it('says nothing about an empty number here: validateTeam asks for it', () => {
    expect(jerseyProblem(player({ jersey: '' }))).toBeNull()
  })
})

describe('duplicateJerseys', () => {
  it('finds numbers used twice', () => {
    expect([...duplicateJerseys([player({ jersey: '9' }), player({ id: '2', jersey: '9' }), player({ id: '3', jersey: '10' })])]).toEqual(['9'])
  })
})

describe('validateTeam', () => {
  it('accepts numbered players with exactly one captain', () => {
    expect(validateTeam(goodTeam())).toEqual([])
  })
  it('asks for players when there are none, also when there are only officials', () => {
    expect(validateTeam([])).toHaveLength(1)
    expect(validateTeam([player({ position: 'TO', jersey: 'HC' })])).toHaveLength(1)
  })
  it('wants a number for every player', () => {
    const problems = validateTeam([...goodTeam(), player({ id: '3', jersey: '', lastname: 'Roe' })])
    expect(problems).toEqual(['Roe Jane has no number. Give a number, or remove the player.'])
  })
  it('wants exactly one captain', () => {
    expect(validateTeam([player(), player({ id: '2', jersey: '10' })])).toEqual(['Choose a captain (C).'])
    expect(validateTeam([player({ captain: 'C' }), player({ id: '2', jersey: '10', captain: 'C' })])).toEqual(['Only one captain (C) is allowed.'])
  })
  it('does not count an assistant captain, nor a captain without a number', () => {
    expect(validateTeam([player({ captain: 'A' }), player({ id: '2', jersey: '', captain: 'C' })])).toContain('Choose a captain (C).')
  })
  it('stops duplicate and impossible numbers', () => {
    const problems = validateTeam([player({ captain: 'C' }), player({ id: '2', jersey: '9' }), player({ id: '3', jersey: 'x' })])
    expect(problems.some((p) => p.includes('used more than once'))).toBe(true)
    expect(problems.some((p) => p.includes('Doe Jane'))).toBe(true)
  })
  it('ignores team officials when counting players and captains', () => {
    expect(validateTeam([...goodTeam(), player({ id: '9', position: 'TO', jersey: 'T1', captain: 'C' })])).toEqual([])
  })
})

describe('teamWarnings', () => {
  it('mentions dressed players without a position, but not players who are not on the sheet', () => {
    expect(teamWarnings([player({ position: '' }), player({ id: '2', position: '', jersey: '' })])).toEqual(['1 player has no position (G, D or F).'])
  })
})

describe('validateSetup', () => {
  it('lets a game start when both teams are fine', () => {
    expect(validateSetup(report(goodTeam(), goodTeam()), names)).toEqual([])
  })
  it('names the team in each problem', () => {
    const problems = validateSetup(report(goodTeam(), []), names)
    expect(problems).toHaveLength(1)
    expect(problems[0]).toContain('Bulldogs: ')
  })
})

describe('validateSubmit', () => {
  const official = (fn: string, lastname: string) => ({ function: fn, lastname, firstname: '' })
  it('asks for a referee, the scorekeeper, the timekeeper and the times', () => {
    expect(validateSubmit(report([], []))).toHaveLength(4)
  })
  it('is satisfied with one referee, scorekeeper, timekeeper and both times', () => {
    const r = report([], [])
    r.officials = [official('referee2', 'Ref'), official('scorekeeper', 'Sk'), official('timekeeper', 'Tk')]
    r.gamestart = '20:00'
    r.gameend = '21:21'
    expect(validateSubmit(r)).toEqual([])
  })
})
