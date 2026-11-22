<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '../api'
import AppHeader from '../components/AppHeader.vue'
import type { AdminUser } from '../types'
import { btn, card } from '../ui'

const users = ref<AdminUser[]>([])
const loading = ref(true)
const error = ref('')
const busyId = ref<number | null>(null)

const pending = computed(() => users.value.filter((u) => u.role === null))
const approved = computed(() => users.value.filter((u) => u.role !== null))

async function load() {
  try {
    users.value = await api.adminUsers()
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function approve(user: AdminUser) {
  busyId.value = user.id
  error.value = ''
  try {
    await api.setRole(user.id, 'user')
    await load()
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    busyId.value = null
  }
}

const fullName = (u: AdminUser) => `${u.firstname} ${u.lastname}`
const date = (iso: string) => new Date(iso).toLocaleDateString('nl-NL', { day: 'numeric', month: 'short', year: 'numeric' })
</script>

<template>
  <div class="min-h-screen bg-background pb-20 overflow-x-hidden">
    <AppHeader variant="small" title="Beheer" subtitle="Gebruikers" back="/" />

    <div class="px-4 pt-6 max-w-3xl mx-auto space-y-8">
      <p v-if="loading" class="text-center text-muted-foreground py-8">Laden…</p>
      <p v-if="error" class="text-center text-destructive" role="alert">{{ error }}</p>

      <template v-if="!loading">
        <section>
          <h2 class="font-semibold mb-3">Wachten op goedkeuring ({{ pending.length }})</h2>
          <p v-if="!pending.length" class="text-sm text-muted-foreground">Niemand wacht op goedkeuring.</p>
          <div v-else class="space-y-2">
            <div v-for="u in pending" :key="u.id" :class="[card, 'p-4 flex items-center justify-between gap-3']">
              <div class="min-w-0">
                <div class="font-medium">{{ fullName(u) }}</div>
                <div class="text-sm text-muted-foreground truncate">{{ u.email }} · aangemaakt {{ date(u.createdAt) }}</div>
                <div v-if="!u.verified" class="text-xs text-warning">E-mailadres nog niet bevestigd</div>
              </div>
              <button :class="btn('default', 'sm')" :disabled="busyId === u.id" @click="approve(u)">Goedkeuren</button>
            </div>
          </div>
        </section>

        <section>
          <h2 class="font-semibold mb-3">Gebruikers ({{ approved.length }})</h2>
          <div class="space-y-2">
            <div v-for="u in approved" :key="u.id" :class="[card, 'p-4 flex items-center justify-between gap-3']">
              <div class="min-w-0">
                <div class="font-medium">{{ fullName(u) }}</div>
                <div class="text-sm text-muted-foreground truncate">{{ u.email }}</div>
              </div>
              <span class="text-sm text-muted-foreground">{{ u.role === 'administrator' ? 'Beheerder' : 'Gebruiker' }}</span>
            </div>
          </div>
        </section>
      </template>
    </div>
  </div>
</template>
