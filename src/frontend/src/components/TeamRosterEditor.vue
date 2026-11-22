<script setup lang="ts">
import { Trash2, UserPlus, Users } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import type { Player } from '../types'
import { btn, card } from '../ui'
import { captainCount, duplicateJerseys, hasNoPlayers, isDressed, jerseyProblem } from '../validation'
import AddPlayerModal from './AddPlayerModal.vue'

// Roster comes from hockeydata; here the jersey number, position and captaincy are filled in.
// Every player needs a number and the team one captain before the game can be started. What is wrong
// is shown by marking the field red. Someone the coach forgot to select can be added from everybody
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

const noPlayers = computed(() => hasNoPlayers(props.players))
const duplicates = computed(() => duplicateJerseys(props.players))
const numberWrong = (p: Player) =>
  duplicates.value.has(p.jersey.trim().toUpperCase()) || jerseyProblem(p) !== null || (p.position !== 'TO' && p.jersey.trim() === '')
const positionMissing = (p: Player) => isDressed(p) && p.position === ''

// One captain per team, chosen from a list. The other players can be assistant captain (A).
const captainChoices = computed(() =>
  props.players.filter(isDressed).sort((a, b) => (parseInt(a.jersey) || 999) - (parseInt(b.jersey) || 999)))
const captainId = computed({
  get: () => props.players.find((p) => isDressed(p) && p.captain === 'C')?.id ?? '',
  set: (id: string) => {
    for (const p of props.players) {
      if (p.captain === 'C') p.captain = ''
      if (p.id === id) p.captain = 'C'
    }
  },
})
const captainWrong = computed(() => !noPlayers.value && captainCount(props.players) !== 1)

/** A player who is not going to play can be taken off the list, as long as he has no number. */
const removable = (p: Player) => p.added || (p.position !== 'TO' && p.jersey.trim() === '')
const remove = (p: Player) => props.players.splice(props.players.indexOf(p), 1)

const select = 'h-10 w-14 rounded-md border bg-input text-sm text-center'
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

    <div class="px-4 pb-4 space-y-2">
      <p v-if="noPlayers" class="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert">
        {{ players.length ? 'There are no players yet.' : 'No roster received from hockeydata.' }}
        Add them with "Add player or official".</p>

      <div v-for="p in sorted" :key="p.id" class="flex items-center gap-2">
        <input v-model.trim="p.jersey" :inputmode="p.position === 'TO' ? 'text' : 'numeric'" maxlength="3" placeholder="#"
          :aria-invalid="numberWrong(p)"
          class="w-14 h-10 rounded-md border bg-input text-foreground text-center font-semibold outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
          :class="numberWrong(p) ? 'border-destructive' : 'border-border'" />
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium truncate">{{ fullName(p) }}</p>
          <p class="text-xs text-muted-foreground truncate">{{ p.position === 'TO' ? 'Team official' : p.licensenr }}</p>
        </div>
        <template v-if="p.position !== 'TO'">
          <select v-model="p.position" :class="[select, positionMissing(p) ? 'border-warning' : 'border-border']" aria-label="Position">
            <option value="">–</option><option value="G">G</option><option value="D">D</option><option value="F">F</option>
          </select>
          <!-- The captain is chosen below; here a player can be assistant captain. -->
          <select v-model="p.captain" :disabled="p.captain === 'C'" :class="[select, 'border-border']" aria-label="Assistant captain">
            <option value="">–</option><option value="A">A</option><option v-if="p.captain === 'C'" value="C">C</option>
          </select>
        </template>
        <button v-if="removable(p)" type="button" :class="btn('ghost', 'icon-sm', 'shrink-0')" :aria-label="`Remove ${fullName(p)}`" @click="remove(p)">
          <Trash2 class="w-4 h-4" /></button>
      </div>

      <label v-if="!noPlayers" class="flex items-center gap-3 pt-2">
        <span class="text-sm font-medium w-20 shrink-0">Captain</span>
        <select v-model="captainId" :aria-invalid="captainWrong"
          class="h-10 flex-1 min-w-0 rounded-md border bg-input px-2 text-sm" :class="captainWrong ? 'border-destructive' : 'border-border'">
          <option value="">Choose the captain…</option>
          <option v-for="p in captainChoices" :key="p.id" :value="p.id">#{{ p.jersey }} {{ fullName(p) }}</option>
        </select>
      </label>

      <button type="button" :class="btn('outline', 'default', 'w-full bg-transparent mt-2')" @click="adding = true">
        <UserPlus class="w-4 h-4" /> Add player or official</button>
    </div>
    <AddPlayerModal v-if="adding" :game-id="gameId" :team-id="teamId" :team-name="name" :players="players"
      @add="addPlayer" @close="adding = false" />
  </div>
</template>
