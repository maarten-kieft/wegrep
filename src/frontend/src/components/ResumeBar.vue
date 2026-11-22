<script setup lang="ts">
import { Play, Trash2 } from 'lucide-vue-next'
import { ref } from 'vue'
import { discardSession } from '../discardSession'
import { listLocal } from '../localGames'
import { btn } from '../ui'

// A strip "Doorgaan met <game>" on the list screens for games in progress on this device,
// with a way to throw the game away (after a confirmation).
const running = ref(listLocal().filter((s) => s.phase === 'active').slice(0, 2))
const confirmId = ref<number | null>(null)
const busy = ref(false)
const error = ref('')

async function discard(gameId: number) {
  busy.value = true
  error.value = ''
  try {
    await discardSession(gameId)
    running.value = running.value.filter((s) => s.gameId !== gameId)
    confirmId.value = null
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div v-if="running.length" class="space-y-1.5 mb-4">
    <div v-for="s in running" :key="s.gameId"
      class="rounded-xl bg-primary/15 border border-primary/30 min-h-14 flex items-stretch">
      <template v-if="confirmId !== s.gameId">
        <RouterLink :to="`/leagues/${s.leagueId}/games/${s.gameId}`"
          class="flex-1 min-w-0 flex items-center gap-3 pl-4 pr-2 py-3.5 text-base rounded-l-xl hover:bg-primary/10 active:bg-primary/20">
          <Play class="w-5 h-5 text-primary shrink-0" />
          <span class="min-w-0">
            <span class="block text-xs uppercase tracking-wide text-muted-foreground">Doorgaan met</span>
            <span class="block font-medium truncate">{{ s.title }}</span>
          </span>
          <span class="ml-auto text-lg font-semibold shrink-0">{{ s.scoreHome }}-{{ s.scoreAway }}</span>
        </RouterLink>
        <button type="button" class="w-14 shrink-0 grid place-items-center rounded-r-xl text-muted-foreground hover:text-destructive hover:bg-destructive/10 cursor-pointer"
          :aria-label="`Gooi ${s.title} weg`" @click="confirmId = s.gameId; error = ''">
          <Trash2 class="w-5 h-5" />
        </button>
      </template>
      <div v-else class="flex-1 px-4 py-3 space-y-2">
        <p class="text-sm">Wedstrijd <strong>{{ s.title }}</strong> weggooien? Dit kan niet ongedaan worden gemaakt.</p>
        <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
        <div class="grid grid-cols-2 gap-2">
          <button :class="btn('outline', 'lg', 'h-11 bg-transparent')" :disabled="busy" @click="confirmId = null">Annuleren</button>
          <button :class="btn('destructive', 'lg', 'h-11')" :disabled="busy" @click="discard(s.gameId)">Weggooien</button>
        </div>
      </div>
    </div>
  </div>
</template>
