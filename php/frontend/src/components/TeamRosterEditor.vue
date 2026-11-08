<script setup lang="ts">
import { Users } from 'lucide-vue-next'
import { computed } from 'vue'
import type { Player } from '../types'
import { card } from '../ui'

// Roster comes from hockeydata; here the jersey number, position and captaincy are filled in.
// Players without a jersey number are not on the game sheet.
const props = defineProps<{ name: string; label: string; players: Player[] }>()

// Fixed order (goalies, defence, forwards, others, staff) so rows don't jump while typing a number.
const ORDER: Record<string, number> = { G: 0, D: 1, F: 2, '': 3, TO: 4 }
const sorted = computed(() =>
  [...props.players].sort((a, b) =>
    (ORDER[a.position] ?? 3) - (ORDER[b.position] ?? 3) || a.lastname.localeCompare(b.lastname)))
const dressed = computed(() => props.players.filter((p) => p.jersey.trim() && p.position !== 'TO').length)
const duplicates = computed(() => {
  const count = new Map<string, number>()
  for (const p of props.players) if (p.jersey.trim()) count.set(p.jersey.trim(), (count.get(p.jersey.trim()) ?? 0) + 1)
  return new Set([...count].filter(([, n]) => n > 1).map(([j]) => j))
})
</script>

<template>
  <div :class="card">
    <div class="px-4 pt-4 pb-3 flex items-center gap-3">
      <div class="w-10 h-10 shrink-0 rounded-full bg-primary/20 flex items-center justify-center">
        <Users class="w-5 h-5 text-primary" />
      </div>
      <div class="min-w-0">
        <h3 class="text-base font-semibold leading-tight">{{ name }}</h3>
        <p class="text-xs text-muted-foreground">{{ dressed }} players dressed</p>
      </div>
      <span class="text-xs text-muted-foreground ml-auto whitespace-nowrap">{{ label }}</span>
    </div>

    <div class="px-4 pb-4 space-y-2">
      <p v-if="!players.length" class="py-4 text-center text-muted-foreground text-sm">No roster received from hockeydata</p>
      <div v-for="p in sorted" :key="p.id" class="flex items-center gap-2" :class="{ 'opacity-60': !p.jersey.trim() }">
        <input v-model.trim="p.jersey" inputmode="numeric" maxlength="3" placeholder="#"
          class="w-14 h-10 rounded-md border bg-input text-foreground text-center font-semibold outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
          :class="duplicates.has(p.jersey) ? 'border-destructive' : 'border-border'" />
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium truncate">{{ p.lastname }} {{ p.firstname }}</p>
          <p class="text-xs text-muted-foreground truncate">{{ p.position === 'TO' ? 'Team official' : p.licensenr }}</p>
        </div>
        <template v-if="p.position !== 'TO'">
          <select v-model="p.position" class="h-10 w-14 rounded-md border border-border bg-input text-sm text-center">
            <option value="">–</option><option value="G">G</option><option value="D">D</option><option value="F">F</option>
          </select>
          <select v-model="p.captain" class="h-10 w-14 rounded-md border border-border bg-input text-sm text-center">
            <option value="">–</option><option value="C">C</option><option value="A">A</option>
          </select>
        </template>
      </div>
      <p v-if="duplicates.size" class="text-sm text-destructive pt-1">Duplicate jersey number: {{ [...duplicates].join(', ') }}</p>
    </div>
  </div>
</template>
