<?php
declare(strict_types=1);

namespace Wegrep\Controllers;

use Wegrep\Auth\AdminService;
use Wegrep\Auth\Authenticator;
use Wegrep\Http\ApiException;
use Wegrep\Http\Request;
use Wegrep\Sessions\SessionService;

/** The /admin endpoints. index.php only lets administrators in. */
final class AdminController
{
    public function __construct(
        private readonly AdminService $admin,
        private readonly Authenticator $authenticator,
        private readonly SessionService $sessions
    ) {
    }

    /** GET /admin/users */
    public function users(): array
    {
        return $this->admin->list();
    }

    /** PATCH /admin/users/{id} {role}: approve an account or change its role */
    public function setRole(string $id): array
    {
        $role = Request::json()['role'] ?? null;
        if (!is_string($role)) {
            throw new ApiException('role ontbreekt');
        }
        return $this->admin->setRole($this->authenticator->user(), (int) $id, $role);
    }

    /** DELETE /admin/users/{id} */
    public function remove(string $id): array
    {
        $this->admin->remove($this->authenticator->user(), (int) $id);
        return ['ok' => true];
    }

    /** GET /admin/sessions: the sessions of all users */
    public function sessions(): array
    {
        return $this->sessions->listAll();
    }

    /** POST /admin/sessions/{userId}/{gameId}/take-over: move the session to the administrator */
    public function takeOver(string $userId, string $gameId): array
    {
        return $this->sessions->takeOver((int) $this->authenticator->user()['id'], (int) $userId, (int) $gameId, (int) (microtime(true) * 1000));
    }

    /** GET /admin/sessions/{userId}/{gameId}: one session in full, to look at it */
    public function session(string $userId, string $gameId): array
    {
        return $this->sessions->get((int) $userId, (int) $gameId) ?? throw new ApiException('Sessie niet gevonden', 404);
    }
}
