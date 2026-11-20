<script setup lang="ts">
import { Target } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import type { GameStore } from '../gameStore'
import { formatTime, parseTime } from '../time'
import type { Goal, Side } from '../types'
import { choice } from '../ui'
import FullScreenModal from './FullScreenModal.vue'

const props = defineProps<{ game: GameStore; team: Side; editing?: Goal | null }>()
const emit = defineEmits<{ close: [] }>()
const g = props.game

const SITUATIONS = [
  { value: 0, label: 'EQ' }, { value: 1, label: 'PP1' }, { value: 2, label: 'PP2' },
  { value: -1, label: 'SH1' }, { value: -2, label: 'SH2' },
]
const SPECIALS = [
  { value: '', label: 'Regular' }, { value: 'EN', label: 'Empty net' },
  { value: 'PS', label: 'Penalty shot' }, { value: 'AG', label: 'Awarded' },
]

const e = props.editing
const startTime = e?.time ?? g.gameTime.value
const period = ref(g.periodOf(startTime))
const timeText = ref(formatTime(startTime))
const scorer = ref(e?.scorer ?? '')
const assist1 = ref(e?.assist1 ?? '')
const assist2 = ref(e?.assist2 ?? '')
const strength = ref(e?.strength ?? 0)
const special = ref(e?.special ?? '')
const error = ref('')

const teamName = computed(() => (props.team === 'home' ? g.report.value?.gamedata.teama : g.report.value?.gamedata.teamb) ?? '')
const players = computed(() => g.lineup(props.team).filter((p) => p.position !== 'TO'))

// Picking another period moves the time into that period.
function pickPeriod(p: number) {
  period.value = p
  const t = parseTime(timeText.value) ?? 0
  if (g.periodOf(t) !== p) timeText.value = formatTime(g.periodStart(p))
}

function save() {
  const time = parseTime(timeText.value)
  if (time === null) return (error.value = 'Enter the time as MM:SS, e.g. 25:12')
  if (!scorer.value) return (error.value = 'Select the goal scorer')
  const picked = [scorer.value, assist1.value, assist2.value].filter(Boolean)
  if (new Set(picked).size !== picked.length) return (error.value = 'A player can only be picked once')
  const goal: Goal = {
    team: props.team, time, scorer: scorer.value, assist1: assist1.value,
    assist2: assist1.value ? assist2.value : '', strength: strength.value, special: special.value,
  }
  if (e) g.updateGoal(e, goal)
  else g.addGoal(goal)
  emit('close')
}
</script>

<template>
  <FullScreenModal :title="editing ? 'Edit Goal' : 'Add Goal'" :subtitle="teamName"
    :save-label="editing ? 'Save Changes' : 'Add Goal'" @close="emit('close')" @save="save">
    <template #icon><Target class="w-5 h-5" /></template>

    <div class="space-y-2">
      <label class="label">Period</label>
      <div class="grid grid-cols-4 gap-2">
        <button v-for="p in g.totalPeriods.value" :key="p" type="button" :class="choice(period === p)" @click="pickPeriod(p)">
          {{ g.periodShort(p) }}</button>
      </div>
    </div>

    <div class="space-y-2">
      <label class="label" for="goal-time">Game time (MM:SS)</label>
      <input id="goal-time" v-model="timeText" class="field text-lg" inputmode="numeric" placeholder="25:12" />
      <p class="text-xs text-muted-foreground">Time since the start of the game, as on the clock.</p>
    </div>

    <p v-if="!players.length" class="text-sm text-warning">No jersey numbers yet for {{ teamName }}. Fill them in on the setup screen.</p>

    <div class="space-y-2">
      <label class="label" for="goal-scorer">Goal Scorer *</label>
      <select id="goal-scorer" v-model="scorer" class="field">
        <option value="">Select player</option>
        <option v-for="p in players" :key="p.id" :value="p.jersey">#{{ p.jersey }} {{ p.lastname }} {{ p.firstname }}</option>
      </select>
    </div>
    <div class="space-y-2">
      <label class="label" for="goal-a1">First Assist (optional)</label>
      <select id="goal-a1" v-model="assist1" class="field">
        <option value="">No assist</option>
        <option v-for="p in players" :key="p.id" :value="p.jersey">#{{ p.jersey }} {{ p.lastname }} {{ p.firstname }}</option>
      </select>
    </div>
    <div class="space-y-2">
      <label class="label" for="goal-a2">Second Assist (optional)</label>
      <select id="goal-a2" v-model="assist2" class="field" :disabled="!assist1">
        <option value="">No assist</option>
        <option v-for="p in players" :key="p.id" :value="p.jersey">#{{ p.jersey }} {{ p.lastname }} {{ p.firstname }}</option>
      </select>
    </div>

    <div class="space-y-2">
      <label class="label">Situation</label>
      <div class="grid grid-cols-5 gap-2">
        <button v-for="s in SITUATIONS" :key="s.value" type="button" :class="[choice(strength === s.value), 'px-1']"
          @click="strength = s.value">{{ s.label }}</button>
      </div>
    </div>
    <div class="space-y-2">
      <label class="label">Type</label>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
        <button v-for="s in SPECIALS" :key="s.value" type="button" :class="choice(special === s.value)"
          @click="special = s.value">{{ s.label }}</button>
      </div>
    </div>

    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
  </FullScreenModal>
</template>
