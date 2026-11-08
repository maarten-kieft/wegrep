# Wegrep (PHP + Vue)

Web version of EGREP (Electronic Game Report) that talks to the hockeydata LOS6 API.
Plain PHP 8.1+ backend (no Composer, no framework) and a Vue 3 single page app.
Runs on any PHP host with Apache + mod_rewrite.

```
php/
  public/            document root: built SPA + api/index.php + .htaccess
  src/               PHP: hockeydata client, NRBF reader, [HD] crypto, game report XML
  data/              penalty codes (from EGREP's config)
  frontend/          Vue source (Vite)
  config.php         settings (no secrets; committed)
  .env.example       template for the secrets (copy to .env)
```

## Run locally

```bash
cp .env.example .env                  # then set WEGREP_LEAGUE_PASSWORD in it
docker compose up -d                  # http://localhost:8080
cd frontend && npm install && npm run build
```

For frontend development with hot reload: `cd frontend && npm run dev` (http://localhost:5173, proxies `/api` to :8080).

Set `WEGREP_LEAGUE_PASSWORD` in `.env`, otherwise the list of games returns "Permission denied". `config.php` holds no secrets and is committed; all passwords live in `.env` (gitignored).

## Deploy

Run `npm run build` in `frontend/`, then upload the whole `php/` folder except `frontend/`, and point the
document root at `public/`. Set `WEGREP_APP_PASSWORD` in `.env` when the site is reachable from the internet.

In a sub folder (like `https://example.nl/sheet/`): build with `WEGREP_BASE=/sheet/ npm run build`, copy the
contents of `public/` to that folder and `src/`, `data/`, `config.php` one level up (the api looks for them at
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
