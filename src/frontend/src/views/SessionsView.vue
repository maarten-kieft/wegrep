<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next'
import { onMounted, ref } from 'vue'
import { api } from '../api'
import AppHeader from '../components/AppHeader.vue'
import { discardSession } from '../discardSession'
import { listLocal } from '../localGames'
import type { SessionSummary } from '../types'
import { btn, card } from '../ui'

// The games this user has started: the ones in this browser plus those on the server (from other devices).
// Offline you just see the ones in this browser.
const sessions = ref<SessionSummary[]>(listLocal())
const loading = ref(true)
const error = ref('')
const confirmId = ref<number | null>(null)

onMounted(async () => {
  try {
    const remote = (await api.sessions()).filter((s) => s.phase !== 'setup')
    const merged = new Map(sessions.value.map((s) => [s.gameId, s]))
    for (const r of remote) {
      const mine = merged.get(r.gameId)
      if (!mine || r.savedAt > mine.savedAt) merged.set(r.gameId, r)
    }
    sessions.value = [...merged.values()].sort((a, b) => b.savedAt - a.savedAt)
  } catch {
    /* offline: the local list will do */
  } finally {
    loading.value = false
  }
})

async function remove(s: SessionSummary) {
  error.value = ''
  try {
    await discardSession(s.gameId)
  } catch (e) {
    error.value = (e as Error).message
    confirmId.value = null
    return
  }
  sessions.value = sessions.value.filter((x) => x.gameId !== s.gameId)
  confirmId.value = null
}

const when = (ms: number) => new Date(ms).toLocaleString('nl-NL', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
</script>

<template>
  <div class="min-h-screen bg-background pb-20 overflow-x-hidden">
    <AppHeader variant="small" title="Mijn wedstrijden" subtitle="Gestarte wedstrijdverslagen" back="/" />

    <div class="px-4 pt-6 max-w-3xl mx-auto space-y-3">
      <p v-if="error" class="text-center text-destructive" role="alert">{{ error }}</p>
      <p v-if="!sessions.length && !loading" class="text-center text-muted-foreground py-8">
        Je hebt nog geen wedstrijd gestart. Kies een wedstrijd en druk op Start Game.
      </p>

      <div v-for="s in sessions" :key="s.gameId" :class="[card, 'p-4 flex flex-wrap items-center justify-between gap-3']">
        <div class="min-w-0">
          <div class="font-medium truncate">{{ s.title }}</div>
          <div class="text-sm text-muted-foreground">
            Stand {{ s.scoreHome }}-{{ s.scoreAway }} · {{ s.phase === 'ended' ? 'afgelopen' : 'bezig' }} · {{ when(s.savedAt) }}
          </div>
        </div>
        <div class="flex items-center gap-2">
          <RouterLink :to="`/leagues/${s.leagueId}/games/${s.gameId}?resume=1`" :class="btn('default', 'sm')">Openen</RouterLink>
          <button v-if="confirmId !== s.gameId" :class="btn('outline', 'icon-sm')" :aria-label="`Verwijder ${s.title}`"
            @click="confirmId = s.gameId"><Trash2 class="w-4 h-4" /></button>
          <button v-else :class="btn('destructive', 'sm')" @click="remove(s)">Zeker weten?</button>
        </div>
      </div>
    </div>
  </div>
</template>
