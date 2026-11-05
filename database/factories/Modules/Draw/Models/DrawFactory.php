<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Draw\Models;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Models\DrawToken;
use App\Modules\Draw\Services\DrawService;
use App\Modules\Draw\Services\TokenService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datetimes are plain "Y-m-d H:i:s" strings, exactly as the legacy app stores them.
 * Apply token() before executed(): executed() derives the result from the payload
 * present at that point.
 */
class DrawFactory extends Factory
{
    protected $model = Draw::class;

    public function definition(): array
    {
        $payload = [
            'entries' => [101, 102, 103, 104],
            'slots' => ['A2', 'B2', 'C2', 'D2'],
            'rule' => 'Testregel',
        ];
        $seed = bin2hex(random_bytes(32));
        $draws = new DrawService;

        return [
            'slug' => bin2hex(random_bytes(6)),
            'title' => 'Testziehung',
            'status' => 'committed',
            'mode' => 'direct',
            'payload' => $draws->canonical($payload),
            'commitment' => $draws->commit($payload, $seed),
            'beacon_source' => 'drand:round:1000',
            'server_seed' => $seed,
            'created_uid' => 1,
            'created' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function withPayload(array $payload): static
    {
        return $this->state(function (array $attributes) use ($payload): array {
            $draws = new DrawService;

            return [
                'payload' => $draws->canonical($payload),
                'commitment' => $draws->commit($payload, $attributes['server_seed']),
            ];
        });
    }

    /**
     * @param  list<string>  $slots
     */
    public function token(int $count = 6, string $prefix = 'AC', array $slots = ['A2', 'B2', 'C2', 'A3', 'B3', 'C3']): static
    {
        return $this->state(function (array $attributes) use ($count, $prefix, $slots): array {
            $tokens = new TokenService;
            $payload = $tokens->payload($tokens->tokenNames($count, $prefix), $slots, 'Testregel');
            $draws = new DrawService;

            return [
                'mode' => 'token',
                'payload' => $draws->canonical($payload),
                'commitment' => $draws->commit($payload, $attributes['server_seed']),
            ];
        })->afterCreating(function (Draw $draw) use ($count, $prefix): void {
            $slotByToken = [];
            foreach ($draw->resultArray() ?? [] as $slot => $token) {
                $slotByToken[$token] = $slot;
            }
            $claimed = $draw->status === 'closed';

            foreach ((new TokenService)->tokenNames($count, $prefix) as $row) {
                DrawToken::create([
                    'did' => $draw->did,
                    'position' => $row['position'],
                    'token' => $row['token'],
                    'slot' => $slotByToken[$row['token']] ?? null,
                    'claimed_tpid' => $claimed ? 100 + $row['position'] : null,
                    'claimed_uid' => $claimed ? 1000 + $row['position'] : null,
                    'claimed' => $claimed ? $draw->closed : null,
                    'assigned_by' => $claimed ? 'pick' : null,
                ]);
            }
        });
    }

    public function executed(?string $beacon = null): static
    {
        $beacon ??= str_repeat('ab', 32);

        return $this->state(function (array $attributes) use ($beacon): array {
            $payload = json_decode($attributes['payload'], true);
            $run = (new DrawService)->execute($payload, $attributes['server_seed'], $beacon);

            return [
                'beacon_value' => $beacon,
                'final_seed' => $run['final_seed'],
                'result' => json_encode($run['assignment'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'executed' => now()->format('Y-m-d H:i:s'),
                'status' => $attributes['mode'] === 'token' ? 'picking' : 'executed',
            ];
        });
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'closed',
            'closed' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function discarded(string $reason = 'Testgrund'): static
    {
        return $this->state(fn (): array => [
            'discarded' => now()->format('Y-m-d H:i:s'),
            'discarded_reason' => $reason,
        ]);
    }
}
