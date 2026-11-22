<script setup lang="ts">
import type { Official } from '../types'

// The officials of a game (referees, scorekeeper...), edited in place. Shown on the setup screen and on the
// end screen, where the scorekeeper may still have to be filled in before submitting.
defineProps<{ officials: Official[] }>()

const LABELS: Record<string, string> = {
  referee1: 'Referee 1', referee2: 'Referee 2', linesman1: 'Linesman 1', linesman2: 'Linesman 2', scorekeeper: 'Scorekeeper',
  timekeeper: 'Timekeeper', goaljudge1: 'Goal judge 1', goaljudge2: 'Goal judge 2', gamesupervisor: 'Supervisor',
}
const label = (fn: string) => LABELS[fn]
const shown = (officials: Official[]) => officials.filter((o) => LABELS[o.function])
</script>

<template>
  <div class="space-y-3">
    <div v-for="o in shown(officials)" :key="o.function" class="grid grid-cols-[6.5rem_1fr_1fr] gap-2 items-center">
      <span class="text-sm text-muted-foreground">{{ label(o.function) }}</span>
      <input v-model="o.lastname" class="field h-10 text-sm" placeholder="Last name" />
      <input v-model="o.firstname" class="field h-10 text-sm" placeholder="First name" />
    </div>
  </div>
</template>
