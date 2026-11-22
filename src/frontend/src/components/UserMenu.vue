<script setup lang="ts">
import { ClipboardList, LogOut, Shield, User } from 'lucide-vue-next'
import { ref } from 'vue'
import { currentUser, isAdmin, signOut } from '../auth'

// Who is signed in, with the way out, and for administrators the link to the user management.
// Sits in the top right corner of every screen.
const open = ref(false)

function logout() {
  open.value = false
  signOut()
}
</script>

<template>
  <div v-if="currentUser" class="flex items-center gap-1">
    <RouterLink v-if="isAdmin" to="/admin"
      class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md text-sm font-medium text-white hover:bg-white/20">
      <Shield class="w-4 h-4" /> Beheer
    </RouterLink>
    <div class="relative">
      <button type="button"
        class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md text-sm font-medium text-white hover:bg-white/20 cursor-pointer"
        @click="open = !open">
        <User class="w-4 h-4" /> {{ currentUser.firstname }}
      </button>
      <template v-if="open">
        <div class="fixed inset-0 z-30" @click="open = false" />
        <div class="absolute right-0 mt-1 z-40 w-56 rounded-lg border border-border bg-card text-card-foreground shadow-lg p-1">
          <div class="px-3 py-2 text-sm">
            <div class="font-medium">{{ currentUser.firstname }} {{ currentUser.lastname }}</div>
            <div class="text-muted-foreground truncate">{{ currentUser.email }}</div>
          </div>
          <RouterLink to="/sessions" class="w-full flex items-center gap-2 px-3 py-2 text-sm rounded-md hover:bg-accent/50" @click="open = false">
            <ClipboardList class="w-4 h-4" /> Mijn wedstrijden
          </RouterLink>
          <button type="button"
            class="w-full flex items-center gap-2 px-3 py-2 text-sm rounded-md hover:bg-accent/50 cursor-pointer" @click="logout">
            <LogOut class="w-4 h-4" /> Uitloggen
          </button>
        </div>
      </template>
    </div>
  </div>
</template>
