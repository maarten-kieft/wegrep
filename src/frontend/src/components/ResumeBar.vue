<script setup lang="ts">
import { Play } from 'lucide-vue-next'
import { listLocal } from '../localGames'

// A slim strip "Doorgaan met <game>" on the list screens for games in progress on this device.
const running = listLocal().filter((s) => s.phase === 'active').slice(0, 2)
</script>

<template>
  <div v-if="running.length" class="space-y-1.5 mb-4">
    <RouterLink v-for="s in running" :key="s.gameId" :to="`/leagues/${s.leagueId}/games/${s.gameId}`"
      class="flex items-center gap-2 rounded-lg bg-primary/15 border border-primary/30 px-3 py-2 text-sm hover:bg-primary/25">
      <Play class="w-4 h-4 text-primary shrink-0" />
      <span class="text-muted-foreground shrink-0">Doorgaan met</span>
      <span class="font-medium truncate">{{ s.title }}</span>
      <span class="ml-auto text-muted-foreground shrink-0">{{ s.scoreHome }}-{{ s.scoreAway }}</span>
    </RouterLink>
  </div>
</template>
