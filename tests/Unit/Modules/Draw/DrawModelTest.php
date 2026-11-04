<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Draw;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DrawModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function revealCases(): array
    {
        return [
            'closed token' => ['closed', 'token', true],
            'executed direct' => ['executed', 'direct', true],
            'executed token' => ['executed', 'token', false],
            'picking token' => ['picking', 'token', false],
            'committed direct' => ['committed', 'direct', false],
            'closed direct' => ['closed', 'direct', true],
        ];
    }

    #[Test]
    #[DataProvider('revealCases')]
    public function is_revealed_truth_table(string $status, string $mode, bool $expected): void
    {
        $draw = new Draw(['status' => $status, 'mode' => $mode]);

        $this->assertSame($expected, $draw->isRevealed());
    }

    #[Test]
    public function datetime_columns_stay_raw_strings(): void
    {
        $draw = Draw::factory()->create(['created' => '2026-03-01 10:00:00']);

        $this->assertSame('2026-03-01 10:00:00', $draw->fresh()->created);
    }

    #[Test]
    public function tokens_are_ordered_by_position(): void
    {
        $draw = Draw::factory()->token(4)->create();
        $draw->tokens()->where('position', 1)->update(['position' => 9]);

        $this->assertSame([2, 3, 4, 9], $draw->tokens()->pluck('position')->all());
    }

    #[Test]
    public function payload_and_result_helpers_decode(): void
    {
        $draw = Draw::factory()->create();

        $this->assertSame('Testregel', $draw->payloadArray()['rule']);
        $this->assertNull($draw->resultArray());
        $this->assertSame([], (new Draw(['payload' => 'nope']))->payloadArray());
        $this->assertSame($draw->did, Draw::slug($draw->slug)->first()->did);
    }

    #[Test]
    public function factory_states_are_consistent(): void
    {
        $service = new DrawService;
        $draw = Draw::factory()->executed()->create()->fresh();

        $payload = $draw->payloadArray();
        $this->assertTrue($service->verifyCommitment($payload, $draw->server_seed, $draw->commitment));
        $this->assertSame(
            json_encode($service->execute($payload, $draw->server_seed, $draw->beacon_value)['assignment'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $draw->result,
        );
        $this->assertSame('executed', $draw->status);

        $token = Draw::factory()->token()->executed()->create()->fresh();
        $this->assertSame('picking', $token->status);
        $this->assertCount(6, $token->tokens);
        $this->assertNotNull($token->tokens->first()->slot);

        $closed = Draw::factory()->token()->closed()->create();
        $this->assertNotNull($closed->tokens->first()->claimed_tpid);

        $this->assertSame('Testgrund', Draw::factory()->discarded()->create()->discarded_reason);
    }
}
