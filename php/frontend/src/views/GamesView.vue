<script setup lang="ts">
import { Calendar, ChevronRight, MapPin, ServerCrash } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api, ApiError } from '../api'
import AppHeader from '../components/AppHeader.vue'
import TeamBadge from '../components/TeamBadge.vue'
import { storage } from '../storage'
import type { GameListItem } from '../types'
import { btn, card } from '../ui'

const props = defineProps<{ leagueId: number }>()
const router = useRouter()

const FILTERS = [
  { value: 'TODAY_FUTURE', label: 'Today & Future' },
  { value: 'TODAY', label: 'Today' },
  { value: 'TODAY_2DAYSBEFORE', label: 'Last 3 days' },
  { value: 'ALL', label: 'All Games' },
]
const STATUS: Record<number, { label: string; cls: string }> = {
  1: { label: 'Live', cls: 'bg-destructive/15 text-destructive' },
  2: { label: 'Final', cls: 'bg-warning/15 text-warning' },
  3: { label: 'Completed', cls: 'bg-success/15 text-success' },
  4: { label: 'Confirmed', cls: 'bg-success/15 text-success' },
}

const filter = ref(storage.get('filter') ?? 'TODAY_FUTURE')
const games = ref<GameListItem[]>([])
const loading = ref(false)
const error = ref('')
// The league password is a server secret (config.php), not entered in the app.
const serverNeedsPassword = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  serverNeedsPassword.value = false
  storage.set('filter', filter.value)
  try {
    games.value = await api.games(props.leagueId, filter.value)
  } catch (e) {
    games.value = []
    // hockeydata answers "Permission denied" when the server's league password is missing or wrong
    if (e instanceof ApiError && e.status === 403) serverNeedsPassword.value = true
    else error.value = (e as Error).message
  } finally {
    loading.value = false
  }
}
watch([() => props.leagueId, filter], load, { immediate: true })

const sorted = computed(() =>
  [...games.value].sort((a, b) => (a.scheduled ?? '').localeCompare(b.scheduled ?? '')))

const fmtDate = (d: string | null) => d ? new Date(d).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }) : ''
const fmtTime = (d: string | null) => d ? new Date(d).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : ''
const inProgress = (id: number) => storage.get(`game:${id}`) !== null
</script>

<template>
  <div class="min-h-screen bg-background pb-[calc(5rem+env(safe-area-inset-bottom))] overflow-x-hidden">
    <AppHeader variant="large" back="/leagues" />

    <div class="px-4 pt-6 max-w-4xl mx-auto">
      <p class="text-muted-foreground text-center mb-6">Select a game</p>

      <!-- The league password lives on the server; show a hint if it isn't set there. -->
      <div v-if="serverNeedsPassword" :class="[card, 'p-6 max-w-md mx-auto']">
        <div class="flex gap-3">
          <div class="w-10 h-10 shrink-0 rounded-full bg-warning/20 flex items-center justify-center">
            <ServerCrash class="w-5 h-5 text-warning" />
          </div>
          <div class="space-y-1">
            <h2 class="font-semibold">League password not accepted</h2>
            <p class="text-sm text-muted-foreground">
              hockeydata rejected the server's league password. Set <code>league_password</code> in
              <code>config.php</code> on the server (or the <code>WEGREP_LEAGUE_PASSWORD</code> environment variable).
            </p>
          </div>
        </div>
      </div>

      <div v-else class="space-y-4">
        <div class="flex gap-2 pb-2 overflow-x-auto -mx-4 px-4 [scrollbar-width:none]">
          <button v-for="f in FILTERS" :key="f.value"
            :class="filter === f.value ? btn('default', 'sm') : btn('outline', 'sm', 'bg-transparent')"
            @click="filter = f.value">{{ f.label }}</button>
        </div>

        <p v-if="loading" class="text-center py-8 text-muted-foreground">Loading games…</p>
        <p v-else-if="error" class="text-center py-8 text-destructive">{{ error }}</p>

        <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <div v-if="!sorted.length" class="text-center py-8 text-muted-foreground md:col-span-2">
            <p>No games found for this filter</p>
          </div>
          <div v-for="game in sorted" :key="game.id"
            :class="[card, 'cursor-pointer hover:bg-secondary transition-colors active:scale-[0.98]']"
            @click="router.push(`/leagues/${leagueId}/games/${game.id}`)">
            <div class="p-4">
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                  <Calendar class="w-4 h-4" />
                  <span>{{ fmtDate(game.scheduled) }}</span>
                  <span>•</span>
                  <span>{{ fmtTime(game.scheduled) }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <span v-if="inProgress(game.id)" class="text-xs px-2 py-0.5 rounded bg-primary/10 text-primary font-medium">In progress</span>
                  <span v-if="STATUS[game.status]" class="text-xs px-2 py-0.5 rounded font-medium" :class="STATUS[game.status].cls">
                    {{ STATUS[game.status].label }}</span>
                  <ChevronRight class="w-5 h-5 text-muted-foreground" />
                </div>
              </div>

              <div class="flex items-center justify-between">
                <div class="flex-1 text-center min-w-0">
                  <TeamBadge :name="game.home" side="home" class="mx-auto mb-2" />
                  <p class="font-semibold text-foreground text-sm">{{ game.home }}</p>
                  <p class="text-xs text-muted-foreground">Home</p>
                </div>
                <div class="px-4"><span class="text-xl font-bold text-muted-foreground">VS</span></div>
                <div class="flex-1 text-center min-w-0">
                  <TeamBadge :name="game.away" side="away" class="mx-auto mb-2" />
                  <p class="font-semibold text-foreground text-sm">{{ game.away }}</p>
                  <p class="text-xs text-muted-foreground">Away</p>
                </div>
              </div>

              <div class="flex items-center justify-center gap-2 mt-3 text-xs text-muted-foreground">
                <MapPin class="w-3 h-3" />
                <span>{{ game.location }}</span>
                <span v-if="game.name">• {{ game.name }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
