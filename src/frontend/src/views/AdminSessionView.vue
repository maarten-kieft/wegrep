<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '../api'
import { currentUser } from '../auth'
import AppHeader from '../components/AppHeader.vue'
import TakeOverButton from '../components/TakeOverButton.vue'
import { formatTime } from '../time'
import type { RemoteSession, Side } from '../types'
import { card } from '../ui'

// Read-only look at somebody else's saved game. Nothing here changes the session or copies it to the viewer.
const props = defineProps<{ userId: number; gameId: number }>()
const session = ref<RemoteSession | null>(null)
const error = ref('')

onMounted(async () => {
  try {
    session.value = await api.adminSession(props.userId, props.gameId)
  } catch (e) {
    error.value = (e as Error).message
  }
})

const report = computed(() => session.value?.state.report)
const ruleset = computed(() => session.value?.state.ruleset)
const gd = computed(() => report.value?.gamedata ?? {})
const teamName = (s: Side) => (s === 'home' ? gd.value.teama : gd.value.teamb) ?? ''

/** Period label for a game time in seconds, from the rules of this game. */
function periodShort(time: number) {
  const r = ruleset.value
  if (!r) return ''
  let start = 0
  for (let p = 1; p <= r.PeriodNum + r.OvertimeNum; p++) {
    const length = p <= r.PeriodNum ? r.PeriodLengthSek : r.OvertimeLengthSek
    if (time < start + length || p === r.PeriodNum + r.OvertimeNum) return p <= r.PeriodNum ? `P${p}` : 'OT'
    start += length
  }
  return ''
}
const player = (side: Side, jersey: string) => {
  const p = report.value?.players[side].find((x) => x.jersey === jersey)
  return p ? `#${jersey} ${p.lastname} ${p.firstname}`.trim() : jersey ? `#${jersey}` : ''
}
const goals = computed(() => [...(report.value?.goals ?? [])].sort((a, b) => a.time - b.time))
const penalties = computed(() => [...(report.value?.penalties ?? [])].sort((a, b) => a.time - b.time))
const dressed = (side: Side) => (report.value?.players[side] ?? [])
  .filter((p) => p.jersey.trim() !== '').sort((a, b) => (parseInt(a.jersey) || 999) - (parseInt(b.jersey) || 999))
const when = (ms: number) => new Date(ms).toLocaleString('nl-NL', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
</script>

<template>
  <div class="min-h-screen bg-background pb-20 overflow-x-hidden">
    <AppHeader variant="small" title="Sessie bekijken" :subtitle="session?.title" back="/admin" />

    <div class="px-4 pt-6 max-w-3xl mx-auto space-y-4">
      <p v-if="error" class="text-center text-destructive" role="alert">{{ error }}</p>
      <p v-else-if="!session" class="text-center text-muted-foreground py-8">Laden…</p>

      <template v-else-if="report">
        <div class="rounded-lg bg-warning/10 text-warning p-3 text-sm flex flex-wrap items-center justify-between gap-3">
          <span>Alleen kijken: dit is de sessie van een andere gebruiker. Hier kun je niets wijzigen.</span>
          <TakeOverButton v-if="userId !== currentUser?.id" :user-id="userId" :game-id="gameId" :league-id="session.leagueId"
            :title="session.title" owner="de gebruiker" />
        </div>

        <div :class="[card, 'p-4 text-center space-y-1']">
          <div class="text-sm text-muted-foreground">{{ teamName('home') }} - {{ teamName('away') }}</div>
          <div class="text-4xl font-bold">{{ session.scoreHome }} - {{ session.scoreAway }}</div>
          <div class="text-sm text-muted-foreground">
            {{ session.phase === 'ended' ? 'afgelopen' : session.phase === 'active' ? 'bezig' : 'nog niet gestart' }}
            · laatst bijgewerkt {{ when(session.savedAt) }}
          </div>
        </div>

        <div :class="[card, 'p-4']">
          <h3 class="font-semibold mb-3">Goals ({{ goals.length }})</h3>
          <p v-if="!goals.length" class="text-sm text-muted-foreground">Geen goals.</p>
          <ul class="space-y-2 text-sm">
            <li v-for="(g, i) in goals" :key="i" class="flex gap-3">
              <span class="font-mono text-muted-foreground w-20 shrink-0">{{ periodShort(g.time) }} {{ formatTime(g.time) }}</span>
              <span class="min-w-0">
                <strong>{{ teamName(g.team) }}</strong> {{ player(g.team, g.scorer) }}
                <span v-if="g.assist1 || g.assist2" class="text-muted-foreground">
                  (assist {{ [player(g.team, g.assist1), player(g.team, g.assist2)].filter(Boolean).join(', ') }})</span>
              </span>
            </li>
          </ul>
        </div>

        <div :class="[card, 'p-4']">
          <h3 class="font-semibold mb-3">Penalties ({{ penalties.length }})</h3>
          <p v-if="!penalties.length" class="text-sm text-muted-foreground">Geen penalties.</p>
          <ul class="space-y-2 text-sm">
            <li v-for="(p, i) in penalties" :key="i" class="flex gap-3">
              <span class="font-mono text-muted-foreground w-20 shrink-0">{{ periodShort(p.time) }} {{ formatTime(p.time) }}</span>
              <span class="min-w-0"><strong>{{ teamName(p.team) }}</strong> {{ player(p.team, p.player) }} · {{ p.pim }} min {{ p.offence }}</span>
            </li>
          </ul>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div v-for="side in (['home', 'away'] as Side[])" :key="side" :class="[card, 'p-4']">
            <h3 class="font-semibold mb-3">{{ teamName(side) }}</h3>
            <ul class="space-y-1 text-sm">
              <li v-for="p in dressed(side)" :key="p.id">
                <span class="font-mono text-muted-foreground inline-block w-8">{{ p.jersey }}</span>{{ p.lastname }} {{ p.firstname }}
                <span v-if="p.captain" class="text-muted-foreground"> ({{ p.captain }})</span>
              </li>
            </ul>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
