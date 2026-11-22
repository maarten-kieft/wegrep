<script setup lang="ts">
import { Play } from 'lucide-vue-next'
import { listLocal } from '../localGames'

// A slim strip "Doorgaan met <game>" on the list screens for games in progress on this device.
const running = listLocal().filter((s) => s.phase === 'active').slice(0, 2)
</script>

<template>
  <div v-if="running.length" class="space-y-1.5 mb-4">
    <RouterLink v-for="s in running" :key="s.gameId" :to="`/leagues/${s.leagueId}/games/${s.gameId}`"
      class="flex items-center gap-3 rounded-xl bg-primary/15 border border-primary/30 px-4 py-3.5 min-h-14 text-base hover:bg-primary/25 active:bg-primary/30">
      <Play class="w-5 h-5 text-primary shrink-0" />
      <span class="min-w-0">
        <span class="block text-xs uppercase tracking-wide text-muted-foreground">Doorgaan met</span>
        <span class="block font-medium truncate">{{ s.title }}</span>
      </span>
      <span class="ml-auto text-lg font-semibold shrink-0">{{ s.scoreHome }}-{{ s.scoreAway }}</span>
    </RouterLink>
  </div>
</template>
