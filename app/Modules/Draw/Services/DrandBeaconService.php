<?php

declare(strict_types=1);

namespace App\Modules\Draw\Services;

use App\Modules\Draw\Contracts\BeaconService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * drand HTTP client, ported from ZF2 Draw\Service\BeaconService.
 *
 * The configured endpoints are tried in order; a failed request or a body that is not a JSON
 * object moves on to the next one. Chain info is fetched once per instance (scoped binding:
 * once per request). round() returns null while a round is unpublished: the caller has to wait,
 * never switch to another source.
 */
final class DrandBeaconService implements BeaconService
{
    private ?int $genesisTime = null;

    private ?int $period = null;

    public function info(): array
    {
        $info = $this->request('/info');

        if (! isset($info['genesis_time']) || ! isset($info['period'])) {
            throw new RuntimeException('drand /info response is missing genesis_time or period');
        }

        $this->genesisTime = (int) $info['genesis_time'];
        $this->period = (int) $info['period'];

        return $info;
    }

    public function roundTime(int $round): int
    {
        [$genesis, $period] = $this->chain();

        return $genesis + ($round - 1) * $period;
    }

    public function roundAt(int $timestamp): int
    {
        [$genesis, $period] = $this->chain();

        if ($timestamp < $genesis) {
            throw new RuntimeException('timestamp is before the chain genesis');
        }

        return (int) floor(($timestamp - $genesis) / $period) + 1;
    }

    public function assertFutureRound(int $round): true
    {
        $current = $this->roundAt(now()->getTimestamp());

        if ($round <= $current) {
            throw new RoundNotFutureException($round, $current);
        }

        return true;
    }

    public function round(int $round): ?array
    {
        try {
            $data = $this->request('/public/'.$round);
        } catch (RuntimeException) {
            return null;
        }

        if (! isset($data['round']) || ! isset($data['randomness'])) {
            return null;
        }

        if ((int) $data['round'] !== $round) {
            throw new RuntimeException(sprintf('drand returned round %u instead of the requested %u', (int) $data['round'], $round));
        }

        return [
            'round' => (int) $data['round'],
            'randomness' => (string) $data['randomness'],
            'signature' => isset($data['signature']) ? (string) $data['signature'] : '',
        ];
    }

    public function sourceLabel(int $round): string
    {
        return 'drand:round:'.$round;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function chain(): array
    {
        if ($this->genesisTime === null || $this->period === null) {
            $this->info();
        }

        return [(int) $this->genesisTime, (int) $this->period];
    }

    /**
     * @return array<mixed>
     */
    private function request(string $path): array
    {
        $lastError = null;
        $timeout = (int) config('draw.beacon.timeout', 10);

        foreach ((array) config('draw.beacon.endpoints', []) as $base) {
            $url = rtrim((string) $base, '/').$path;

            try {
                $response = Http::timeout($timeout)
                    ->connectTimeout($timeout)
                    ->withUserAgent((string) config('draw.beacon.user_agent', 'draw-beacon/1.0'))
                    ->acceptJson()
                    ->get($url);
            } catch (ConnectionException $e) {
                $lastError = new RuntimeException('GET '.$url.' failed: '.$e->getMessage(), 0, $e);

                continue;
            }

            if ($response->status() !== 200) {
                $lastError = new RuntimeException(sprintf('GET %s failed (HTTP %s)', $url, $response->status()));

                continue;
            }

            $data = json_decode($response->body(), true);

            if (is_array($data)) {
                return $data;
            }

            $lastError = new RuntimeException('drand response was not valid JSON: '.$url);
        }

        throw $lastError ?? new RuntimeException('no drand endpoint reachable');
    }
}
