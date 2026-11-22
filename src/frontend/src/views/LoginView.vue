<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'
import { setSession } from '../auth'
import { btn, card } from '../ui'

// Sign in without a password: ask for a code, get it by e-mail (6 digits, also in a link), type it in.
// The link in the mail opens this page with ?email=&code= and signs in at once.
const route = useRoute()
const router = useRouter()

const step = ref<'email' | 'register' | 'code'>('email')
const email = ref(String(route.query.email ?? ''))
const firstname = ref('')
const lastname = ref('')
const code = ref('')
const busy = ref(false)
const error = ref('')
const info = ref('')

const codeComplete = computed(() => /^\d{6}$/.test(code.value))
watch(code, (c) => {
  code.value = c.replace(/\D/g, '').slice(0, 6)
  if (codeComplete.value) verify()
})

onMounted(() => {
  const linkCode = String(route.query.code ?? '')
  if (email.value && /^\d{6}$/.test(linkCode)) {
    step.value = 'code'
    code.value = linkCode
  }
})

async function run(action: () => Promise<void>) {
  if (busy.value) return
  busy.value = true
  error.value = ''
  info.value = ''
  try {
    await action()
  } catch (e) {
    error.value = e instanceof TypeError ? 'Geen verbinding met de server. Probeer het later opnieuw.' : (e as Error).message
  } finally {
    busy.value = false
  }
}

const sendCode = () => run(async () => {
  await api.requestCode(email.value)
  step.value = 'code'
  code.value = ''
  info.value = `We hebben een code gestuurd naar ${email.value.trim()}.`
})

const register = () => run(async () => {
  await api.register(email.value, firstname.value, lastname.value)
  step.value = 'code'
  code.value = ''
  info.value = `We hebben een code gestuurd naar ${email.value.trim()}.`
})

const verify = () => run(async () => {
  const { token, user } = await api.verifyCode(email.value, code.value)
  setSession(token, user)
  const target = String(route.query.redirect ?? '/')
  await router.replace(target.startsWith('/') ? target : '/')
})

function resend() {
  run(async () => {
    await api.requestCode(email.value)
    code.value = ''
    info.value = 'Er is een nieuwe code onderweg.'
  })
}
</script>

<template>
  <div class="min-h-screen grid place-items-center p-4">
    <div :class="[card, 'w-full max-w-sm p-6 space-y-4']">
      <h1 class="text-xl font-bold">
        {{ step === 'register' ? 'Account aanmaken' : 'Inloggen' }}
      </h1>

      <form v-if="step === 'email'" class="space-y-4" @submit.prevent="sendCode">
        <p class="text-sm text-muted-foreground">Vul je e-mailadres in. Je krijgt een code om in te loggen, een wachtwoord is niet nodig.</p>
        <input v-model="email" type="email" class="field" placeholder="E-mailadres" autocomplete="email" required autofocus />
        <button :class="btn('default', 'lg', 'w-full')" :disabled="busy">Stuur code</button>
        <p class="text-sm text-center text-muted-foreground">
          Nog geen account?
          <button type="button" class="text-primary hover:underline cursor-pointer" @click="step = 'register'; error = ''">Account aanmaken</button>
        </p>
      </form>

      <form v-else-if="step === 'register'" class="space-y-4" @submit.prevent="register">
        <p class="text-sm text-muted-foreground">Een beheerder moet je account goedkeuren voordat je het kunt gebruiken.</p>
        <input v-model="firstname" class="field" placeholder="Voornaam" autocomplete="given-name" required autofocus />
        <input v-model="lastname" class="field" placeholder="Achternaam" autocomplete="family-name" required />
        <input v-model="email" type="email" class="field" placeholder="E-mailadres" autocomplete="email" required />
        <button :class="btn('default', 'lg', 'w-full')" :disabled="busy">Account aanmaken</button>
        <p class="text-sm text-center text-muted-foreground">
          Heb je al een account?
          <button type="button" class="text-primary hover:underline cursor-pointer" @click="step = 'email'; error = ''">Inloggen</button>
        </p>
      </form>

      <form v-else class="space-y-4" @submit.prevent="verify">
        <p v-if="info" class="text-sm text-muted-foreground">{{ info }} De code is 15 minuten geldig.</p>
        <input v-model="code" class="field text-center text-2xl tracking-[0.5em]" inputmode="numeric"
          autocomplete="one-time-code" maxlength="6" placeholder="000000" autofocus />
        <button :class="btn('default', 'lg', 'w-full')" :disabled="busy || !codeComplete">Inloggen</button>
        <p class="text-sm text-center text-muted-foreground">
          <button type="button" class="text-primary hover:underline cursor-pointer" :disabled="busy" @click="resend">Stuur een nieuwe code</button>
          ·
          <button type="button" class="text-primary hover:underline cursor-pointer" @click="step = 'email'; error = ''; info = ''">Ander e-mailadres</button>
        </p>
      </form>

      <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
    </div>
  </div>
</template>
