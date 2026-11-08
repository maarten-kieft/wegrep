<?php
// Configuration. This file holds NO secrets, so it is committed.
// Secrets (passwords) go in a .env file next to docker-compose.yml, or in real
// environment variables on the server. See .env.example.
return [
    'api_base' => getenv('WEGREP_API_BASE') ?: 'https://los.hockeydata.net/los/data-asp/api',
    // League customer code, same as "Consumer" in the .NET version. Not a secret.
    'customer' => getenv('WEGREP_CUSTOMER') ?: 'IJSijs',
    // League password for the list of games. SECRET -> set WEGREP_LEAGUE_PASSWORD in .env.
    'league_password' => getenv('WEGREP_LEAGUE_PASSWORD') ?: '',
    // Sent along with every upload, like EGREP does ("e-grep_v" + version). Not a secret.
    'egrep_version' => getenv('WEGREP_EGREP_VERSION') ?: 'e-grep_v21.0.1.1957',
    // Uploading writes the official game report. Keep this off until you have tested.
    'upload_enabled' => filter_var(getenv('WEGREP_UPLOAD_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    // Shared password for the web app. SECRET -> set WEGREP_APP_PASSWORD in .env.
    'app_password' => getenv('WEGREP_APP_PASSWORD') ?: '',
    // Club logos are read from ijshockey.nl's public competition feeds (no secret,
    // no login) and cached on disk. Turn off to show lettered badges only.
    'logos_enabled' => filter_var(getenv('WEGREP_LOGOS_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'logos_source' => getenv('WEGREP_LOGOS_SOURCE') ?: 'https://www.ijshockey.nl',
    // Season as "2026-2027". Empty = derive from today (Dutch season starts in August).
    'season' => getenv('WEGREP_SEASON') ?: '',
];
