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
cp .env.example .env          # then fill in WEGREP_LEAGUE_PASSWORD, WEGREP_JWT_SECRET, WEGREP_ADMIN_EMAIL
cd frontend
npm install
```

In `.env` you need `WEGREP_LEAGUE_PASSWORD`, a `WEGREP_JWT_SECRET` (how to generate one is in `.env.example`) and
`WEGREP_ADMIN_EMAIL` (the address that becomes the first administrator) to get started. Keep
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

Open http://localhost:5173. Mails the app sends (login codes) never leave your machine; read them in Mailpit on
http://localhost:8025. The MySQL database is also in docker (data in a volume; reset it with `docker compose down -v`).
Vite proxies every `/api` call to the backend on :8080, so frontend changes reload
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

### Tests

```bash
cd src/frontend && npm test                                  # Vitest
docker run --rm -v "${PWD}/src/backend:/app" -w /app php:8.3-cli php tests/run.php   # PHP tests (PowerShell)
```

The PHP tests have no dependencies: `src/backend/tests/*Test.php` register tests with `test('name', fn)` and
`tests/run.php` runs them.

### Pipeline (GitHub Actions)

`.github/workflows/ci.yml` runs on every pull request to `main` and on every push to `main`: PHP lint and tests,
frontend typecheck, tests and build. A push to `main` (a merge) then deploys `src/backend/` including the built app to
the server over SFTP. `.env` and `data/logo_cache/` are never overwritten. Set these as secrets in GitHub
(Settings > Secrets and variables > Actions, ideally on an environment called `production`):

| Secret | Value |
|---|---|
| `SFTP_HOST` | server name |
| `SFTP_PORT` | optional, default 22 |
| `SFTP_USERNAME` | login |
| `SFTP_PASSWORD` | password |
| `SFTP_REMOTE_PATH` | folder on the server that is the project root (contains `public/`, `lib/`, `config.php`) |

## Project layout

```
src/
  backend/             PHP, this folder is what gets deployed
    public/            document root: api/index.php (route table for /api), .htaccess; the built SPA lands here
    lib/               PHP classes, namespace Wegrep\<folder>
      Http/            Router, ApiException and Controllers/ (the /api endpoints: Meta, League, Game)
      HockeyData/      hockeydata LOS6 client and the reader for its .NET BinaryFormatter answers
      GameReport/      EGREP game report XML and the [HD] encryption around it
      Clubs/           ijshockey.nl feed (logos + schedule) and the club filter
    data/              penalty codes, and the club logo cache (logo_cache/, gitignored)
    config.php         settings without secrets
  frontend/            Vue source: views/, components/, gameStore.ts (game state), api.ts (calls to /api)
    public/            icons, manifest and service worker, copied into the build
  docker-compose.yml   PHP 8.3 + Apache serving backend/, MySQL 8.4 and Mailpit (catches all mail)
  .env                 your secrets (gitignored)
```

## Handy settings for local testing

Set these in `src/.env` and recreate the container:

| Setting | Effect |
|---|---|
| `WEGREP_CLUB_FILTER=true` + `WEGREP_CLUBS=...` | only show leagues and games of these clubs |
| `WEGREP_DEMO_LEAGUE=true` | keep hockeydata's demo league (id 320, dummy games) visible while the club filter is on |
| `WEGREP_LOGOS_ENABLED=false` | no club logos from ijshockey.nl, lettered badges only |
| `WEGREP_ADMIN_EMAIL=you@example.com` | this address is administrator from its first login |

Use the "Voorbeeld XML" button in a game to see exactly what would be uploaded, without sending anything.

## Troubleshooting

- **"Permission denied" when loading games**: `WEGREP_LEAGUE_PASSWORD` is missing or wrong in `src/.env`, or the
  container was not recreated after changing it.
- **"WEGREP_JWT_SECRET is niet (of te kort...) ingesteld"**: set a secret of at least 32 characters in `src/.env` and
  recreate the container.
- **No login mail arrives**: locally mails go to Mailpit, http://localhost:8025. On a server check `WEGREP_MAIL_FROM` and
  that the hosting allows PHP `mail()`.
- **API calls fail on :5173**: the backend is not running; check `docker compose ps` and `docker compose logs web`.
- **Port 8080 already in use**: change `"8080:80"` in `docker-compose.yml` and the proxy target in
  `frontend/vite.config.ts`.
- **Old version after a build on :8080**: the service worker served the cached app; hard refresh.

## TODO

- In the administrator pages administrators can change user roles. A new user registration is just assign a role to it. The roles: user / administrator
- See if its an option to we can upload a gamereport into the current windows application. If possible make it possible to download a gamereport so we can upload it in the application if the normal flow doesn't work
- Keep sessions: It should be possible to view which game report was started and everything is saved in both local session as database. Initially in local storage and synced to the backend. (if the interconnection interupts)
- Make sure the applications keep running if the internet connection drops. So storing stat ein local storage, run the application as single page. 
- Remove the claude co pilot from the github history
