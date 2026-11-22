import { computed, onScopeDispose, reactive, ref, watch } from 'vue'
import { api } from './api'
import { currentUser } from './auth'
import { readLocal, removeLocal, summarize, writeLocal } from './localGames'
import { clearDirty, markDirty, pushNow, schedulePush } from './sessionSync'
import type { Clock, GameRuleset, Goal, Penalty, Phase, ReportState, SavedGame, SessionSummary, Side } from './types'

export type { Phase }

const DEFAULT_RULESET: GameRuleset = {
  PeriodNum: 3, PeriodLengthSek: 1200, OvertimeNum: 1, OvertimeLengthSek: 300, ShootoutShots: 5, Name: '3x20',
}

/** What the app asks the user when the server already has a session for a game they open. */
export interface ResumeChoice {
  remote: SessionSummary & { state: SavedGame }
  hasLocal: boolean
}

/**
 * Everything for one game: the server template, the edited report and the game clock.
 * It is saved in the browser after every change, so a reload or a dead tablet battery
 * doesn't lose the game, and a started game is synced to the server so another device can pick it up.
 *
 * `resume`: open the saved session without asking (coming from the session list).
 */
export function useGame(gameId: number, leagueId: number, resume = false) {
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

  /** Set when the server has a session that may be newer than ours and the user has to pick. */
  const choice = ref<ResumeChoice | null>(null)

  function apply(saved: SavedGame) {
    phase.value = saved.phase ?? 'setup'
    templateXml.value = saved.templateXml
    report.value = saved.report
    ruleset.value = saved.ruleset
    Object.assign(clock, saved.clock)
  }

  async function startFresh() {
    const data = await api.game(gameId)
    phase.value = 'setup'
    templateXml.value = data.templateXml
    report.value = data.report
    ruleset.value = data.gameRuleset ?? DEFAULT_RULESET
    Object.assign(clock, { period: 1, elapsed: 0, runningSince: null })
  }

  /** The server's copy, or null when there is none or we cannot reach it (then the browser copy rules). */
  async function fetchRemote() {
    if (navigator.onLine === false) return null
    const abort = new AbortController()
    const timeout = setTimeout(() => abort.abort(), 4000)
    try {
      return await api.session(gameId, abort.signal)
    } catch {
      return null
    } finally {
      clearTimeout(timeout)
    }
  }

  async function load(fresh = false) {
    loading.value = true
    error.value = ''
    choice.value = null
    try {
      const local = fresh ? null : readLocal(gameId)
      const remote = fresh ? null : await fetchRemote()
      if (remote && (!local || remote.savedAt > local.savedAt)) {
        // Somewhere (maybe another device) this game was worked on more recently than here.
        if (resume) apply(remote.state)
        else choice.value = { remote, hasLocal: !!local }
      } else if (local) {
        apply(local)
        if (!remote && local.phase !== 'setup') markDirty(gameId) // the server may not have it yet
        else if (remote && remote.savedAt < local.savedAt) markDirty(gameId)
      } else {
        await startFresh()
      }
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  /** Answer to `choice`: carry on with the server's session, or throw it away and begin again. */
  async function resolveChoice(useRemote: boolean) {
    const pending = choice.value
    if (!pending) return
    choice.value = null
    if (useRemote) {
      apply(pending.remote.state)
      return
    }
    loading.value = true
    try {
      await api.deleteSession(gameId)
      removeLocal(gameId)
      clearDirty(gameId)
      await startFresh()
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  /** Take over the server's (newer) version while working, after another device saved. */
  async function adoptRemote() {
    const remote = await fetchRemote()
    if (remote) apply(remote.state)
  }

  function save() {
    if (!report.value || choice.value) return
    const saved: SavedGame = {
      phase: phase.value, templateXml: templateXml.value, report: report.value, ruleset: ruleset.value,
      clock: { ...clock }, savedAt: Date.now(), leagueId, userId: currentUser.value?.id,
    }
    writeLocal(gameId, saved)
    if (saved.phase !== 'setup') schedulePush(gameId)
  }
  watch([report, clock, phase], save, { deep: true })
  // Leaving the screen: send what is still waiting right away.
  onScopeDispose(() => { if (report.value && phase.value !== 'setup') pushNow(gameId) })

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

  async function resetFromServer() {
    removeLocal(gameId)
    clearDirty(gameId)
    await api.deleteSession(gameId).catch(() => { /* offline: the old session stays on the server */ })
    return load(true)
  }

  return {
    loading, error, phase, templateXml, ruleset, report, clock,
    choice, load, resolveChoice, adoptRemote, resetFromServer,
    totalPeriods, periodLength, periodStart, periodLabel, periodShort, periodOf,
    elapsedInPeriod, remainingInPeriod, gameTime, running, startClock, stopClock, setPeriod, setGameTime, adjustClock, resetClock,
    score, byTime, addGoal, updateGoal, updatePenalty, removeGoal, addPenalty, removePenalty, lineup, playerName,
  }
}

export type GameStore = ReturnType<typeof useGame>
