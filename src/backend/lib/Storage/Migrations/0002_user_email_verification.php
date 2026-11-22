<?php
declare(strict_types=1);

// The address is proven by using a login code once; administrators are told about a new
// account only then. Accounts that existed before count as verified.
return [
    'ALTER TABLE users ADD COLUMN verified_at DATETIME NULL',
    'UPDATE users SET verified_at = created_at',
];
