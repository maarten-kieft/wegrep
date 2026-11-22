<script setup lang="ts">
import { Trash2, UserPlus, Users } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import type { Player } from '../types'
import { btn, card } from '../ui'
import { duplicateJerseys, isDressed, jerseyProblem, teamWarnings, validateTeam } from '../validation'
import AddPlayerModal from './AddPlayerModal.vue'

// Roster comes from hockeydata; here the jersey number, position and captaincy are filled in.
// Every player needs a number and the team needs one captain before the game can be started; what is
// still wrong is listed right here. Someone the coach forgot to select can be added from everybody
// registered with the club (the "Last minute selectie-update" of the Windows EGREP).
const props = defineProps<{ name: string; label: string; players: Player[]; logo?: string | null; gameId: number; teamId: number | string }>()
const adding = ref(false)
const addPlayer = (p: Player) => { if (!props.players.some((x) => x.id === p.id)) props.players.push(p) }
const fullName = (p: Player) => `${p.lastname} ${p.firstname}${p.prefix ? ` ${p.prefix}` : ''}`
const broken = ref(false)
watch(() => props.logo, () => { broken.value = false })

// Fixed order (goalies, defence, forwards, others, staff) so rows don't jump while typing a number.
const ORDER: Record<string, number> = { G: 0, D: 1, F: 2, '': 3, TO: 4 }
const sorted = computed(() =>
  [...props.players].sort((a, b) =>
    (ORDER[a.position] ?? 3) - (ORDER[b.position] ?? 3) || a.lastname.localeCompare(b.lastname)))
const dressed = computed(() => props.players.filter(isDressed).length)

const problems = computed(() => validateTeam(props.players))
const warnings = computed(() => teamWarnings(props.players))
const duplicates = computed(() => duplicateJerseys(props.players))
const hasProblem = (p: Player) =>
  duplicates.value.has(p.jersey.trim().toUpperCase()) || jerseyProblem(p) !== null || (p.position !== 'TO' && p.jersey.trim() === '')

/** A player who is not going to play can be taken off the list, as long as he has no number. */
const removable = (p: Player) => p.added || (p.position !== 'TO' && p.jersey.trim() === '')
const remove = (p: Player) => props.players.splice(props.players.indexOf(p), 1)
</script>

<template>
  <div :class="card">
    <div class="px-4 pt-4 pb-3 flex items-center gap-3">
      <div class="w-10 h-10 shrink-0 rounded-full flex items-center justify-center overflow-hidden"
        :class="logo && !broken ? 'bg-white ring-1 ring-border' : 'bg-primary/20'">
        <img v-if="logo && !broken" :src="logo" alt="" loading="lazy"
          class="w-full h-full object-contain p-1" @error="broken = true" />
        <Users v-else class="w-5 h-5 text-primary" />
      </div>
      <div class="min-w-0">
        <h3 class="text-base font-semibold leading-tight">{{ name }}</h3>
        <p class="text-xs text-muted-foreground">{{ dressed }} players dressed</p>
      </div>
      <span class="text-xs text-muted-foreground ml-auto whitespace-nowrap">{{ label }}</span>
    </div>

    <!-- What has to be fixed for this team before the game can be started. -->
    <div v-if="problems.length || warnings.length" class="mx-4 mb-3 rounded-lg px-3 py-2 space-y-1"
      :class="problems.length ? 'bg-destructive/10 border border-destructive/40' : 'bg-warning/10 border border-warning/40'">
      <p v-for="(problem, i) in problems" :key="'p' + i" class="text-sm text-destructive" role="alert">{{ problem }}</p>
      <p v-for="(warning, i) in warnings" :key="'w' + i" class="text-sm text-warning">{{ warning }}</p>
    </div>

    <div class="px-4 pb-4 space-y-2">
      <p v-if="!players.length" class="py-2 text-center text-muted-foreground text-sm">No roster received from hockeydata</p>
      <div v-for="p in sorted" :key="p.id" class="flex items-center gap-2">
        <input v-model.trim="p.jersey" :inputmode="p.position === 'TO' ? 'text' : 'numeric'" maxlength="3" placeholder="#"
          class="w-14 h-10 rounded-md border bg-input text-foreground text-center font-semibold outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
          :class="hasProblem(p) ? 'border-destructive' : 'border-border'" />
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium truncate">{{ fullName(p) }}</p>
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
        <button v-if="removable(p)" type="button" :class="btn('ghost', 'icon-sm', 'shrink-0')" :aria-label="`Remove ${fullName(p)}`" @click="remove(p)">
          <Trash2 class="w-4 h-4" /></button>
      </div>
      <button type="button" :class="btn('outline', 'default', 'w-full bg-transparent mt-2')" @click="adding = true">
        <UserPlus class="w-4 h-4" /> Add player or official</button>
    </div>
    <AddPlayerModal v-if="adding" :game-id="gameId" :team-id="teamId" :team-name="name" :players="players"
      @add="addPlayer" @close="adding = false" />
  </div>
</template>
