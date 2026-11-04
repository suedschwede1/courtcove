<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

use App\Modules\Draw\Models\Draw;
use Throwable;

/**
 * Recomputes a stored draw from the database: canonical payload, commitment,
 * final seed, assignment (byte-exact) and token slots. Checks that do not apply
 * yet (nothing revealed) are null, any exception inside a check is a failure.
 */
final class DrawVerifier
{
    public function __construct(private readonly DrawService $draws) {}

    public function verify(Draw $draw): DrawVerification
    {
        $payload = (string) $draw->payload;
        $seed = $draw->server_seed;
        $beacon = $draw->beacon_value;
        $executed = $draw->final_seed !== null;

        $checks = [
            'canonical' => $this->check(fn (): bool => $this->draws->isCanonical($payload)),
            'commitment' => $seed === null ? null : $this->check(
                fn (): bool => hash_equals((string) $draw->commitment, hash('sha256', $payload.'|'.$seed))
            ),
            'final_seed' => $draw->final_seed === null ? null : $this->check(
                fn (): bool => hash_equals($draw->final_seed, $this->draws->finalSeed((string) $seed, (string) $beacon))
            ),
            'result' => $executed ? $this->check(
                fn (): bool => $this->encode($this->assignment($payload, (string) $seed, (string) $beacon)) === $draw->result
            ) : null,
            'tokens' => $executed && $draw->mode === 'token' ? $this->check(
                fn (): bool => $this->tokensMatch($draw, $this->assignment($payload, (string) $seed, (string) $beacon))
            ) : null,
        ];

        return new DrawVerification((string) $draw->slug, (string) $draw->status, (string) $draw->mode, $checks);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function assignment(string $payload, string $seed, string $beacon): array
    {
        return $this->draws->execute(json_decode($payload, true, flags: JSON_THROW_ON_ERROR), $seed, $beacon)['assignment'];
    }

    private function encode(array $assignment): string
    {
        return json_encode($assignment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<int|string, mixed>  $assignment
     */
    private function tokensMatch(Draw $draw, array $assignment): bool
    {
        $rows = $draw->tokens()->get();
        $names = [];

        foreach ($rows as $row) {
            $names[(string) $row->token] = true;

            if ($row->slot !== null && (! array_key_exists($row->slot, $assignment) || (string) $assignment[$row->slot] !== (string) $row->token)) {
                return false;
            }
        }

        foreach ($assignment as $token) {
            if (! isset($names[(string) $token])) {
                return false;
            }
        }

        return true;
    }

    private function check(callable $check): bool
    {
        try {
            return $check();
        } catch (Throwable) {
            return false;
        }
    }
}
