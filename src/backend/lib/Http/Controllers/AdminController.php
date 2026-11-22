<?php
declare(strict_types=1);

namespace Wegrep\Http\Controllers;

use Wegrep\Auth\AdminService;
use Wegrep\Auth\Authenticator;
use Wegrep\Http\ApiException;
use Wegrep\Http\Request;

/** The /admin endpoints. index.php only lets administrators in. */
final class AdminController
{
    public function __construct(private readonly AdminService $admin, private readonly Authenticator $authenticator)
    {
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
}
