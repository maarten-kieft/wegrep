<?php
declare(strict_types=1);

namespace Wegrep\Controllers;

use Wegrep\Auth\Authenticator;
use Wegrep\Http\ApiException;
use Wegrep\Http\Request;
use Wegrep\Sessions\SessionService;

/** The signed-in user's own saved game sessions. */
final class SessionController
{
    public function __construct(private readonly SessionService $sessions, private readonly Authenticator $authenticator)
    {
    }

    /** GET /sessions */
    public function list(): array
    {
        return $this->sessions->list($this->userId());
    }

    /** GET /sessions/{gameId} */
    public function show(string $gameId): array
    {
        $session = $this->sessions->get($this->userId(), (int) $gameId);
        if ($session === null && $this->sessions->isTakenOver($this->userId(), (int) $gameId)) {
            throw new ApiException('Deze sessie is overgenomen door een beheerder', 410);
        }
        return $session ?? throw new ApiException('Geen sessie voor deze wedstrijd', 404);
    }

    /** PUT /sessions/{gameId}: save (409 when a newer one is stored) */
    public function save(string $gameId): array
    {
        return $this->sessions->put($this->userId(), (int) $gameId, Request::json());
    }

    /** DELETE /sessions/{gameId} */
    public function delete(string $gameId): array
    {
        $this->sessions->delete($this->userId(), (int) $gameId);
        return ['ok' => true];
    }

    private function userId(): int
    {
        return (int) $this->authenticator->user()['id'];
    }
}
