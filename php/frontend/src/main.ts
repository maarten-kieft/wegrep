import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import HomeView from './views/HomeView.vue'
import LeaguesView from './views/LeaguesView.vue'
import GamesView from './views/GamesView.vue'
import GameView from './views/GameView.vue'
import './style.css'

// Same routes as the Next.js app.
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
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

createApp(App).use(router).mount('#app')

// Installable app with offline start-up (not in dev, where it would cache stale code).
if ('serviceWorker' in navigator && import.meta.env.PROD) {
  navigator.serviceWorker.register(`${import.meta.env.BASE_URL}sw.js`, { scope: import.meta.env.BASE_URL })
}
