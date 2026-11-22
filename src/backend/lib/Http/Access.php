<?php
declare(strict_types=1);

namespace Wegrep\Http;

/** Who may call a route. Each level includes the checks of the one before it. */
enum Access
{
    /** Anybody, also without being signed in. */
    case Anyone;
    /** Signed in, even while the account waits for approval. */
    case Account;
    /** Signed in with an approved account (has a role). */
    case Approved;
    /** Administrators only. */
    case Admin;
}
