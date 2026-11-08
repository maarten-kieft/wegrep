<script setup lang="ts">
import { Pause, Play, RotateCcw } from 'lucide-vue-next'
import type { GameStore } from '../gameStore'
import { formatTime } from '../time'
import { btn, choice } from '../ui'

// Game clock counts up from the start of the game, like the poc timer.
const props = defineProps<{ game: GameStore }>()
const g = props.game
</script>

<template>
  <div class="bg-card/80 border border-border rounded-lg p-3">
    <div class="grid gap-2 mb-3" :style="{ gridTemplateColumns: `repeat(${g.totalPeriods.value}, minmax(0, 1fr))` }">
      <button v-for="p in g.totalPeriods.value" :key="p" :class="[choice(g.clock.period === p), 'p-2']"
        @click="g.setPeriod(p)">{{ g.periodShort(p) }}</button>
    </div>

    <div class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-1">
        <button :class="btn('outline', 'sm', 'h-10 w-10 p-0 text-xs bg-transparent')" @click="g.adjustClock(-60)">-60</button>
        <button :class="btn('outline', 'sm', 'h-10 w-10 p-0 text-xs bg-transparent')" @click="g.adjustClock(-10)">-10</button>
        <span class="hidden sm:flex"><button :class="btn('outline', 'sm', 'h-10 w-9 p-0 text-xs bg-transparent')" @click="g.adjustClock(-1)">-1</button></span>
      </div>
      <div class="text-center">
        <div class="text-3xl font-mono font-bold text-foreground tracking-wider tabular-nums">{{ formatTime(g.gameTime.value) }}</div>
        <div class="text-xs text-muted-foreground">{{ g.periodLabel(g.clock.period) }} · {{ formatTime(g.remainingInPeriod.value) }} left</div>
      </div>
      <div class="flex items-center gap-1">
        <span class="hidden sm:flex"><button :class="btn('outline', 'sm', 'h-10 w-9 p-0 text-xs bg-transparent')" @click="g.adjustClock(1)">+1</button></span>
        <button :class="btn('outline', 'sm', 'h-10 w-10 p-0 text-xs bg-transparent')" @click="g.adjustClock(10)">+10</button>
        <button :class="btn('outline', 'sm', 'h-10 w-10 p-0 text-xs bg-transparent')" @click="g.adjustClock(60)">+60</button>
      </div>
    </div>

    <div class="flex items-center justify-center gap-2 mt-3">
      <button :class="btn('outline', 'sm', 'h-10 bg-transparent')" @click="g.resetClock()">
        <RotateCcw class="w-4 h-4" /> Reset
      </button>
      <button v-if="g.running.value" :class="btn('default', 'sm', 'h-10 px-6 bg-destructive hover:bg-destructive/90')" @click="g.stopClock()">
        <Pause class="w-4 h-4" /> Pause
      </button>
      <button v-else :class="btn('default', 'sm', 'h-10 px-6')" @click="g.startClock()">
        <Play class="w-4 h-4" /> Start
      </button>
    </div>
  </div>
</template>
