<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from './api'
import { currentUser, isSignedIn, setUser } from './auth'
import { loadMeta } from './meta'
import PendingApprovalView from './views/PendingApprovalView.vue'

const router = useRouter()
const route = useRoute()
const ready = ref(false)
const startError = ref('')

// Ask the server who we are. Offline or a hiccup: ignore it, being signed in is a local fact.
// A 401 (token expired, account removed) signs us out inside api.ts.
async function refreshUser() {
  if (!isSignedIn.value) return
  try {
    setUser((await api.me()).user)
  } catch {
    /* offline */
  }
}

onMounted(async () => {
  try {
    await loadMeta()
    ready.value = true
    refreshUser()
  } catch (e) {
    startError.value = (e as Error).message
  }
})
window.addEventListener('online', refreshUser)
onBeforeUnmount(() => window.removeEventListener('online', refreshUser))

// Signed out (by the server, or by the menu): to the login screen.
watch(isSignedIn, (signedIn) => {
  if (!signedIn && route.path !== '/login') router.replace('/login')
})
</script>

<template>
  <div v-if="startError" class="min-h-screen grid place-items-center p-4 text-center text-muted-foreground">
    Cannot reach the server: {{ startError }}
  </div>
  <PendingApprovalView v-else-if="ready && isSignedIn && currentUser?.role === null" />
  <RouterView v-else-if="ready" />
</template>
