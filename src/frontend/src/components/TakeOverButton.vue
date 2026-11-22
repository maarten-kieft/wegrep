<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { currentUser } from '../auth'
import { readLocal, summarize, writeLocal, removeLocal } from '../localGames'
import { clearDirty, markDirty } from '../sessionSync'
import type { SessionSummary } from '../types'
import { btn, card } from '../ui'

// An administrator takes over somebody else's session: it moves to the administrator, the owner no longer
// has it. Asks first, and warns when the administrator has a session of their own for the same game,
// because there is room for one per game and that one is replaced.
const props = defineProps<{
  userId: number
  gameId: number
  leagueId: number
  title: string
  owner: string
  size?: 'sm' | 'default'
}>()
const router = useRouter()

const open = ref(false)
const busy = ref(false)
const error = ref('')
const ownSession = ref<SessionSummary | null>(null)

const when = (ms: number) => new Date(ms).toLocaleString('nl-NL', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })

async function ask() {
  error.value = ''
  ownSession.value = null
  open.value = true
  // Does the administrator have a started session of their own for this game, here or on the server?
  const local = readLocal(props.gameId)
  const remote = await api.session(props.gameId).catch(() => null)
  const own = remote && remote.savedAt >= (local?.savedAt ?? 0) ? remote : local && local.phase !== 'setup' ? summarize(props.gameId, local) : remote
  if (own && own.phase !== 'setup') ownSession.value = own
}

async function takeOver() {
  busy.value = true
  error.value = ''
  try {
    const taken = await api.takeOverSession(props.userId, props.gameId)
    // Our own copy of this game (if any) was replaced on the server: replace it here too.
    removeLocal(props.gameId)
    clearDirty(props.gameId)
    writeLocal(props.gameId, { ...taken.state, leagueId: taken.leagueId, userId: currentUser.value?.id, savedAt: Date.now() })
    markDirty(props.gameId)
    await router.push(`/leagues/${taken.leagueId}/games/${taken.gameId}?resume=1`)
  } catch (e) {
    error.value = e instanceof TypeError ? 'Geen verbinding met de server.' : (e as Error).message
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <button type="button" :class="btn('outline', size ?? 'default')" @click="ask">Overnemen</button>

  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[80] bg-black/60 grid place-items-center p-4" role="dialog" aria-modal="true">
      <div :class="[card, 'w-full max-w-md p-6 space-y-4']">
        <h2 class="text-lg font-bold">Sessie overnemen?</h2>
        <p class="text-sm">
          <strong>{{ title }}</strong> van {{ owner }} wordt naar jou verplaatst. {{ owner }} ziet deze wedstrijd daarna niet meer,
          ook niet op andere apparaten.
        </p>
        <div v-if="ownSession" class="rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-sm">
          <strong>Let op:</strong> je hebt zelf ook een sessie voor deze wedstrijd (stand {{ ownSession.scoreHome }}-{{ ownSession.scoreAway }},
          laatst bijgewerkt {{ when(ownSession.savedAt) }}). Een wedstrijd kan maar één sessie per gebruiker hebben:
          als je overneemt raakt jouw eigen sessie verloren.
        </div>
        <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
        <div class="grid grid-cols-2 gap-2">
          <button :class="btn('outline', 'lg', 'h-12 bg-transparent')" :disabled="busy" @click="open = false">Annuleren</button>
          <button :class="btn('destructive', 'lg', 'h-12')" :disabled="busy" @click="takeOver">
            {{ ownSession ? 'Overnemen en vervangen' : 'Overnemen' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
