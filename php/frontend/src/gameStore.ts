import { computed, onScopeDispose, reactive, ref, watch } from 'vue'
import { api } from './api'
import { storage } from './storage'
import type { GameRuleset, Goal, Penalty, ReportState, Side } from './types'

interface Clock {
  period: number // 1..PeriodNum, then overtime periods
  elapsed: number // seconds played in this period when the clock was last stopped
  runningSince: number | null // Date.now() when started
}

export type Phase = 'setup' | 'active' | 'ended'

interface SavedGame {
  phase: Phase
  templateXml: string
  report: ReportState
  ruleset: GameRuleset
  clock: Clock
  savedAt: number
}

const DEFAULT_RULESET: GameRuleset = {
  PeriodNum: 3, PeriodLengthSek: 1200, OvertimeNum: 1, OvertimeLengthSek: 300, ShootoutShots: 5, Name: '3x20',
}

/**
 * Everything for one game: the server template, the edited report and the game clock.
 * It is saved in the browser after every change, so a reload or a dead tablet battery
 * doesn't lose the game.
 */
export function useGame(gameId: number) {
  const key = `game:${gameId}`
  const loading = ref(true)
  const error = ref('')
  const templateXml = ref('')
  const ruleset = ref<GameRuleset>(DEFAULT_RULESET)
  const report = ref<ReportState | null>(null)
  const phase = ref<Phase>('setup')
  const clock = reactive<Clock>({ period: 1, elapsed: 0, runningSince: null })
  const now = ref(Date.now())
  const tick = setInterval(() => (now.value = Date.now()), 250)
  onScopeDispose(() => clearInterval(tick))

  async function load(fresh = false) {
    loading.value = true
    error.value = ''
    const saved = fresh ? null : storage.getJson<SavedGame>(key)
    try {
      if (saved) {
        phase.value = saved.phase ?? 'setup'
        templateXml.value = saved.templateXml
        report.value = saved.report
        ruleset.value = saved.ruleset
        Object.assign(clock, saved.clock)
      } else {
        const data = await api.game(gameId)
        phase.value = 'setup'
        templateXml.value = data.templateXml
        report.value = data.report
        ruleset.value = data.gameRuleset ?? DEFAULT_RULESET
        Object.assign(clock, { period: 1, elapsed: 0, runningSince: null })
      }
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  function save() {
    if (!report.value) return
    storage.setJson(key, {
      phase: phase.value, templateXml: templateXml.value, report: report.value, ruleset: ruleset.value, clock: { ...clock }, savedAt: Date.now(),
    } satisfies SavedGame)
  }
  watch([report, clock, phase], save, { deep: true })

  // --- clock ---
  const regularPeriods = computed(() => ruleset.value.PeriodNum)
  const totalPeriods = computed(() => ruleset.value.PeriodNum + ruleset.value.OvertimeNum)

  function periodLength(p: number) {
    return p <= regularPeriods.value ? ruleset.value.PeriodLengthSek : ruleset.value.OvertimeLengthSek
  }
  function periodStart(p: number) {
    let t = 0
    for (let i = 1; i < p; i++) t += periodLength(i)
    return t
  }
  function periodLabel(p: number) {
    return p <= regularPeriods.value ? `Period ${p}` : (ruleset.value.OvertimeNum > 1 ? `Overtime ${p - regularPeriods.value}` : 'Overtime')
  }
  function periodShort(p: number) {
    return p <= regularPeriods.value ? `P${p}` : (ruleset.value.OvertimeNum > 1 ? `OT${p - regularPeriods.value}` : 'OT')
  }
  /** Period number for a game time (for showing goals and penalties). */
  function periodOf(time: number) {
    for (let p = 1; p <= totalPeriods.value; p++) {
      if (time < periodStart(p) + periodLength(p) || p === totalPeriods.value) return p
    }
    return 1
  }

  const elapsedInPeriod = computed(() => {
    const running = clock.runningSince ? (now.value - clock.runningSince) / 1000 : 0
    return Math.min(periodLength(clock.period), clock.elapsed + running)
  })
  const remainingInPeriod = computed(() => periodLength(clock.period) - elapsedInPeriod.value)
  const gameTime = computed(() => Math.floor(periodStart(clock.period) + elapsedInPeriod.value))
  const running = computed(() => clock.runningSince !== null)

  function startClock() {
    if (!clock.runningSince && remainingInPeriod.value > 0) clock.runningSince = Date.now()
  }
  function stopClock() {
    clock.elapsed = elapsedInPeriod.value
    clock.runningSince = null
  }
  function setPeriod(p: number) {
    stopClock()
    clock.period = Math.max(1, Math.min(totalPeriods.value, p))
    clock.elapsed = 0
  }
  /** Set the clock to a game time, e.g. after the referee corrects it. */
  function setGameTime(t: number) {
    stopClock()
    const p = periodOf(t)
    clock.period = p
    clock.elapsed = Math.min(periodLength(p), Math.max(0, t - periodStart(p)))
  }
  /** Move the clock by some seconds within the current period, keeps it running. */
  function adjustClock(delta: number) {
    const next = Math.min(periodLength(clock.period), Math.max(0, elapsedInPeriod.value + delta))
    clock.elapsed = next
    if (clock.runningSince) clock.runningSince = Date.now()
  }
  function resetClock() {
    clock.runningSince = null
    clock.elapsed = 0
  }
  // Stop at the end of the period.
  watch(remainingInPeriod, (r) => { if (r <= 0 && clock.runningSince) stopClock() })

  // --- score and events ---
  const score = computed(() => ({
    home: report.value?.goals.filter((g) => g.team === 'home').length ?? 0,
    away: report.value?.goals.filter((g) => g.team === 'away').length ?? 0,
  }))

  function byTime<T extends { time: number }>(list: T[]) {
    return [...list].sort((a, b) => a.time - b.time)
  }
  function addGoal(g: Goal) { report.value?.goals.push(g) }
  function updateGoal(old: Goal, g: Goal) {
    if (report.value) report.value.goals = report.value.goals.map((x) => (x === old ? g : x))
  }
  function updatePenalty(old: Penalty, p: Penalty) {
    if (report.value) report.value.penalties = report.value.penalties.map((x) => (x === old ? p : x))
  }
  function removeGoal(g: Goal) {
    if (report.value) report.value.goals = report.value.goals.filter((x) => x !== g)
  }
  function addPenalty(p: Penalty) { report.value?.penalties.push(p) }
  function removePenalty(p: Penalty) {
    if (report.value) report.value.penalties = report.value.penalties.filter((x) => x !== p)
  }

  /** Players that are dressed: they have a jersey number. */
  function lineup(side: Side) {
    return (report.value?.players[side] ?? [])
      .filter((p) => p.jersey.trim() !== '')
      .sort((a, b) => (parseInt(a.jersey) || 999) - (parseInt(b.jersey) || 999))
  }
  function playerName(side: Side, jersey: string) {
    const p = report.value?.players[side].find((x) => x.jersey === jersey)
    return p ? `${p.lastname} ${p.firstname}`.trim() : ''
  }

  function resetFromServer() {
    storage.remove(key)
    return load(true)
  }

  return {
    loading, error, phase, templateXml, ruleset, report, clock,
    load, resetFromServer,
    totalPeriods, periodLength, periodStart, periodLabel, periodShort, periodOf,
    elapsedInPeriod, remainingInPeriod, gameTime, running, startClock, stopClock, setPeriod, setGameTime, adjustClock, resetClock,
    score, byTime, addGoal, updateGoal, updatePenalty, removeGoal, addPenalty, removePenalty, lineup, playerName,
  }
}

export type GameStore = ReturnType<typeof useGame>
