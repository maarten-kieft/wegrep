<script setup lang="ts">
import { WifiOff } from 'lucide-vue-next'
import { online, waitingToSync } from '../sessionSync'
</script>

<template>
  <!-- Offline is fine: games keep working from the browser. This only says so. -->
  <div v-if="!online" role="status"
    class="fixed top-2 left-1/2 -translate-x-1/2 z-[60] inline-flex items-center gap-2 rounded-full bg-warning text-warning-foreground px-3 py-1 text-xs font-medium shadow-lg">
    <WifiOff class="w-3.5 h-3.5" />
    Offline, je werk blijft bewaard
    <template v-if="waitingToSync">· {{ waitingToSync }} wacht{{ waitingToSync === 1 ? '' : 'en' }} op synchronisatie</template>
  </div>
</template>
