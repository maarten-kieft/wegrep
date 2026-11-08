<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import type { GameStore } from '../gameStore'
import { meta } from '../meta'
import { formatTime, parseTime } from '../time'
import type { Penalty, Side } from '../types'
import { choice } from '../ui'
import FullScreenModal from './FullScreenModal.vue'

const props = defineProps<{ game: GameStore; team: Side; editing?: Penalty | null }>()
const emit = defineEmits<{ close: [] }>()
const g = props.game

const MINUTES = [2, 4, 5, 10, 20, 25]

const e = props.editing
const startTime = e?.time ?? g.gameTime.value
const period = ref(g.periodOf(startTime))
const timeText = ref(formatTime(startTime))
const startText = ref(e && e.start !== e.time ? formatTime(e.start) : '')
const player = ref(e?.player ?? '')
const pim = ref(e?.pim ?? 2)
const offence = ref(e?.offence ?? '')
const search = ref('')
const error = ref('')

const teamName = computed(() => (props.team === 'home' ? g.report.value?.gamedata.teama : g.report.value?.gamedata.teamb) ?? '')
const players = computed(() => g.lineup(props.team))
const offences = computed(() => {
  const q = search.value.trim().toLowerCase()
  return meta.value.penaltyTypes.filter((t) => !q || t.en.toLowerCase().includes(q) || t.code.toLowerCase().includes(q))
})

function pickPeriod(p: number) {
  period.value = p
  const t = parseTime(timeText.value) ?? 0
  if (g.periodOf(t) !== p) timeText.value = formatTime(g.periodStart(p))
}

function save() {
  const time = parseTime(timeText.value)
  if (time === null) return (error.value = 'Enter the time as MM:SS, e.g. 25:12')
  const start = startText.value.trim() ? parseTime(startText.value) : time
  if (start === null) return (error.value = 'Enter the start time as MM:SS')
  if (!offence.value) return (error.value = 'Select the infraction')
  const penalty: Penalty = {
    team: props.team, time, player: player.value, pim: pim.value, offence: offence.value,
    start, end: start + pim.value * 60,
  }
  if (e) g.updatePenalty(e, penalty)
  else g.addPenalty(penalty)
  emit('close')
}
</script>

<template>
  <FullScreenModal :title="editing ? 'Edit Penalty' : 'Add Penalty'" :subtitle="teamName" tone="destructive"
    :save-label="editing ? 'Save Changes' : 'Add Penalty'" @close="emit('close')" @save="save">
    <template #icon><AlertTriangle class="w-5 h-5" /></template>

    <div class="space-y-2">
      <label class="label">Period</label>
      <div class="grid grid-cols-4 gap-2">
        <button v-for="p in g.totalPeriods.value" :key="p" type="button" :class="choice(period === p)" @click="pickPeriod(p)">
          {{ g.periodShort(p) }}</button>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div class="space-y-2">
        <label class="label" for="pen-time">Game time</label>
        <input id="pen-time" v-model="timeText" class="field text-lg" inputmode="numeric" placeholder="MM:SS" />
      </div>
      <div class="space-y-2">
        <label class="label" for="pen-start">Starts at</label>
        <input id="pen-start" v-model="startText" class="field text-lg" inputmode="numeric" placeholder="same" />
      </div>
    </div>

    <div class="space-y-2">
      <label class="label" for="pen-player">Player</label>
      <select id="pen-player" v-model="player" class="field">
        <option value="">Bench penalty</option>
        <option v-for="p in players" :key="p.id" :value="p.jersey">#{{ p.jersey }} {{ p.lastname }} {{ p.firstname }}</option>
      </select>
    </div>

    <div class="space-y-2">
      <label class="label">Minutes</label>
      <div class="grid grid-cols-6 gap-2">
        <button v-for="m in MINUTES" :key="m" type="button" :class="[choice(pim === m), 'px-1']" @click="pim = m">{{ m }}</button>
      </div>
    </div>

    <div class="space-y-2">
      <label class="label" for="pen-search">Infraction</label>
      <input id="pen-search" v-model="search" class="field" type="search" placeholder="Search, e.g. tripping or TRIP" />
      <div class="max-h-64 overflow-y-auto rounded-md border border-border divide-y divide-border">
        <button v-for="t in offences" :key="t.code" type="button"
          class="w-full flex items-center justify-between gap-2 px-3 py-3 text-left text-sm cursor-pointer"
          :class="offence === t.code ? 'bg-primary text-primary-foreground' : 'hover:bg-secondary'"
          @click="offence = t.code">
          <span>{{ t.en }}</span><span class="text-xs opacity-70 font-mono">{{ t.code }}</span>
        </button>
        <p v-if="!offences.length" class="px-3 py-3 text-sm text-muted-foreground">Nothing found</p>
      </div>
    </div>

    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
  </FullScreenModal>
</template>
