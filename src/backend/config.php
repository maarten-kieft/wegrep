<?php
// Configuration. This file holds NO secrets, so it is committed.
// Secrets (passwords) go in a .env file next to docker-compose.yml, or in real
// environment variables on the server. See .env.example.
// On hosting without real environment variables, put the same settings in a .env file
// next to this config (not deployed, not committed). Real environment variables win.
if (is_file(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (getenv($name) === false) {
            putenv($name . '=' . trim($value, "\"'"));
        }
    }
}

return [
    'api_base' => getenv('WEGREP_API_BASE') ?: 'https://los.hockeydata.net/los/data-asp/api',
    // League customer code, same as "Consumer" in the .NET version. Not a secret.
    'customer' => getenv('WEGREP_CUSTOMER') ?: 'IJSijs',
    // League password for the list of games. SECRET -> set WEGREP_LEAGUE_PASSWORD in .env.
    'league_password' => getenv('WEGREP_LEAGUE_PASSWORD') ?: '',
    // Sent along with every upload, like EGREP does ("e-grep_v" + version). Not a secret.
    'egrep_version' => getenv('WEGREP_EGREP_VERSION') ?: 'e-grep_v26.1.0.2018',
    // Write every request to hockeydata and its answer to the server log (the full body, so only for debugging).
    'debug_hockeydata' => filter_var(getenv('WEGREP_DEBUG_HOCKEYDATA') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    // Uploading writes the official game report. Keep this off until you have tested.
    'upload_enabled' => filter_var(getenv('WEGREP_UPLOAD_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    // MySQL. The tables are created automatically on first use (see lib/Storage/Migrations).
    'db_host' => getenv('WEGREP_DB_HOST') ?: 'localhost',
    'db_port' => (int) (getenv('WEGREP_DB_PORT') ?: 3306),
    'db_name' => getenv('WEGREP_DB_NAME') ?: 'wegrep',
    'db_user' => getenv('WEGREP_DB_USER') ?: 'wegrep',
    // SECRET -> set WEGREP_DB_PASSWORD in .env.
    'db_password' => getenv('WEGREP_DB_PASSWORD') ?: '',
    // Login. The app signs people in with a code sent by e-mail and then hands out a JWT.
    // SECRET -> set WEGREP_JWT_SECRET (at least 32 random characters) in .env, for example:
    //   php -r "echo bin2hex(random_bytes(32));"
    'jwt_secret' => getenv('WEGREP_JWT_SECRET') ?: '',
    // Addresses that are administrators from their first login (comma-separated). This is how
    // the first administrator comes about; after that administrators can promote others in the app.
    'admin_emails' => array_values(array_filter(array_map(
        fn($e) => strtolower(trim($e)),
        explode(',', getenv('WEGREP_ADMIN_EMAIL') ?: '')
    ), fn($e) => $e !== '')),
    // Public address of the app, used for the login link in mails, e.g. https://sheet.example.nl.
    // Empty = mails carry the code only. Never derived from the request: a forged Host header
    // would put a link to someone else's site in the mail.
    'app_url' => rtrim(getenv('WEGREP_APP_URL') ?: '', '/'),
    // Sender of the mails. On a real server use an address of your own domain, or mails end up in spam.
    'mail_from' => getenv('WEGREP_MAIL_FROM') ?: 'wegrep@localhost',
    'mail_from_name' => getenv('WEGREP_MAIL_FROM_NAME') ?: 'Wegrep',
    // Club logos are read from ijshockey.nl's public competition feeds (no secret,
    // no login) and cached on disk. Turn off to show lettered badges only.
    'logos_enabled' => filter_var(getenv('WEGREP_LOGOS_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'logos_source' => getenv('WEGREP_LOGOS_SOURCE') ?: 'https://www.ijshockey.nl',
    // Season as "2026-2027". Empty = derive from today (Dutch season starts in August).
    'season' => getenv('WEGREP_SEASON') ?: '',
    // Club focus. When on, only the leagues and games where one of the configured
    // clubs plays are shown. Clubs are matched on team name (substring). Set the
    // list with WEGREP_CLUBS (comma-separated). Turn the whole thing off with
    // WEGREP_CLUB_FILTER=false.
    'club_filter_enabled' => filter_var(getenv('WEGREP_CLUB_FILTER') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'club_filter' => array_values(array_filter(array_map(
        'trim',
        explode(',', getenv('WEGREP_CLUBS') ?: 'Blue Mountain Cougars')
    ), fn($c) => $c !== '')),
    // Demo league. hockeydata has a test league ("IJSHOCKEY NEDERLAND DEMO",
    // id 320) with dummy games, handy for trying the report flow. The club filter
    // normally hides it. Turn this on to keep the demo league (and all its games)
    // visible even while the club filter is active. Only has effect when the club
    // filter is on. Set the id(s) with WEGREP_DEMO_LEAGUES (comma-separated).
    'demo_league_enabled' => filter_var(getenv('WEGREP_DEMO_LEAGUE') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'demo_leagues' => array_values(array_filter(array_map(
        'intval',
        explode(',', getenv('WEGREP_DEMO_LEAGUES') ?: '320')
    ), fn($id) => $id > 0)),
];
