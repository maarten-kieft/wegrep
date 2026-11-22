<script setup lang="ts">
import { LoaderCircle, Search, X } from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { api } from '../api'
import type { Eligible, EligibleOfficial, EligiblePlayer, Player } from '../types'
import { btn, card } from '../ui'

// EGREP's "Last minute selectie-update": add someone who is registered with the club to the lineup,
// for when a coach did not enter his lineup. Only people hockeydata knows can be added, no new names.
const props = defineProps<{ gameId: number; teamId: number | string; teamName: string; players: Player[] }>()
const emit = defineEmits<{ close: []; add: [player: Player] }>()

const MAX_OFFICIALS = 8

const tab = ref<'players' | 'officials'>('players')
const query = ref('')
const loading = ref(true)
const error = ref('')
const eligible = ref<Eligible>({ players: [], officials: [] })

onMounted(async () => {
  document.body.classList.add('overflow-hidden')
  try {
    eligible.value = await api.eligible(props.gameId, props.teamId)
  } catch (e) {
    error.value = e instanceof TypeError ? 'No connection: adding players needs the internet.' : (e as Error).message
  } finally {
    loading.value = false
  }
})
onBeforeUnmount(() => document.body.classList.remove('overflow-hidden'))

const inList = (id: string) => props.players.some((p) => p.id === id)
const officialsCount = computed(() => props.players.filter((p) => p.position === 'TO').length)

const norm = (s: string) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
const matches = (...parts: string[]) => {
  const q = norm(query.value.trim())
  return q === '' || norm(parts.join(' ')).includes(q)
}

const players = computed(() => eligible.value.players
  .filter((p) => matches(p.lastname, p.firstname, p.prefix, p.licensenr))
  .sort((a, b) => a.lastname.localeCompare(b.lastname, 'nl') || a.firstname.localeCompare(b.firstname, 'nl')))
const officials = computed(() => eligible.value.officials
  .filter((o) => matches(o.lastname, o.firstname, o.prefix))
  .sort((a, b) => a.lastname.localeCompare(b.lastname, 'nl') || a.firstname.localeCompare(b.firstname, 'nl')))

const displayName = (p: { lastname: string; firstname: string; prefix: string }) =>
  `${p.lastname} ${p.firstname}${p.prefix ? ` ${p.prefix}` : ''}`.trim()
const year = (date: string) => date.slice(0, 4)

function addPlayer(p: EligiblePlayer) {
  emit('add', {
    id: p.id, jersey: '', lastname: p.lastname, firstname: p.firstname, prefix: p.prefix, licensenr: p.licensenr,
    birthdate: p.birthdate, captain: '', position: p.position === 'G' || p.position === 'D' || p.position === 'F' ? p.position : '',
    line: '1', starting: false, added: true,
  })
}

/** HC for the head coach, otherwise the first free T1..T7, as the manual says (never AC or TM). */
function officialNumber(job: string): string {
  const taken = new Set(props.players.filter((p) => p.position === 'TO').map((p) => p.jersey.trim().toUpperCase()))
  if (job === 'HC' && !taken.has('HC')) return 'HC'
  for (let i = 1; i <= 7; i++) if (!taken.has(`T${i}`)) return `T${i}`
  return ''
}

function addOfficial(o: EligibleOfficial) {
  emit('add', {
    id: o.id, jersey: officialNumber(o.job), lastname: o.lastname, firstname: o.firstname, prefix: o.prefix, licensenr: '',
    birthdate: o.birthdate, captain: '', position: 'TO', line: '1', starting: false, added: true,
  })
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[80] bg-black/60 grid place-items-center p-3" role="dialog" aria-modal="true" @click.self="emit('close')">
      <div :class="[card, 'w-full max-w-lg max-h-[90vh] flex flex-col']">
        <div class="p-4 pb-3 flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="text-lg font-bold">Add to lineup</h2>
            <p class="text-sm text-muted-foreground truncate">{{ teamName }}: people registered with the club</p>
          </div>
          <button type="button" :class="btn('ghost', 'icon-sm')" aria-label="Close" @click="emit('close')"><X class="w-4 h-4" /></button>
        </div>

        <div class="px-4 space-y-3">
          <div class="flex gap-2" role="tablist">
            <button role="tab" :aria-selected="tab === 'players'" :class="btn(tab === 'players' ? 'default' : 'outline', 'sm', 'flex-1')"
              @click="tab = 'players'">Players ({{ eligible.players.length }})</button>
            <button role="tab" :aria-selected="tab === 'officials'" :class="btn(tab === 'officials' ? 'default' : 'outline', 'sm', 'flex-1')"
              @click="tab = 'officials'">Team officials ({{ eligible.officials.length }})</button>
          </div>
          <div class="relative">
            <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <input v-model="query" class="field !pl-9 h-11" placeholder="Search by name or license number" autocomplete="off" />
          </div>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-4 py-3 space-y-1.5">
          <p v-if="loading" class="py-8 text-center text-muted-foreground inline-flex w-full items-center justify-center gap-2">
            <LoaderCircle class="w-4 h-4 animate-spin" /> Loading…</p>
          <p v-else-if="error" class="py-6 text-center text-destructive" role="alert">{{ error }}</p>

          <template v-else-if="tab === 'players'">
            <p v-if="!players.length" class="py-6 text-center text-sm text-muted-foreground">Nobody found.</p>
            <div v-for="p in players" :key="p.id" class="flex items-center gap-3 rounded-lg bg-secondary/50 px-3 py-2">
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium truncate">{{ displayName(p) }}</p>
                <p class="text-xs text-muted-foreground truncate">
                  {{ [p.position, p.licensenr, year(p.birthdate)].filter(Boolean).join(' · ') }}
                  <span v-if="p.suspended" class="text-destructive font-medium"> · suspended</span>
                </p>
              </div>
              <span v-if="inList(p.id)" class="text-xs text-muted-foreground">In the list</span>
              <button v-else :class="btn('default', 'sm')" :disabled="p.suspended" @click="addPlayer(p)">Add</button>
            </div>
          </template>

          <template v-else>
            <p v-if="officialsCount >= MAX_OFFICIALS" class="text-sm text-warning pb-1">A team can have at most {{ MAX_OFFICIALS }} team officials.</p>
            <p v-if="!officials.length" class="py-6 text-center text-sm text-muted-foreground">Nobody found.</p>
            <div v-for="o in officials" :key="o.id" class="flex items-center gap-3 rounded-lg bg-secondary/50 px-3 py-2">
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium truncate">{{ displayName(o) }}</p>
                <p class="text-xs text-muted-foreground truncate">{{ [o.job, year(o.birthdate)].filter(Boolean).join(' · ') }}</p>
              </div>
              <span v-if="inList(o.id)" class="text-xs text-muted-foreground">In the list</span>
              <button v-else :class="btn('default', 'sm')" :disabled="officialsCount >= MAX_OFFICIALS" @click="addOfficial(o)">Add</button>
            </div>
          </template>
        </div>

        <div class="p-4 pt-3 border-t border-border">
          <p class="text-xs text-muted-foreground mb-3">Type the jersey number after adding. A player who is not in this list cannot be added: names cannot be created or changed here.</p>
          <button :class="btn('default', 'lg', 'w-full h-11')" @click="emit('close')">Done</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
