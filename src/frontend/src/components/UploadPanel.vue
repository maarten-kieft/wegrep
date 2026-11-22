<script setup lang="ts">
import { CloudUpload, Download, ServerCrash } from 'lucide-vue-next'
import { computed, ref, toRaw } from 'vue'
import { api } from '../api'
import { downloadText, reportFileName } from '../download'
import type { GameStore } from '../gameStore'
import { meta } from '../meta'
import type { PrecheckResult } from '../types'
import { btn, card } from '../ui'

const props = defineProps<{ game: GameStore; gameId: number }>()

const SERVER_STATUS: Record<number, string> = {
  0: 'Not started', 1: 'Live', 2: 'Final (no more uploads)', 3: 'Completed', 4: 'Confirmed',
}
const RESULT: Record<string, string> = {
  '1': 'Live update sent', '3': 'Game sheet uploaded (completed)', '4': 'Game sheet uploaded and confirmed',
}

const log = ref<string[]>([])
const precheck = ref<PrecheckResult | null>(null)
const busy = ref(false)
const confirming = ref(false)
const blueCode = ref('')
const redCode = ref('')

const addLog = (t: string) => log.value.unshift(`${new Date().toLocaleTimeString('en-GB')}  ${t}`)
const scorekeeper = computed(() => {
  const o = props.game.report.value?.officials.find((x) => x.function.toLowerCase() === 'scorekeeper')
  return o ? `${o.lastname} ${o.firstname}`.trim() : ''
})
const state = () => ({ ...structuredClone(toRaw(props.game.report.value!)), ruleset: structuredClone(toRaw(props.game.ruleset.value)) })
const timesFilledIn = computed(() => !!props.game.report.value?.gamestart && !!props.game.report.value?.gameend)
const tie = computed(() => props.game.score.value.home === props.game.score.value.away)

async function check() {
  const gd = props.game.report.value!.gamedata
  try {
    precheck.value = await api.precheck(props.gameId, gd.teamaid ?? '0', gd.teambid ?? '0')
    addLog(precheck.value.GameDoesExist
      ? `Status on server: ${SERVER_STATUS[precheck.value.Status] ?? precheck.value.Status}`
      : 'Game does not exist on the server')
  } catch (e) {
    addLog(`Check failed: ${(e as Error).message}`)
  }
}

/** Save the report as a file (nothing is sent to hockeydata), to import by hand when the normal flow does not work. */
async function downloadXml() {
  busy.value = true
  try {
    const gd = props.game.report.value!.gamedata
    const built = await api.preview(props.gameId, props.game.templateXml.value, state())
    const name = reportFileName(props.gameId, gd.teama ?? '', gd.teamb ?? '')
    downloadText(name, built.xml)
    addLog(`Saved ${name}`)
  } catch (e) {
    addLog(`Download failed: ${(e as Error).message}`)
  } finally {
    busy.value = false
  }
}

async function upload() {
  busy.value = true
  try {
    const res = await api.upload(props.gameId, props.game.templateXml.value, state(), 3,
      scorekeeper.value, blueCode.value, redCode.value)
    let text = RESULT[res.Status] ?? `Server answered with status ${res.Status}`
    if (res.Status === '3' && res.SmsReturnCode) text += ` — confirmation code: ${res.SmsReturnCode.toUpperCase()}`
    addLog(text)
  } catch (e) {
    addLog(`Upload failed: ${(e as Error).message}`)
  } finally {
    busy.value = false
    confirming.value = false
  }
}
</script>

<template>
  <div :class="[card, 'p-4 space-y-4']">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center">
        <CloudUpload class="w-5 h-5 text-primary" />
      </div>
      <div>
        <h3 class="font-semibold">Submit to hockeydata</h3>
        <p class="text-xs text-muted-foreground">Scorekeeper: {{ scorekeeper || 'not filled in' }}</p>
      </div>
    </div>

    <div v-if="!meta.uploadEnabled" class="flex gap-2 rounded-lg bg-warning/10 text-warning p-3 text-sm">
      <ServerCrash class="w-4 h-4 mt-0.5 shrink-0" />
      <span>Uploading is switched off on the server (<code>upload_enabled</code> in config.php). You can still check the status and download the XML.</span>
    </div>

    <button :class="btn('outline', 'lg', 'h-12 bg-transparent w-full')" @click="check">Check status</button>

    <div class="space-y-2">
      <button :class="btn('outline', 'lg', 'h-12 bg-transparent w-full')" :disabled="busy" @click="downloadXml">
        <Download class="w-4 h-4" /> Download XML</button>
      <p class="text-xs text-muted-foreground">
        For importing by hand in the Windows EGREP if submitting here does not work. Nothing is sent to hockeydata.
        Whether EGREP accepts this file is still being checked.
      </p>
    </div>

    <template v-if="meta.uploadEnabled">
      <div class="grid grid-cols-2 gap-3">
        <input v-model="blueCode" class="field" placeholder="Blue code (optional)" />
        <input v-model="redCode" class="field" placeholder="Red code (optional)" />
      </div>
      <p v-if="scorekeeper && !timesFilledIn" class="text-sm text-warning">Fill in the start and end time of the game (Game times above) first.</p>
      <p v-if="!scorekeeper" class="text-sm text-warning">Fill in the scorekeeper (under Officials above) first: EGREP always sends their name with the upload.</p>
      <button v-if="!confirming" :class="btn('default', 'lg', 'w-full h-12 font-semibold')" :disabled="busy || !scorekeeper || !timesFilledIn" @click="confirming = true">
        Submit Game Sheet</button>
      <div v-else class="rounded-lg border border-destructive/50 p-3 space-y-3">
        <p class="text-sm">This sends the official game sheet ({{ game.score.value.home }}–{{ game.score.value.away }}) to hockeydata.
          <strong v-if="tie" class="block mt-1">The score is tied. EGREP blocks this when the game has overtime or a shoot-out.</strong></p>
        <div class="grid grid-cols-2 gap-2">
          <button :class="btn('outline', 'lg', 'h-12 bg-transparent')" @click="confirming = false">Cancel</button>
          <button :class="btn('destructive', 'lg', 'h-12')" :disabled="busy" @click="upload">Yes, submit</button>
        </div>
      </div>
    </template>

    <ul v-if="log.length" class="rounded-lg bg-muted p-3 text-xs font-mono space-y-1 max-h-48 overflow-y-auto">
      <li v-for="(l, i) in log" :key="i">{{ l }}</li>
    </ul>

  </div>
</template>
