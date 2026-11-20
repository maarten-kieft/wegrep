<script setup lang="ts">
import { ref, watch } from 'vue'
// Club logo when we have one (from ijshockey.nl), otherwise the first three
// letters of the team, like the poc game cards.
const props = defineProps<{ name: string; side: 'home' | 'away'; size?: 'sm' | 'md'; logo?: string | null }>()
const broken = ref(false)
watch(() => props.logo, () => { broken.value = false })
</script>

<template>
  <div class="rounded-full flex items-center justify-center shrink-0 overflow-hidden"
    :class="[
      logo && !broken ? 'bg-white ring-1 ring-border' : (side === 'home' ? 'bg-primary/20' : 'bg-accent/20'),
      size === 'sm' ? 'w-10 h-10' : 'w-12 h-12',
    ]">
    <img v-if="logo && !broken" :src="logo" alt="" loading="lazy"
      class="w-full h-full object-contain p-1" @error="broken = true" />
    <span v-else class="text-xs font-bold" :class="side === 'home' ? 'text-primary' : 'text-accent'">
      {{ name.replace(/[^\p{L}\p{N}]/gu, '').substring(0, 3).toUpperCase() }}
    </span>
  </div>
</template>
