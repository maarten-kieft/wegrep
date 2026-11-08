<script setup lang="ts">
import { AlertTriangle, CheckCircle, Pencil, Radio, Target, Trash2 } from 'lucide-vue-next'
import { computed, onBeforeUnmount, ref, toRaw, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import AppHeader from '../components/AppHeader.vue'
import GameTimer from '../components/GameTimer.vue'
import GoalModal from '../components/GoalModal.vue'
import PenaltyModal from '../components/PenaltyModal.vue'
import TeamBadge from '../components/TeamBadge.vue'
import TeamRosterEditor from '../components/TeamRosterEditor.vue'
import UploadPanel from '../components/UploadPanel.vue'
import { useGame } from '../gameStore'
import { meta } from '../meta'
import { formatTime } from '../time'
import type { Goal, Penalty, Side } from '../types'
import { btn, card } from '../ui'

const props = defineProps<{ leagueId: number; gameId: number }>()
const router = useRouter()
const game = useGame(props.gameId)
game.load()

const report = game.report
const back = `/leagues/${props.leagueId}/games`
const gd = computed(() => report.value?.gamedata ?? {})
const teamName = (s: Side) => (s === 'home' ? gd.value.teama : gd.value.teamb) ?? ''

const tab = ref<'goals' | 'penalties' | 'shots'>('goals')
const goalModal = ref<{ team: Side; editing: Goal | null } | null>(null)
const penaltyModal = ref<{ team: Side; editing: Penalty | null } | null>(null)
const confirmEnd = ref(false)
const confirmReset = ref(false)

// ---- grouped lists, newest first, like the poc ----
function groupByPeriod<T extends { time: number }>(items: T[]) {
  const groups = new Map<number, T[]>()
  for (const item of [...items].sort((a, b) => b.time - a.time)) {
    const p = game.periodOf(item.time)
    groups.set(p, [...(groups.get(p) ?? []), item])
  }
  return [...groups.entries()]
}
const goalsByPeriod = computed(() => groupByPeriod(report.value?.goals ?? []))
const penaltiesByPeriod = computed(() => groupByPeriod(report.value?.penalties ?? []))
const situation = (goal: Goal) => [goal.strength > 0 ? `PP${goal.strength}` : goal.strength < 0 ? `SH${-goal.strength}` : 'EQ', goal.special].filter(Boolean).join(' · ')
const penaltyName = (code: string) => meta.value.penaltyTypes.find((t) => t.code === code)?.en ?? code
const assists = (goal: Goal) => [goal.assist1, goal.assist2].filter(Boolean)
  .map((n) => `#${n} ${game.playerName(goal.team, n)}`).join(', ')

// ---- shots on goal ----
const shotPeriods = computed(() => {
  const list = Array.from({ length: game.ruleset.value.PeriodNum }, (_, i) => String(i + 1))
  if (game.ruleset.value.OvertimeNum) list.push('OVT')
  return list
})
const shots = (p: string) => report.value?.shots[p] ?? { home: 0, away: 0 }
const totalShots = (s: Side) => shotPeriods.value.reduce((t, p) => t + shots(p)[s], 0)

// ---- officials ----
const OFFICIALS: Record<string, string> = {
  referee1: 'Referee 1', referee2: 'Referee 2', linesman1: 'Linesman 1', linesman2: 'Linesman 2', scorekeeper: 'Scorekeeper',
  timekeeper: 'Timekeeper', goaljudge1: 'Goal judge 1', goaljudge2: 'Goal judge 2', gamesupervisor: 'Supervisor',
}
const officials = computed(() => report.value?.officials.filter((o) => OFFICIALS[o.function]) ?? [])

// ---- live scoring: every minute while on, like EGREP ----
const live = ref(false)
const liveStatus = ref('')
let liveTimer: ReturnType<typeof setInterval> | undefined
async function sendLive() {
  if (!report.value) return
  try {
    const keeper = report.value.officials.find((o) => o.function.toLowerCase() === 'scorekeeper')
    const res = await api.upload(props.gameId, game.templateXml.value, structuredClone(toRaw(report.value)), 1,
      keeper ? `${keeper.lastname} ${keeper.firstname}`.trim() : '')
    liveStatus.value = res.Status === '1' ? `Live update ${new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}` : `Server status ${res.Status}`
  } catch (e) {
    liveStatus.value = (e as Error).message
  }
}
watch(live, (on) => {
  clearInterval(liveTimer)
  if (on) {
    sendLive()
    liveTimer = setInterval(sendLive, 60_000)
  }
})
onBeforeUnmount(() => clearInterval(liveTimer))

// ---- phases ----
function startGame() {
  game.phase.value = 'active'
  if (report.value && !report.value.livetime) report.value.livetime = 'START-1'
}
function endGame() {
  confirmEnd.value = false
  game.stopClock()
  live.value = false
  if (report.value) report.value.livetime = 'END-GAME'
  game.phase.value = 'ended'
}
// Keep the live status ("START-2" etc.) in step with the clock period.
watch(() => game.clock.period, (p) => {
  if (report.value && game.phase.value === 'active') {
    report.value.livetime = p <= game.ruleset.value.PeriodNum ? `START-${p}` : 'START-OVT'
  }
})
async function resetGame() {
  confirmReset.value = false
  await game.resetFromServer()
}
</script>

<template>
  <div class="min-h-screen bg-background overflow-x-hidden">
    <div v-if="game.loading.value" class="min-h-screen grid place-items-center text-muted-foreground">Loading game…</div>
    <div v-else-if="game.error.value" class="min-h-screen grid place-items-center p-4 text-center">
      <div class="space-y-4">
        <p class="text-destructive">{{ game.error.value }}</p>
        <RouterLink :to="back" :class="btn('outline')">Back to games</RouterLink>
      </div>
    </div>

    <!-- ================= SETUP ================= -->
    <template v-else-if="report && game.phase.value === 'setup'">
      <AppHeader variant="small" :back="back" title="Game Setup" subtitle="Review and edit team rosters" />

      <div class="px-4 pt-6 pb-[calc(7rem+env(safe-area-inset-bottom))] max-w-4xl mx-auto space-y-6">
        <div class="space-y-6 lg:grid lg:grid-cols-2 lg:gap-6 lg:space-y-0">
          <TeamRosterEditor :name="teamName('home')" label="Home Team" :players="report.players.home" />
          <TeamRosterEditor :name="teamName('away')" label="Away Team" :players="report.players.away" />
        </div>

        <div :class="[card, 'p-4']">
          <h3 class="font-semibold mb-3">Officials</h3>
          <div class="space-y-3">
            <div v-for="o in officials" :key="o.function" class="grid grid-cols-[6.5rem_1fr_1fr] gap-2 items-center">
              <span class="text-sm text-muted-foreground">{{ OFFICIALS[o.function] }}</span>
              <input v-model="o.lastname" class="field h-10 text-sm" placeholder="Last name" />
              <input v-model="o.firstname" class="field h-10 text-sm" placeholder="First name" />
            </div>
          </div>
        </div>
      </div>

      <div class="fixed bottom-0 left-0 right-0 p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] bg-background border-t border-border">
        <div class="max-w-4xl mx-auto">
          <button :class="btn('default', 'lg', 'w-full h-12')" @click="startGame">Start Game</button>
        </div>
      </div>
    </template>

    <!-- ================= GAME SHEET ================= -->
    <template v-else-if="report && game.phase.value === 'active'">
      <AppHeader variant="small" :back="back" title="Game Sheet">
        <template #actions>
          <button class="inline-flex items-center h-8 px-3 rounded-md text-sm font-medium text-white hover:bg-white/20"
            @click="game.phase.value = 'setup'">Rosters</button>
        </template>
      </AppHeader>

      <div class="px-4 pt-4 pb-[calc(7rem+env(safe-area-inset-bottom))] max-w-4xl mx-auto">
        <div :class="[card, 'mb-4 p-4']">
          <div class="flex items-center justify-between mb-4">
            <div class="text-center flex-1 min-w-0">
              <p class="text-sm text-muted-foreground truncate">{{ teamName('home') }}</p>
              <p class="text-3xl font-bold text-foreground">{{ game.score.value.home }}</p>
            </div>
            <div class="px-4"><span class="text-2xl font-bold text-muted-foreground">VS</span></div>
            <div class="text-center flex-1 min-w-0">
              <p class="text-sm text-muted-foreground truncate">{{ teamName('away') }}</p>
              <p class="text-3xl font-bold text-foreground">{{ game.score.value.away }}</p>
            </div>
          </div>
          <GameTimer :game="game" />

          <label v-if="meta.uploadEnabled" class="mt-3 flex items-center justify-between gap-3 rounded-lg bg-secondary px-3 py-2 cursor-pointer">
            <span class="flex items-center gap-2 text-sm font-medium">
              <Radio class="w-4 h-4" :class="live ? 'text-destructive animate-pulse' : 'text-muted-foreground'" />
              Live scoring <span class="text-xs text-muted-foreground font-normal">{{ liveStatus }}</span>
            </span>
            <input v-model="live" type="checkbox" class="w-5 h-5 accent-[var(--primary)]" />
          </label>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
          <button :class="btn('default', 'lg', 'h-16 min-w-0 px-3 justify-start')" @click="goalModal = { team: 'home', editing: null }">
            <Target class="w-5 h-5" />
            <span class="min-w-0 text-left leading-tight"><span class="block font-semibold">Goal</span><span class="block truncate text-xs opacity-80">{{ teamName('home') }}</span></span>
          </button>
          <button :class="btn('default', 'lg', 'h-16 min-w-0 px-3 justify-start')" @click="goalModal = { team: 'away', editing: null }">
            <Target class="w-5 h-5" />
            <span class="min-w-0 text-left leading-tight"><span class="block font-semibold">Goal</span><span class="block truncate text-xs opacity-80">{{ teamName('away') }}</span></span>
          </button>
          <button :class="btn('destructive', 'lg', 'h-16 min-w-0 px-3 justify-start')" @click="penaltyModal = { team: 'home', editing: null }">
            <AlertTriangle class="w-5 h-5" />
            <span class="min-w-0 text-left leading-tight"><span class="block font-semibold">Penalty</span><span class="block truncate text-xs opacity-80">{{ teamName('home') }}</span></span>
          </button>
          <button :class="btn('destructive', 'lg', 'h-16 min-w-0 px-3 justify-start')" @click="penaltyModal = { team: 'away', editing: null }">
            <AlertTriangle class="w-5 h-5" />
            <span class="min-w-0 text-left leading-tight"><span class="block font-semibold">Penalty</span><span class="block truncate text-xs opacity-80">{{ teamName('away') }}</span></span>
          </button>
        </div>

        <!-- tabs -->
        <div class="grid w-full grid-cols-3 bg-muted rounded-lg p-[3px] h-10">
          <button v-for="t in (['goals', 'penalties', 'shots'] as const)" :key="t"
            class="rounded-md text-sm font-medium transition-colors cursor-pointer"
            :class="tab === t ? 'bg-input/80 text-foreground shadow-sm border border-input' : 'text-muted-foreground'"
            @click="tab = t">
            {{ t === 'goals' ? `Goals (${report.goals.length})` : t === 'penalties' ? `Penalties (${report.penalties.length})` : 'Shots' }}
          </button>
        </div>

        <!-- goals -->
        <div v-if="tab === 'goals'" class="space-y-3 mt-4">
          <div v-if="!report.goals.length" class="text-center py-8 text-muted-foreground"><p>No goals recorded yet</p></div>
          <div v-for="[period, goals] in goalsByPeriod" :key="period">
            <div class="flex items-center gap-2 mb-3">
              <div class="h-px bg-border flex-1" />
              <span class="text-xs text-muted-foreground font-medium px-2">{{ game.periodLabel(period) }}</span>
              <div class="h-px bg-border flex-1" />
            </div>
            <div class="space-y-3">
              <div v-for="(goal, i) in goals" :key="i" :class="[card, 'border-l-4 border-l-primary p-3']">
                <div class="flex items-start gap-3">
                  <TeamBadge :name="teamName(goal.team)" :side="goal.team" size="sm" />
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                      <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary/10 text-primary">{{ game.periodShort(period) }}</span>
                      <span class="text-sm font-semibold text-foreground font-mono">{{ formatTime(goal.time) }}</span>
                      <span class="text-xs px-2 py-0.5 rounded bg-secondary text-secondary-foreground">{{ situation(goal) }}</span>
                    </div>
                    <p class="text-sm font-semibold text-foreground">#{{ goal.scorer }} {{ game.playerName(goal.team, goal.scorer) }}</p>
                    <p v-if="assists(goal)" class="text-xs text-muted-foreground">Assists: {{ assists(goal) }}</p>
                  </div>
                  <div class="flex gap-1 shrink-0">
                    <button :class="btn('ghost', 'icon-sm')" aria-label="Edit" @click="goalModal = { team: goal.team, editing: goal }"><Pencil class="w-4 h-4" /></button>
                    <button :class="btn('ghost', 'icon-sm', 'text-destructive')" aria-label="Delete" @click="game.removeGoal(goal)"><Trash2 class="w-4 h-4" /></button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- penalties -->
        <div v-else-if="tab === 'penalties'" class="space-y-3 mt-4">
          <div v-if="!report.penalties.length" class="text-center py-8 text-muted-foreground"><p>No penalties recorded yet</p></div>
          <div v-for="[period, pens] in penaltiesByPeriod" :key="period">
            <div class="flex items-center gap-2 mb-3">
              <div class="h-px bg-border flex-1" />
              <span class="text-xs text-muted-foreground font-medium px-2">{{ game.periodLabel(period) }}</span>
              <div class="h-px bg-border flex-1" />
            </div>
            <div class="space-y-3">
              <div v-for="(p, i) in pens" :key="i" :class="[card, 'border-l-4 border-l-destructive p-3']">
                <div class="flex items-start gap-3">
                  <TeamBadge :name="teamName(p.team)" :side="p.team" size="sm" />
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                      <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary/10 text-primary">{{ game.periodShort(period) }}</span>
                      <span class="text-sm font-semibold text-foreground font-mono">{{ formatTime(p.time) }}</span>
                      <span class="text-xs px-2 py-0.5 rounded bg-destructive/10 text-destructive font-medium">{{ p.pim }} min</span>
                    </div>
                    <p class="text-sm font-semibold text-foreground">{{ p.player ? `#${p.player} ${game.playerName(p.team, p.player)}` : 'Bench penalty' }}</p>
                    <p class="text-xs text-muted-foreground">{{ penaltyName(p.offence) }}</p>
                    <p class="text-xs text-muted-foreground font-mono">{{ formatTime(p.start) }} - {{ formatTime(p.end) }}</p>
                  </div>
                  <div class="flex gap-1 shrink-0">
                    <button :class="btn('ghost', 'icon-sm')" aria-label="Edit" @click="penaltyModal = { team: p.team, editing: p }"><Pencil class="w-4 h-4" /></button>
                    <button :class="btn('ghost', 'icon-sm', 'text-destructive')" aria-label="Delete" @click="game.removePenalty(p)"><Trash2 class="w-4 h-4" /></button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- shots -->
        <div v-else class="mt-4 space-y-3">
          <div v-for="p in shotPeriods" :key="p" :class="[card, 'p-3']">
            <p class="text-xs text-muted-foreground font-medium mb-2">{{ p === 'OVT' ? 'Overtime' : `Period ${p}` }}</p>
            <div class="grid grid-cols-2 gap-3">
              <div v-for="s in (['home', 'away'] as Side[])" :key="s" class="flex items-center justify-between gap-2">
                <span class="text-sm truncate">{{ teamName(s) }}</span>
                <div class="flex items-center gap-1 shrink-0">
                  <button :class="btn('outline', 'icon', 'bg-transparent')" @click="shots(p)[s] = Math.max(0, shots(p)[s] - 1)">−</button>
                  <span class="w-7 text-center font-bold tabular-nums">{{ shots(p)[s] }}</span>
                  <button :class="btn('secondary', 'icon')" @click="shots(p)[s]++">+</button>
                </div>
              </div>
            </div>
          </div>
          <p class="text-center text-sm text-muted-foreground">Total: {{ totalShots('home') }} – {{ totalShots('away') }}</p>
        </div>
      </div>

      <div class="fixed bottom-0 left-0 right-0 p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] bg-background border-t border-border">
        <div class="max-w-4xl mx-auto">
          <button v-if="!confirmEnd" :class="btn('default', 'lg', 'w-full h-12')" @click="confirmEnd = true">End Game & Submit Sheet</button>
          <div v-else class="grid grid-cols-2 gap-2">
            <button :class="btn('outline', 'lg', 'h-12 bg-transparent')" @click="confirmEnd = false">Keep playing</button>
            <button :class="btn('default', 'lg', 'h-12')" @click="endGame">End game</button>
          </div>
        </div>
      </div>

      <GoalModal v-if="goalModal" :game="game" :team="goalModal.team" :editing="goalModal.editing" @close="goalModal = null" />
      <PenaltyModal v-if="penaltyModal" :game="game" :team="penaltyModal.team" :editing="penaltyModal.editing" @close="penaltyModal = null" />
    </template>

    <!-- ================= ENDED ================= -->
    <div v-else-if="report" class="min-h-screen bg-background flex items-center justify-center p-4 pt-[calc(1rem+env(safe-area-inset-top))] pb-[calc(1rem+env(safe-area-inset-bottom))]">
      <div class="max-w-md w-full space-y-6">
        <div class="text-center">
          <CheckCircle class="w-16 h-16 text-green-500 mx-auto mb-4" />
          <h1 class="text-3xl font-bold text-foreground mb-2">Game Complete</h1>
          <p class="text-muted-foreground">Check the sheet and submit it to hockeydata</p>
        </div>

        <div :class="[card, 'p-6']">
          <div class="flex items-center justify-between mb-6">
            <div class="text-center flex-1 min-w-0">
              <p class="text-sm text-muted-foreground truncate">{{ teamName('home') }}</p>
              <p class="text-3xl font-bold text-foreground">{{ game.score.value.home }}</p>
            </div>
            <div class="px-4"><span class="text-2xl font-bold text-muted-foreground">-</span></div>
            <div class="text-center flex-1 min-w-0">
              <p class="text-sm text-muted-foreground truncate">{{ teamName('away') }}</p>
              <p class="text-3xl font-bold text-foreground">{{ game.score.value.away }}</p>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <button :class="btn('outline', 'default', 'bg-transparent')" @click="game.phase.value = 'active'">Back to sheet</button>
            <button :class="btn('default')" @click="router.push(back)">Back to Games</button>
          </div>
        </div>

        <UploadPanel :game="game" :game-id="gameId" />

        <div class="text-center">
          <button v-if="!confirmReset" :class="btn('link', 'sm', 'text-muted-foreground')" @click="confirmReset = true">
            Discard local data and reload from server</button>
          <div v-else class="grid grid-cols-2 gap-2">
            <button :class="btn('outline', 'default', 'bg-transparent')" @click="confirmReset = false">Cancel</button>
            <button :class="btn('destructive')" @click="resetGame">Discard</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
