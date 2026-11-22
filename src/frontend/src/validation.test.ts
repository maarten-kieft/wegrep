import { describe, expect, it } from 'vitest'
import type { Player, ReportState } from './types'
import { duplicateJerseys, jerseyProblem, setupWarnings, validateSetup, validateSubmit } from './validation'

const player = (over: Partial<Player> = {}): Player => ({
  id: '1', jersey: '9', lastname: 'Doe', firstname: 'Jane', licensenr: '', birthdate: '', captain: '', position: 'F', line: '1', starting: false, ...over,
})

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
  it('says nothing about an empty number: that player is just not on the sheet', () => {
    expect(jerseyProblem(player({ jersey: '' }))).toBeNull()
  })
})

describe('duplicateJerseys', () => {
  it('finds numbers used twice', () => {
    expect([...duplicateJerseys([player({ jersey: '9' }), player({ id: '2', jersey: '9' }), player({ id: '3', jersey: '10' })])]).toEqual(['9'])
  })
})

describe('validateSetup', () => {
  it('lets a game start when both teams have dressed players', () => {
    expect(validateSetup(report([player()], [player({ id: '2' })]), names)).toEqual([])
  })
  it('stops a team without any dressed player, also when only officials or players without a number are there', () => {
    const empty = validateSetup(report([player()], []), names)
    expect(empty).toHaveLength(1)
    expect(empty[0]).toContain('Bulldogs')
    expect(validateSetup(report([player()], [player({ jersey: '' }), player({ id: '2', position: 'TO', jersey: 'HC' })]), names)).toHaveLength(1)
  })
  it('stops duplicate and impossible numbers', () => {
    const problems = validateSetup(report([player({ jersey: '9' }), player({ id: '2', jersey: '9' }), player({ id: '3', jersey: 'x' })], [player({ id: '9' })]), names)
    expect(problems.some((p) => p.includes('used more than once'))).toBe(true)
    expect(problems.some((p) => p.includes('Doe Jane'))).toBe(true)
  })
})

describe('setupWarnings', () => {
  it('mentions dressed players without a position, but not players who are not on the sheet', () => {
    const warnings = setupWarnings(report([player({ position: '' }), player({ id: '2', position: '', jersey: '' })], [player({ id: '3' })]), names)
    expect(warnings).toEqual(['Cougars: 1 dressed player has no position (G, D or F).'])
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
