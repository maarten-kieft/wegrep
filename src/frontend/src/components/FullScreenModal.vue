<script setup lang="ts">
import { X } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted } from 'vue'
import { btn } from '../ui'

// Full screen input page with a fixed save bar, like the poc goal modal. Feels native on a phone.
defineProps<{ title: string; subtitle?: string; saveLabel: string; tone?: 'primary' | 'destructive' }>()
const emit = defineEmits<{ close: []; save: [] }>()

// Keep the page behind from scrolling while the modal is open.
onMounted(() => document.body.classList.add('overflow-hidden'))
onBeforeUnmount(() => document.body.classList.remove('overflow-hidden'))
</script>

<template>
  <div class="fixed inset-0 bg-background z-50 flex flex-col pt-[env(safe-area-inset-top)]">
    <form class="flex-1 flex flex-col min-h-0" @submit.prevent="emit('save')">
      <div class="flex-1 overflow-y-auto overscroll-contain p-4">
        <div class="flex items-center justify-between mb-6 max-w-xl mx-auto">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center"
              :class="tone === 'destructive' ? 'bg-destructive/20 text-destructive' : 'bg-primary/20 text-primary'">
              <slot name="icon" />
            </div>
            <div>
              <h2 class="text-lg font-bold text-foreground">{{ title }}</h2>
              <p v-if="subtitle" class="text-sm text-muted-foreground">{{ subtitle }}</p>
            </div>
          </div>
          <button type="button" :class="btn('ghost', 'icon')" aria-label="Close" @click="emit('close')">
            <X class="w-5 h-5" />
          </button>
        </div>
        <div class="space-y-5 max-w-xl mx-auto">
          <slot />
        </div>
      </div>
      <div class="p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] bg-background border-t border-border">
        <div class="max-w-xl mx-auto">
          <button :class="btn(tone === 'destructive' ? 'destructive' : 'default', 'lg', 'w-full h-12 font-semibold')">{{ saveLabel }}</button>
        </div>
      </div>
    </form>
  </div>
</template>
