<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Draw;

use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\DrawService;
use App\Modules\Draw\Services\DrawVerifier;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DrawVerifierTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function verify(Draw $draw)
    {
        return (new DrawVerifier(new DrawService))->verify($draw->fresh());
    }

    #[Test]
    public function a_factory_draw_verifies(): void
    {
        $executed = $this->verify(Draw::factory()->executed()->create());
        $this->assertTrue($executed->ok());
        $this->assertTrue($executed->executed());

        $token = $this->verify(Draw::factory()->token()->executed()->closed()->create());
        $this->assertTrue($token->ok());
        $this->assertTrue($token->checks['tokens']);

        $committed = $this->verify(Draw::factory()->create());
        $this->assertTrue($committed->ok());
        $this->assertFalse($committed->executed());
        $this->assertTrue($committed->checks['commitment']);
        $this->assertNull($committed->checks['final_seed']);
    }

    #[Test]
    public function tampered_payload_fails_commitment(): void
    {
        $draw = Draw::factory()->executed()->create();
        $draw->update(['payload' => (new DrawService)->canonical(['entries' => [101, 102, 103, 999], 'slots' => ['A2', 'B2', 'C2', 'D2'], 'rule' => 'Testregel'])]);

        $this->assertSame(['commitment', 'result'], $this->verify($draw)->failures());
    }

    #[Test]
    public function non_canonical_payload_fails(): void
    {
        $draw = Draw::factory()->create();
        $draw->update(['payload' => str_replace('"Testregel"', '"Test\/regel"', $draw->payload)]);

        $this->assertContains('canonical', $this->verify($draw)->failures());
    }

    #[Test]
    public function verifier_flags_int_string_drift_in_result(): void
    {
        $draw = Draw::factory()->executed()->create();
        $drifted = preg_replace('/:(\d+)/', ':"$1"', $draw->result);
        $this->assertNotSame($draw->result, $drifted);
        $draw->update(['result' => $drifted]);

        $this->assertSame(['result'], $this->verify($draw)->failures());
    }

    #[Test]
    public function token_slot_drift_fails(): void
    {
        $draw = Draw::factory()->token()->executed()->closed()->create();
        [$a, $b] = $draw->tokens()->whereNotNull('slot')->limit(2)->get();
        [$slotA, $slotB] = [$a->slot, $b->slot];
        $a->update(['slot' => $slotB]);
        $b->update(['slot' => $slotA]);

        $this->assertSame(['tokens'], $this->verify($draw)->failures());
    }

    #[Test]
    public function commitment_only_failure(): void
    {
        $draw = Draw::factory()->executed()->create();
        $draw->update(['commitment' => str_repeat('0', 64)]);

        $this->assertSame(['commitment'], $this->verify($draw)->failures());
    }

    #[Test]
    public function final_seed_mismatch_fails(): void
    {
        $draw = Draw::factory()->executed()->create();
        $draw->update(['final_seed' => str_repeat('0', 64)]);

        $this->assertSame(['final_seed'], $this->verify($draw)->failures());
    }

    #[Test]
    public function missing_token_row_fails(): void
    {
        $draw = Draw::factory()->token()->executed()->closed()->create();
        $draw->tokens()->whereNotNull('slot')->first()->delete();

        $this->assertSame(['tokens'], $this->verify($draw)->failures());
    }

    #[Test]
    public function golden_vectors_verify_when_stored(): void
    {
        $cases = json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/draw/vectors.json'), true, flags: JSON_THROW_ON_ERROR)['draw'];
        $this->assertNotEmpty($cases);

        foreach ($cases as $c) {
            $draw = Draw::factory()->create([
                'payload' => $c['canonical'],
                'commitment' => $c['commitment'],
                'server_seed' => $c['server_seed'],
                'beacon_value' => $c['beacon_value'],
                'final_seed' => $c['final_seed'],
                'result' => $c['assignment_json'],
                'status' => 'executed',
            ]);

            $v = $this->verify($draw);
            $this->assertTrue($v->ok(), $c['name'].': '.implode(',', $v->failures()));
        }
    }
}
