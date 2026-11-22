<script setup lang="ts">
import { asset } from '../ui'
import UserMenu from '../components/UserMenu.vue'
import { listLocal } from '../localGames'

// Games in progress on this device: one tap back into the game, also without a connection.
const running = listLocal().filter((s) => s.phase === 'active').slice(0, 3)
</script>

<template>
  <div class="relative h-screen w-screen overflow-hidden">
    <div class="absolute inset-0 bg-cover bg-center"
      :style="{ backgroundImage: `url(${asset('ice-hockey-arena-dark-blue-dramatic-lighting.jpg')})` }">
      <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-black/70" />
    </div>

    <div class="absolute top-4 right-4 z-20"><UserMenu /></div>
    <div class="relative z-10 flex h-full flex-col items-center justify-center gap-8 px-4">
      <h1 class="text-balance text-center font-bold text-white text-5xl md:text-7xl drop-shadow-2xl">
        Ice Hockey Game Sheet
      </h1>
      <p class="text-center text-white/90 text-xl md:text-2xl max-w-2xl drop-shadow-lg">
        Professional game reporting for leagues, teams, and officials
      </p>
      <RouterLink to="/leagues"
        class="mt-8 bg-primary hover:bg-primary/90 text-primary-foreground font-semibold text-xl px-12 py-6 rounded-lg shadow-2xl transition-all hover:scale-105 active:scale-95">
        Start
      </RouterLink>

      <div v-if="running.length" class="w-full max-w-md space-y-2">
        <RouterLink v-for="s in running" :key="s.gameId" :to="`/leagues/${s.leagueId}/games/${s.gameId}`"
          class="block rounded-lg bg-white/15 backdrop-blur px-4 py-3 text-white hover:bg-white/25">
          <div class="text-xs uppercase tracking-wide text-white/70">Doorgaan met</div>
          <div class="font-semibold">{{ s.title }} <span class="font-normal text-white/80">· {{ s.scoreHome }}-{{ s.scoreAway }}</span></div>
        </RouterLink>
      </div>
    </div>
  </div>
</template>
