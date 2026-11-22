<?php
declare(strict_types=1);

namespace Wegrep\Auth;

/**
 * The roles an account can have, the same values as the role column of the users table.
 * An account without a role (NULL in the database) is registered but not approved yet.
 */
enum Role: string
{
    case User = 'user';
    case Administrator = 'administrator';

    /** The role of a users row, or null while the account waits for approval. */
    public static function of(array $user): ?self
    {
        return $user['role'] === null ? null : self::tryFrom($user['role']);
    }
}
