<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, ApiError } from './api'
import { loadMeta, meta } from './meta'
import { storage } from './storage'
import { btn, card } from './ui'

const ready = ref(false)
const needsPassword = ref(false)
const password = ref('')
const loginError = ref('')
const startError = ref('')

onMounted(async () => {
  try {
    await loadMeta()
    if (meta.value.passwordRequired) await checkPassword()
    ready.value = true
  } catch (e) {
    startError.value = (e as Error).message
  }
})

async function checkPassword() {
  try {
    await api.leagues()
    needsPassword.value = false
  } catch (e) {
    if (e instanceof ApiError && e.status === 401) needsPassword.value = true
    else throw e
  }
}

async function login() {
  storage.set('password', password.value)
  await checkPassword()
  loginError.value = needsPassword.value ? 'Wrong password' : ''
}
</script>

<template>
  <div v-if="startError" class="min-h-screen grid place-items-center p-4 text-center text-muted-foreground">
    Cannot reach the server: {{ startError }}
  </div>
  <div v-else-if="ready && needsPassword" class="min-h-screen grid place-items-center p-4">
    <form :class="[card, 'w-full max-w-sm p-6 space-y-4']" @submit.prevent="login">
      <h1 class="text-xl font-bold">Sign in</h1>
      <input v-model="password" type="password" class="field" placeholder="Password" autofocus />
      <p v-if="loginError" class="text-sm text-destructive">{{ loginError }}</p>
      <button :class="btn('default', 'lg', 'w-full')">Continue</button>
    </form>
  </div>
  <RouterView v-else-if="ready" />
</template>
