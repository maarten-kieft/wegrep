<?php
declare(strict_types=1);

// Accounts and their one-time login codes. role NULL = registered but not approved yet.
return [
    "CREATE TABLE users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL,
        firstname VARCHAR(100) NOT NULL,
        lastname VARCHAR(100) NOT NULL,
        role ENUM('user', 'administrator') NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY users_email (email)
    ) DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE login_codes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        code_hash CHAR(64) NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        KEY login_codes_user (user_id),
        CONSTRAINT login_codes_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) DEFAULT CHARSET=utf8mb4",
];
