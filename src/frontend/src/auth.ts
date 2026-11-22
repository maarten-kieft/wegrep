import { computed, ref } from 'vue'
import { storage } from './storage'
import type { User } from './types'

// The login (a JWT from the server plus the user it belongs to) is kept in localStorage.
// Being signed in is decided here, locally: when the connection drops the app just keeps going.
// Only an answer from the server (401) signs you out, see api.ts.
const token = ref<string | null>(storage.get('token'))
export const currentUser = ref<User | null>(storage.getJson<User>('user'))

export const isSignedIn = computed(() => !!token.value && !!currentUser.value)
export const isAdmin = computed(() => currentUser.value?.role === 'administrator')

export const getToken = () => token.value

export function setSession(newToken: string, user: User) {
  token.value = newToken
  currentUser.value = user
  storage.set('token', newToken)
  storage.setJson('user', user)
}

/** The server sends a fresh token once the old one is a day old. */
export function renewToken(newToken: string) {
  token.value = newToken
  storage.set('token', newToken)
}

export function setUser(user: User) {
  currentUser.value = user
  storage.setJson('user', user)
}

export function signOut() {
  token.value = null
  currentUser.value = null
  storage.remove('token')
  storage.remove('user')
}
