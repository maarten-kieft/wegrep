# Wegrep (PHP + Vue)

Web version of EGREP (Electronic Game Report) that talks to the hockeydata LOS6 API.
Plain PHP 8.1+ backend (no Composer, no framework) and a Vue 3 single page app.
Runs on any PHP host with Apache + mod_rewrite.

```
src/
  backend/           what gets deployed
    public/          document root: api/index.php + .htaccess, the built SPA lands here
    lib/             PHP: Http/ (router, controllers), HockeyData/, GameReport/, Clubs/
    data/            penalty codes (from EGREP's config)
    config.php       settings (no secrets; committed)
  frontend/          Vue source (Vite), builds into backend/public/
  docker-compose.yml local PHP + Apache, serves backend/
  .env.example       template for the secrets (copy to .env)
```

## Run locally

See the [README in the repo root](../README.md) for the development setup.

## Deploy

Run `npm run build` in `frontend/`, then upload the `backend/` folder and point the document root at `public/`.
Set the secrets from `.env.example` as environment variables on the server, and always set `WEGREP_APP_PASSWORD`
when the site is reachable from the internet.

In a sub folder (like `https://example.nl/sheet/`): build with `WEGREP_BASE=/sheet/ npm run build`, copy the
contents of `backend/public/` to that folder and `lib/`, `data/`, `config.php` one level up (the api looks for them at
`public/../`). If the server needs it, add `RewriteBase /sheet/` to `.htaccess`.

The app is a PWA: on a phone use "Add to Home Screen" and it opens full screen like a native app.
Games in progress are kept in the browser, so a reload or a closed tab loses nothing.

## How it talks to hockeydata

Base URL `https://los.hockeydata.net/los/data-asp/api`, the same calls EGREP makes:

| Call | Used for |
|---|---|
| `GetListOfAllLeagues?customer=&password=` | leagues. Answer is .NET BinaryFormatter, read by `NrbfReader` |
| `GetAllGamesOfLeagueAsJson?leagueId=&password=&selectionOption=` | games (JSON) |
| `GetEmptyGameReportWithDpLineupForEgrep?theCurrentGameId=` | report template with rosters (JSON with `XmlData`) |
| `CheckGameOkStatusBeforeUpload?gameId=&homeId=&awayId=` | status before upload |
| `POST ReceiveUploadedGameReport` | upload. `status=1` live, `3` final |

The uploaded `serializedData` is the game report XML wrapped by `HdCrypt`:
`"[HD]" + base64(AES-256-CBC(salt + "<HD>" + base64(gzip(xml)) + "</HD>"))`.
The key is derived from the text `System.Security.SecureString` (EGREP calls `ToString()` on a SecureString).

Uploading is off by default (`upload_enabled`). The "Voorbeeld XML" button shows what would be sent.

## Club logos

hockeydata has no team logos, but ijshockey.nl does. `backend/lib/Clubs/IjshockeyFeed.php` reads
ijshockey.nl's public competition feeds once a day and builds a dictionary
(hockeydata team id → logo url, and team name → logo url), served at `GET /api/logos`.
The app matches each game's teams to a logo and falls back to lettered badges when
there is none. It is best-effort and needs no login; turn it off with
`WEGREP_LOGOS_ENABLED=false`. The dictionary is cached under `backend/data/logo_cache/`
(gitignored). Season is derived from the date, or set `WEGREP_SEASON=2026-2027`.
