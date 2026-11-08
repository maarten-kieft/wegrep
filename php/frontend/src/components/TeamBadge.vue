<script setup lang="ts">
// Round badge with the first three letters of the team, like the poc game cards.
defineProps<{ name: string; side: 'home' | 'away'; size?: 'sm' | 'md' }>()
</script>

<template>
  <div class="rounded-full flex items-center justify-center shrink-0"
    :class="[side === 'home' ? 'bg-primary/20' : 'bg-accent/20', size === 'sm' ? 'w-10 h-10' : 'w-12 h-12']">
    <span class="text-xs font-bold" :class="side === 'home' ? 'text-primary' : 'text-accent'">
      {{ name.replace(/[^\p{L}\p{N}]/gu, '').substring(0, 3).toUpperCase() }}
    </span>
  </div>
</template>
