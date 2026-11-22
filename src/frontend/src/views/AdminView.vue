<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { api } from '../api'
import { currentUser } from '../auth'
import AppHeader from '../components/AppHeader.vue'
import type { AdminSession, AdminUser } from '../types'
import { btn, card } from '../ui'

const tab = ref<'users' | 'sessions'>('users')
const users = ref<AdminUser[]>([])
const sessions = ref<AdminSession[]>([])
const loading = ref(true)
const error = ref('')
const busyId = ref<number | null>(null)
const confirmRemove = ref<number | null>(null)

const pending = computed(() => users.value.filter((u) => u.role === null))
const approved = computed(() => users.value.filter((u) => u.role !== null))
const isMe = (u: AdminUser) => u.id === currentUser.value?.id

async function load() {
  try {
    ;[users.value, sessions.value] = await Promise.all([api.adminUsers(), api.adminSessions()])
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function act(user: AdminUser, action: () => Promise<unknown>) {
  busyId.value = user.id
  error.value = ''
  try {
    await action()
    confirmRemove.value = null
    await load()
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    busyId.value = null
  }
}

const setRole = (user: AdminUser, role: 'user' | 'administrator') => act(user, () => api.setRole(user.id, role))
const remove = (user: AdminUser) => act(user, () => api.removeUser(user.id))

const fullName = (u: { firstname: string; lastname: string }) => `${u.firstname} ${u.lastname}`
const date = (iso: string) => new Date(iso).toLocaleDateString('nl-NL', { day: 'numeric', month: 'short', year: 'numeric' })
const when = (ms: number) => new Date(ms).toLocaleString('nl-NL', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
</script>

<template>
  <div class="min-h-screen bg-background pb-20 overflow-x-hidden">
    <AppHeader variant="small" title="Beheer" :subtitle="tab === 'users' ? 'Gebruikers' : 'Sessies van alle gebruikers'" back="/" />

    <div class="px-4 pt-6 max-w-3xl mx-auto space-y-6">
      <div class="flex gap-2" role="tablist">
        <button role="tab" :aria-selected="tab === 'users'" :class="btn(tab === 'users' ? 'default' : 'outline', 'default', 'flex-1')"
          @click="tab = 'users'">Gebruikers<span v-if="pending.length"> ({{ pending.length }} wacht)</span></button>
        <button role="tab" :aria-selected="tab === 'sessions'" :class="btn(tab === 'sessions' ? 'default' : 'outline', 'default', 'flex-1')"
          @click="tab = 'sessions'">Sessies ({{ sessions.length }})</button>
      </div>

      <p v-if="loading" class="text-center text-muted-foreground py-8">Laden…</p>
      <p v-if="error" class="text-center text-destructive" role="alert">{{ error }}</p>

      <!-- ================= SESSIONS ================= -->
      <section v-if="!loading && tab === 'sessions'">
        <p v-if="!sessions.length" class="text-sm text-muted-foreground">Er zijn nog geen sessies.</p>
        <div class="space-y-2">
          <div v-for="x in sessions" :key="x.user.id + '-' + x.gameId" :class="[card, 'p-4 flex flex-wrap items-center justify-between gap-3']">
            <div class="min-w-0">
              <div class="font-medium truncate">{{ x.title }}</div>
              <div class="text-sm text-muted-foreground">
                Stand {{ x.scoreHome }}-{{ x.scoreAway }} · {{ x.phase === 'ended' ? 'afgelopen' : 'bezig' }} · {{ when(x.savedAt) }}
              </div>
              <div class="text-sm text-muted-foreground truncate">{{ fullName(x.user) }} · {{ x.user.email }}</div>
            </div>
            <!-- A separate browser tab, so the list stays where it is. -->
            <RouterLink :to="`/admin/sessions/${x.user.id}/${x.gameId}`" target="_blank" :class="btn('default', 'sm')">Bekijken</RouterLink>
          </div>
        </div>
      </section>

      <!-- ================= USERS ================= -->
      <template v-if="!loading && tab === 'users'">
        <section>
          <h2 class="font-semibold mb-3">Wachten op goedkeuring ({{ pending.length }})</h2>
          <p v-if="!pending.length" class="text-sm text-muted-foreground">Niemand wacht op goedkeuring.</p>
          <div v-else class="space-y-2">
            <div v-for="u in pending" :key="u.id" :class="[card, 'p-4 flex flex-wrap items-center justify-between gap-3']">
              <div class="min-w-0">
                <div class="font-medium">{{ fullName(u) }}</div>
                <div class="text-sm text-muted-foreground truncate">{{ u.email }} · aangemaakt {{ date(u.createdAt) }}</div>
                <div v-if="!u.verified" class="text-xs text-warning">E-mailadres nog niet bevestigd</div>
              </div>
              <div class="flex gap-2">
                <button :class="btn('default', 'sm')" :disabled="busyId === u.id" @click="setRole(u, 'user')">Goedkeuren</button>
                <button v-if="confirmRemove !== u.id" :class="btn('outline', 'sm')" @click="confirmRemove = u.id">Afwijzen</button>
                <button v-else :class="btn('destructive', 'sm')" :disabled="busyId === u.id" @click="remove(u)">Zeker weten?</button>
              </div>
            </div>
          </div>
        </section>

        <section>
          <h2 class="font-semibold mb-3">Gebruikers ({{ approved.length }})</h2>
          <div class="space-y-2">
            <div v-for="u in approved" :key="u.id" :class="[card, 'p-4 flex flex-wrap items-center justify-between gap-3']">
              <div class="min-w-0">
                <div class="font-medium">{{ fullName(u) }}<span v-if="isMe(u)" class="text-muted-foreground font-normal"> (jij)</span></div>
                <div class="text-sm text-muted-foreground truncate">{{ u.email }}</div>
              </div>
              <div class="flex items-center gap-2">
                <select class="field !w-auto h-8 py-0" :value="u.role" :disabled="isMe(u) || busyId === u.id"
                  :aria-label="`Rol van ${fullName(u)}`" @change="setRole(u, ($event.target as HTMLSelectElement).value as 'user' | 'administrator')">
                  <option value="user">Gebruiker</option>
                  <option value="administrator">Beheerder</option>
                </select>
                <template v-if="!isMe(u)">
                  <button v-if="confirmRemove !== u.id" :class="btn('outline', 'icon-sm')" :aria-label="`Verwijder ${fullName(u)}`"
                    @click="confirmRemove = u.id"><Trash2 class="w-4 h-4" /></button>
                  <button v-else :class="btn('destructive', 'sm')" :disabled="busyId === u.id" @click="remove(u)">Zeker weten?</button>
                </template>
              </div>
            </div>
          </div>
        </section>
      </template>
    </div>
  </div>
</template>
