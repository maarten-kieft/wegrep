import { describe, expect, it } from 'vitest'
import { summarize } from './localGames'
import type { SavedGame } from './types'

const saved = (goals: { team: 'home' | 'away' }[]): SavedGame => ({
  phase: 'active',
  templateXml: '<game/>',
  report: { gamedata: { teama: 'Cougars', teamb: 'Bulldogs' }, goals, penalties: [], officials: [], players: { home: [], away: [] }, shots: {}, livetime: '' } as never,
  ruleset: { PeriodNum: 3, PeriodLengthSek: 1200, OvertimeNum: 1, OvertimeLengthSek: 300, ShootoutShots: 5, Name: '3x20' },
  clock: { period: 1, elapsed: 0, runningSince: null },
  savedAt: 1234,
  leagueId: 5,
})

describe('summarize', () => {
  it('describes a saved game for the session list', () => {
    expect(summarize(77, saved([{ team: 'home' }, { team: 'home' }, { team: 'away' }]))).toEqual({
      gameId: 77, leagueId: 5, title: 'Cougars - Bulldogs', phase: 'active', scoreHome: 2, scoreAway: 1, savedAt: 1234,
    })
  })
})
