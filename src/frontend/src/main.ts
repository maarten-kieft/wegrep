import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import HomeView from './views/HomeView.vue'
import LeaguesView from './views/LeaguesView.vue'
import GamesView from './views/GamesView.vue'
import GameView from './views/GameView.vue'
import LoginView from './views/LoginView.vue'
import { isSignedIn } from './auth'
import { ensureLogos } from './logos'
import './style.css'

// Warm the logo/schedule cache at startup so club filtering and logos are ready.
ensureLogos()

// Same routes as the Next.js app.
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/login', component: LoginView },
    { path: '/', component: HomeView },
    { path: '/leagues', component: LeaguesView },
    { path: '/leagues/:leagueId/games', component: GamesView, props: (r) => ({ leagueId: Number(r.params.leagueId) }) },
    {
      path: '/leagues/:leagueId/games/:gameId', component: GameView,
      props: (r) => ({ leagueId: Number(r.params.leagueId), gameId: Number(r.params.gameId) }),
    },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

// Everything except the login screen needs a signed-in user. Signed in is a local fact (see auth.ts),
// so this works offline too. A link from the login mail (/login?email=&code=) is let through to sign in.
router.beforeEach((to) => {
  if (to.path === '/login') return isSignedIn.value && !to.query.code ? '/' : true
  if (!isSignedIn.value) return { path: '/login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } }
  return true
})

createApp(App).use(router).mount('#app')

// Installable app with offline start-up (not in dev, where it would cache stale code).
if ('serviceWorker' in navigator && import.meta.env.PROD) {
  navigator.serviceWorker.register(`${import.meta.env.BASE_URL}sw.js`, { scope: import.meta.env.BASE_URL })
}
