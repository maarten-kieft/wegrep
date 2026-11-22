<?php
declare(strict_types=1);

// Left behind when an administrator takes a session over: the previous owner's devices still hold a
// copy and must be told (410) instead of silently uploading it again. Cleared when they acknowledge.
return [
    "CREATE TABLE session_takeovers (
        user_id INT UNSIGNED NOT NULL,
        game_id INT UNSIGNED NOT NULL,
        taken_at BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (user_id, game_id),
        CONSTRAINT session_takeovers_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) DEFAULT CHARSET=utf8mb4",
];
