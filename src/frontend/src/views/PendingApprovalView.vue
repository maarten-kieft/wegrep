<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { api } from '../api'
import { currentUser, setUser, signOut } from '../auth'
import { btn, card } from '../ui'

// Shown instead of the app while an account has no role yet. Checks every half minute (and when
// the connection comes back) whether an administrator has approved it; then the app opens by itself.
const checking = ref(false)
const stillWaiting = ref(false)
let timer: ReturnType<typeof setInterval> | undefined

async function check() {
  if (checking.value) return
  checking.value = true
  try {
    setUser((await api.me()).user)
    stillWaiting.value = true
  } catch {
    /* offline: try again later */
  } finally {
    checking.value = false
  }
}

onMounted(() => {
  timer = setInterval(check, 30_000)
  window.addEventListener('online', check)
})
onBeforeUnmount(() => {
  clearInterval(timer)
  window.removeEventListener('online', check)
})
</script>

<template>
  <div class="min-h-screen grid place-items-center p-4">
    <div :class="[card, 'w-full max-w-sm p-6 space-y-4 text-center']">
      <h1 class="text-xl font-bold">Wacht op goedkeuring</h1>
      <p class="text-sm text-muted-foreground">
        Bedankt {{ currentUser?.firstname }}, je account is aangemaakt. Een beheerder moet het nog goedkeuren
        voordat je Wegrep kunt gebruiken. Zodra dat is gebeurd opent de app hier vanzelf.
      </p>
      <p v-if="stillWaiting" class="text-sm text-muted-foreground">Nog niet goedgekeurd.</p>
      <button :class="btn('default', 'lg', 'w-full')" :disabled="checking" @click="check">Controleer nu</button>
      <button :class="btn('ghost', 'default', 'w-full')" @click="signOut">Uitloggen</button>
    </div>
  </div>
</template>
