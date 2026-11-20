<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import AppHeader from '../components/AppHeader.vue'
import type { League } from '../types'
import { card } from '../ui'

const router = useRouter()
const leagues = ref<League[]>([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    leagues.value = await api.leagues()
  } catch (e) {
    error.value = (e as Error).message
  } finally {
    loading.value = false
  }
})

// "IJshockey Nederland - Eredivisie" -> "Eredivisie"
const shortName = (name: string) => name.replace(/^ij\s?shockey nederland\s*-?\s*/i, '') || name
const sorted = computed(() =>
  [...leagues.value].sort((a, b) => shortName(a.name).localeCompare(shortName(b.name), 'nl')))
</script>

<template>
  <div class="min-h-screen bg-background pb-20 overflow-x-hidden">
    <AppHeader variant="large" />

    <div class="px-4 pt-6 max-w-4xl mx-auto">
      <p class="text-muted-foreground text-center mb-6">Select a league to get started</p>

      <p v-if="loading" class="text-center text-muted-foreground py-8">Loading leagues…</p>
      <p v-else-if="error" class="text-center text-destructive py-8">{{ error }}</p>

      <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div v-for="league in sorted" :key="league.id"
          :class="[card, 'cursor-pointer hover:bg-secondary transition-colors active:scale-[0.98]']"
          @click="router.push(`/leagues/${league.id}/games`)">
          <div class="p-4 flex items-center justify-between gap-2">
            <div class="min-w-0">
              <h3 class="font-semibold text-foreground">{{ shortName(league.name) }}</h3>
              <p class="text-sm text-muted-foreground truncate">{{ league.name }}</p>
            </div>
            <ChevronRight class="w-5 h-5 text-muted-foreground shrink-0" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
