<?php
declare(strict_types=1);

// One saved game report session per user and game. state is the app's own JSON (report, clock,
// roster edits...); the other columns are what the session list shows without opening it.
return [
    "CREATE TABLE game_sessions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        game_id INT UNSIGNED NOT NULL,
        league_id INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        phase VARCHAR(16) NOT NULL,
        score_home SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        score_away SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        saved_at BIGINT UNSIGNED NOT NULL,
        state LONGTEXT NOT NULL,
        UNIQUE KEY game_sessions_user_game (user_id, game_id),
        CONSTRAINT game_sessions_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) DEFAULT CHARSET=utf8mb4",
];
