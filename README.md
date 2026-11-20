# Wegrep

Web version of EGREP (Electronic Game Report) for ice hockey: pick a league and a game, keep the score sheet
(goals, penalties, game clock, rosters) on a phone or tablet, and upload the game report to hockeydata.

The app lives in [`src/`](src/): a plain PHP 8.1+ backend (no Composer, no framework) and a Vue 3 + Vite +
Tailwind frontend. See [`src/README.md`](src/README.md) for deployment and how the app talks to the hockeydata API.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (runs PHP 8.3 + Apache, nothing else to install)
- [Node.js](https://nodejs.org/) 20 or newer (for the Vue frontend)
- The league password for hockeydata (ask the team, it is not in the repo)

## First time setup

```bash
cd src
cp .env.example .env          # then fill in WEGREP_LEAGUE_PASSWORD
cd frontend
npm install
```

In `.env` you only need `WEGREP_LEAGUE_PASSWORD` to get started. Leave `WEGREP_APP_PASSWORD` empty locally and keep
`WEGREP_UPLOAD_ENABLED=false`: uploading writes the official game report. All settings and their defaults are in
`src/backend/config.php`; anything in `.env` overrides them.

## Develop

Start the PHP backend (from `src/`):

```bash
docker compose up -d          # API on http://localhost:8080/api
```

Start the frontend with hot reload (from `src/frontend/`):

```bash
npm run dev                   # http://localhost:5173
```

Open http://localhost:5173. Vite proxies every `/api` call to the backend on :8080, so frontend changes reload
instantly and PHP changes are live on the next request (`src/backend/` is mounted into the container, no restart
needed). Only after changing `.env` restart the backend with `docker compose up -d --force-recreate`.

Stop the backend with `docker compose down`.

### Try the built app

```bash
cd src/frontend
npm run build                 # type check + build into src/backend/public/
```

Then open http://localhost:8080. This is the app as it will be deployed, including the PWA service worker. The
service worker caches the app, so after a new build do a hard refresh (Ctrl+F5) or clear it in DevTools >
Application > Service workers.

### Type check

```bash
cd src/frontend
npm run typecheck
```

## Project layout

```
src/
  backend/             PHP, this folder is what gets deployed
    public/            document root: api/index.php (route table for /api), .htaccess; the built SPA lands here
    lib/               PHP classes (namespace Wegrep\): hockeydata client, NRBF reader, [HD] crypto, game report XML
      Controllers/     the /api endpoints (Meta, League, Game)
    data/              penalty codes, and the club logo cache (logo_cache/, gitignored)
    config.php         settings without secrets
  frontend/            Vue source: views/, components/, gameStore.ts (game state), api.ts (calls to /api)
    public/            icons, manifest and service worker, copied into the build
  docker-compose.yml   PHP 8.3 + Apache serving backend/
  .env                 your secrets (gitignored)
```

## Handy settings for local testing

Set these in `src/.env` and recreate the container:

| Setting | Effect |
|---|---|
| `WEGREP_CLUB_FILTER=true` + `WEGREP_CLUBS=...` | only show leagues and games of these clubs |
| `WEGREP_DEMO_LEAGUE=true` | keep hockeydata's demo league (id 320, dummy games) visible while the club filter is on |
| `WEGREP_LOGOS_ENABLED=false` | no club logos from ijshockey.nl, lettered badges only |
| `WEGREP_APP_PASSWORD=...` | test the login screen |

Use the "Voorbeeld XML" button in a game to see exactly what would be uploaded, without sending anything.

## Troubleshooting

- **"Permission denied" when loading games**: `WEGREP_LEAGUE_PASSWORD` is missing or wrong in `src/.env`, or the
  container was not recreated after changing it.
- **"Wachtwoord onjuist"**: `WEGREP_APP_PASSWORD` is set; enter it in the app or empty it in `.env`.
- **API calls fail on :5173**: the backend is not running; check `docker compose ps` and `docker compose logs web`.
- **Port 8080 already in use**: change `"8080:80"` in `docker-compose.yml` and the proxy target in
  `frontend/vite.config.ts`.
- **Old version after a build on :8080**: the service worker served the cached app; hard refresh.

## TODO

- [ ] Decide whether to switch the app password to PHP sessions. Now the frontend keeps the password in
  `localStorage` and sends it as `X-App-Password` on every request. A `POST /api/login` that sets an `HttpOnly`
  session cookie would keep it out of JavaScript and allow logging out. Fine as is for a small group over HTTPS;
  worth doing before the app is really public.
