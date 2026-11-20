<script setup lang="ts">
import { ChevronLeft } from 'lucide-vue-next'
import { asset } from '../ui'

// "large": the big header of the league/game lists. "small": the game screens, with a back button.
defineProps<{ variant: 'large' | 'small'; title?: string; subtitle?: string; back?: string }>()
</script>

<template>
  <div v-if="variant === 'large'" class="relative h-56 overflow-hidden">
    <div class="absolute inset-0 bg-cover bg-center"
      :style="{ backgroundImage: `url('${asset('ice-hockey-arena-dark-blue-dramatic-lighting.jpg')}')` }" />
    <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-background" />
    <RouterLink v-if="back" :to="back"
      class="absolute top-4 left-4 z-20 inline-flex items-center h-8 px-3 rounded-md text-sm font-medium text-white hover:bg-white/20">
      <ChevronLeft class="w-4 h-4 mr-1" /> Back
    </RouterLink>
    <div class="relative z-10 flex flex-col items-center justify-center h-full px-4 text-center">
      <div class="flex items-center gap-3 mb-2">
        <svg class="w-10 h-10 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" />
          <path d="M8 14s1.5 2 4 2 4-2 4-2" />
          <path d="M9 9h.01M15 9h.01" />
          <circle cx="12" cy="12" r="8" />
          <path d="M2 12h4M18 12h4" stroke-linecap="round" />
        </svg>
        <h1 class="text-3xl font-bold text-white tracking-tight">Hockey Sheet</h1>
      </div>
      <p class="text-blue-200 text-sm">{{ subtitle ?? 'Game reporting made simple' }}</p>
    </div>
  </div>

  <div v-else class="relative h-28 bg-cover bg-center"
    :style="{ backgroundImage: `url('${asset('ice-hockey-arena-dark-blue-dramatic-lighting.jpg')}')` }">
    <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-background" />
    <div class="absolute top-4 left-4 right-4 flex items-center justify-between">
      <RouterLink v-if="back" :to="back"
        class="inline-flex items-center h-8 px-3 rounded-md text-sm font-medium text-white hover:bg-white/20">
        <ChevronLeft class="w-4 h-4 mr-1" /> Back
      </RouterLink>
      <slot name="actions" />
    </div>
    <div class="absolute bottom-4 left-0 right-0 text-center">
      <h1 class="text-xl font-bold text-white">{{ title }}</h1>
      <p v-if="subtitle" class="text-white/70 text-sm">{{ subtitle }}</p>
    </div>
  </div>
</template>
