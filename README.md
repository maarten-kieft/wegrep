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
    public/            document root: api/index.php (starts the Application), .htaccess; the built SPA lands here
    lib/               PHP classes, namespace Wegrep\<folder>
      Http/            routes.php (the route table: open, any account, or per role), Application (one request), Router, Endpoints
                       (wires the controllers together)
      Controllers/     the /api endpoints (Meta, Auth, Admin, Session, League, Game)
      HockeyData/      hockeydata LOS6 client and the reader for its .NET BinaryFormatter answers
      GameReport/      EGREP game report XML and the [HD] encryption around it
      Clubs/           ijshockey.nl feed (logos + schedule) and the club filter
      Auth/            Role, login codes, JWT and the admin actions
      Sessions/        saved game sessions (per user and game)
      Mail/            Mailer (mail()) and the mail templates in Templates/ (content per mail, layout.php for the look)
      Storage/         MySQL connection, the user store and Migrations/ (one numbered file per schema change, run automatically)
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

Use "Download XML" at the end of a game to see exactly what would be uploaded, without sending anything.

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

Status: **te controleren** = gebouwd en door mij getest, jij moet het nog nakijken. **open** = nog niet af.

### Te controleren

- **Te controleren** E-mail login zonder wachtwoord: account aanmaken (e-mail, voor- en achternaam), code van 6 cijfers per mail (ook als link), één keer te gebruiken, 15 minuten geldig, JWT-token. Lokaal lees je de mail in Mailpit, http://localhost:8025.
- **Te controleren** Goedkeuring door een beheerder: een nieuw account ziet een wachtscherm, alle beheerders krijgen een mail, goedkeuren op `/admin` (link "Beheer" rechtsboven naast de terugknop).
- **Te controleren** Beheerpagina: rol wijzigen (gebruiker / beheerder) en gebruikers verwijderen of afwijzen.
- **Te controleren** Sessies bewaren: gestarte wedstrijden staan in de browser en in de database, de pagina "Mijn wedstrijden" (gebruikersmenu), bij een bestaande sessie de keuze doorgaan of nieuw beginnen.
- **Te controleren** Beheer, tab Sessies: de sessies van alle gebruikers, met Bekijken (alleen-lezen, opent in een nieuw browsertabblad). Ook Overnemen: een sessie van een gebruiker naar jezelf verplaatsen, met bevestiging en een waarschuwing als je zelf al een sessie voor die wedstrijd hebt; de vorige eigenaar krijgt bij de volgende sync een melding. Ook: de Doorgaan-met-balk met weggooien op de leagues- en games-pagina.
- **Te controleren** Offline blijven werken: geen inlogscherm zonder verbinding, een lopende wedstrijd werkt door en synchroniseert later, "Doorgaan met" op het startscherm. Zie ook het open punt over de koude start hieronder.
- **Te controleren** GitHub pipeline: checks bij een PR en op main, op main ook deploy naar SFTP. Nog nooit op GitHub gedraaid.
- **Te controleren** Download van het wedstrijdverslag als XML-bestand (knop onder het uploadpaneel na "End game"); officials (o.a. de scorekeeper) zijn ook op dat eindscherm in te vullen.

### Open

- **Te controleren** Last minute selectie-update uit de EGREP-handleiding: bij elk team in de setup de knop "Add player or official" om iemand toe te voegen die de coach niet had opgesteld (alle aangemelde spelers en team officials van de club, met zoeken; geschorste spelers kunnen niet; officials krijgen HC of T1..T7, maximaal 8). Namen aanmaken of wijzigen kan niet, net als in EGREP.
- **Te controleren** Validatie: een wedstrijd start alleen als elk team een geldige opstelling heeft: alle spelers een rugnummer (0-99, officials HC of T1-T7, geen dubbele nummers) en precies één captain (C); wat er mis is staat bij het team zelf, en een speler zonder nummer is te verwijderen; een gele waarschuwing voor spelers zonder positie. Indienen vraagt een scheidsrechter, scorekeeper, timekeeper en de start- en eindtijd. Toegevoegde spelers zijn weer te verwijderen. Het aantal toeschouwers (spectators) staat nog niet in de app.
- **Open** GitHub-secrets aanmaken voor de deploy: `SFTP_HOST`, `SFTP_PORT (optioneel)`, `SFTP_USERNAME`, `SFTP_PASSWORD`, `SFTP_REMOTE_PATH`. Vraag: wil je inloggen met een SSH-sleutel in plaats van een wachtwoord? Dan pas ik de workflow aan.
- **Open** Productie-instellingen op de server: `WEGREP_DB_*`, `WEGREP_JWT_SECRET`, `WEGREP_ADMIN_EMAIL`, `WEGREP_APP_URL`, `WEGREP_MAIL_FROM` (zie `src/.env.example`). Vraag: kan de hosting environment variables zetten, of gebruiken we een `.env` naast `config.php`?
- **Te controleren** Upload naar hockeydata: de request is nu gelijkgetrokken met een echte EGREP-upload (v26.1: versie, veld errors=null, element-volgorde en standaardwaarden in het XML, scorekeeper, start- en eindtijd verplicht; die tijden worden automatisch gevuld bij Start Game en End game en zijn aan te passen onder Game times). Test op een demo-wedstrijd; zet WEGREP_DEBUG_HOCKEYDATA=true om de request en het antwoord in het serverlog te zien.
- **Open** Kijken of de Windows EGREP het gedownloade verslag accepteert (XML). Pas daarna haal ik de "nog niet gecontroleerd"-tekst uit het paneel weg.
- **Open** Offline koude start (app openen zonder verbinding) testen op een echt apparaat; in mijn testbrowser kon de service worker niet geregistreerd worden.
- **Open** Claude als co-author uit de git-historie halen (9 commits). Vraag: mag ik de historie herschrijven en wil je zelf force pushen? Dit vraagt dat iedereen daarna opnieuw kloont.
