<?php
declare(strict_types=1);

namespace Wegrep\Auth;

use Wegrep\Http\ApiException;
use Wegrep\Storage\UserStore;

/** What administrators can do with accounts: see them, approve them / change their role. */
final class AdminService
{
    public const ROLES = ['user', 'administrator'];

    public function __construct(private readonly UserStore $users)
    {
    }

    /** @return list<array> everybody, accounts that wait for approval first */
    public function list(): array
    {
        $users = array_map(
            static fn(array $u): array => Authenticator::publicUser($u) + [
                'createdAt' => gmdate('c', strtotime($u['created_at'] . ' UTC')),
                'verified' => $u['verified_at'] !== null,
            ],
            $this->users->all()
        );
        usort($users, static fn($a, $b) => [$a['role'] !== null, strtolower($a['lastname'])] <=> [$b['role'] !== null, strtolower($b['lastname'])]);
        return $users;
    }

    /** Give an account a role. For an account that waits for approval this is the approval. */
    public function setRole(array $actor, int $id, string $role): array
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new ApiException('Onbekende rol');
        }
        $user = $this->users->find($id) ?? throw new ApiException('Gebruiker niet gevonden', 404);
        if ((int) $actor['id'] === $id) {
            throw new ApiException('Je kunt je eigen rol niet wijzigen', 400);
        }
        $this->users->setRole($id, $role);
        return Authenticator::publicUser($this->users->find($id));
    }

    /** Remove an account (also how a registration is turned down). Their login codes go with it. */
    public function remove(array $actor, int $id): void
    {
        $this->users->find($id) ?? throw new ApiException('Gebruiker niet gevonden', 404);
        if ((int) $actor['id'] === $id) {
            throw new ApiException('Je kunt jezelf niet verwijderen', 400);
        }
        $this->users->delete($id);
    }
}
