<script setup lang="ts">
import { CloudUpload, Download, LoaderCircle, ServerCrash } from 'lucide-vue-next'
import { computed, ref, toRaw } from 'vue'
import { api } from '../api'
import { downloadText, reportFileName } from '../download'
import type { GameStore } from '../gameStore'
import { meta } from '../meta'
import type { PrecheckResult } from '../types'
import { btn, card } from '../ui'
import { validateSubmit } from '../validation'

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
const blueCode = ref('')
const redCode = ref('')

const addLog = (t: string) => log.value.unshift(`${new Date().toLocaleTimeString('en-GB')}  ${t}`)
const scorekeeper = computed(() => {
  const o = props.game.report.value?.officials.find((x) => x.function.toLowerCase() === 'scorekeeper')
  return o ? `${o.lastname} ${o.firstname}`.trim() : ''
})
const state = () => ({ ...structuredClone(toRaw(props.game.report.value!)), ruleset: structuredClone(toRaw(props.game.ruleset.value)) })
const submitProblems = computed(() => (props.game.report.value ? validateSubmit(props.game.report.value) : []))
const submitting = ref(false)
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
  submitting.value = true
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
    submitting.value = false
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
      <p v-for="(problem, i) in submitProblems" :key="i" class="text-sm text-warning">{{ problem }}</p>
      <p v-if="tie" class="text-sm text-warning">The score is tied. EGREP blocks this when the game has overtime or a shoot-out.</p>
      <button :class="btn('default', 'lg', `w-full h-12 font-semibold ${submitting ? 'disabled:opacity-100' : ''}`)"
        :disabled="busy || submitProblems.length > 0" :aria-busy="submitting" @click="upload">
        <LoaderCircle v-if="submitting" class="w-5 h-5 animate-spin" />
        {{ submitting ? 'Submitting…' : 'Submit Game Sheet' }}</button>
    </template>

    <ul v-if="log.length" class="rounded-lg bg-muted p-3 text-xs font-mono space-y-1 max-h-48 overflow-y-auto">
      <li v-for="(l, i) in log" :key="i">{{ l }}</li>
    </ul>

  </div>
</template>
