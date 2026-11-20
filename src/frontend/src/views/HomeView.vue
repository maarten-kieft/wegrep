<script setup lang="ts">
import { asset } from '../ui'
</script>

<template>
  <div class="relative h-screen w-screen overflow-hidden">
    <div class="absolute inset-0 bg-cover bg-center"
      :style="{ backgroundImage: `url(${asset('ice-hockey-arena-dark-blue-dramatic-lighting.jpg')})` }">
      <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-black/70" />
    </div>

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
    </div>
  </div>
</template>
