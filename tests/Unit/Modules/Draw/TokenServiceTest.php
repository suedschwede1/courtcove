<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Draw;

use App\Modules\Draw\Services\DrawService;
use App\Modules\Draw\Services\TokenService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TokenServiceTest extends TestCase
{
    private function vectors(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/draw/vectors.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function token_name_vectors_are_reproduced(): void
    {
        $cases = $this->vectors()['token_names'];
        $this->assertNotEmpty($cases);

        foreach ($cases as $i => $c) {
            $this->assertSame($c['result'], (new TokenService)->tokenNames($c['count'], $c['prefix']), "token_names #$i");
        }
    }

    #[Test]
    public function token_payload_vectors_are_reproduced(): void
    {
        $cases = $this->vectors()['token_payload'];
        $this->assertNotEmpty($cases);

        foreach ($cases as $i => $c) {
            $this->assertSame($c['result'], (new TokenService)->payload($c['token_names'], $c['slots'], $c['rule']), "token_payload #$i");
        }
    }

    #[Test]
    public function assign_slots_vectors_are_reproduced(): void
    {
        $cases = $this->vectors()['assign_slots'];
        $this->assertNotEmpty($cases);
        $service = new TokenService;

        foreach ($cases as $i => $c) {
            if (isset($c['error'])) {
                try {
                    $service->assignSlots($c['tokens'], $c['slots']);
                    $this->fail("assign_slots #$i: expected an exception");
                } catch (\Throwable $e) {
                    $this->assertSame($c['error']['class'], $e::class, "assign_slots #$i class");
                    $this->assertSame($c['error']['message'], $e->getMessage(), "assign_slots #$i message");
                }

                continue;
            }

            $this->assertSame($c['result'], $service->assignSlots($c['tokens'], $c['slots']), "assign_slots #$i");
        }
    }

    #[Test]
    public function fallback_vectors_are_reproduced(): void
    {
        $cases = $this->vectors()['fallback'];
        $this->assertNotEmpty($cases);
        $service = new TokenService;
        $draw = new DrawService;

        foreach ($cases as $i => $c) {
            $call = fn () => $service->fallbackAssignment(
                $c['free_tokens'],
                $c['non_pickers'],
                $c['final_seed'],
                $c['final_seed'] ? $draw : null,
                $c['partner_map'],
                $c['fixed_groups'],
            );

            if (isset($c['error'])) {
                try {
                    $call();
                    $this->fail("fallback #$i: expected an exception");
                } catch (\Throwable $e) {
                    $this->assertSame($c['error']['class'], $e::class, "fallback #$i class");
                    $this->assertSame($c['error']['message'], $e->getMessage(), "fallback #$i message");
                }

                continue;
            }

            $this->assertSame($c['result'], $call(), "fallback #$i");
        }
    }

    #[Test]
    public function token_names_are_stable(): void
    {
        $names = (new TokenService)->tokenNames(3);

        $this->assertSame('T-01', $names[0]['token']);
        $this->assertSame(3, $names[2]['position']);
    }

    #[Test]
    public function fallback_sorts_tokens_and_players(): void
    {
        $fallback = (new TokenService)->fallbackAssignment(
            [['dtid' => 2, 'position' => 2], ['dtid' => 1, 'position' => 1]],
            [['tpid' => 9, 'uid' => 90], ['tpid' => 4, 'uid' => 40]],
        );

        $this->assertSame(1, $fallback[0]['dtid']);
        $this->assertSame(4, $fallback[0]['tpid']);
    }

    #[Test]
    public function fallback_avoids_the_partner_group(): void
    {
        $result = (new TokenService)->fallbackAssignment(
            [['dtid' => 1, 'position' => 1, 'group' => 'D'], ['dtid' => 2, 'position' => 2, 'group' => 'E']],
            [['tpid' => 1, 'uid' => 10], ['tpid' => 2, 'uid' => 20]],
            null,
            null,
            [1 => 9, 9 => 1],
            [9 => 'D'],
        );

        $this->assertSame(2, $result[0]['dtid']);
    }

    #[Test]
    public function partner_rule_is_dropped_when_unavoidable(): void
    {
        $result = (new TokenService)->fallbackAssignment(
            [['dtid' => 3, 'position' => 1, 'group' => 'D']],
            [['tpid' => 1, 'uid' => 10]],
            null,
            null,
            [1 => 9, 9 => 1],
            [9 => 'D'],
        );

        $this->assertSame(3, $result[0]['dtid']);
    }

    #[Test]
    public function fallback_allows_only_the_one_unavoidable_conflict(): void
    {
        $result = (new TokenService)->fallbackAssignment(
            [
                ['dtid' => 11, 'position' => 1, 'group' => 'D'],
                ['dtid' => 12, 'position' => 2, 'group' => 'D'],
                ['dtid' => 13, 'position' => 3, 'group' => 'E'],
            ],
            [['tpid' => 1, 'uid' => 10], ['tpid' => 2, 'uid' => 20], ['tpid' => 3, 'uid' => 30]],
            null,
            null,
            [1 => 91, 2 => 92, 3 => 93],
            [91 => 'D', 92 => 'D', 93 => 'E'],
        );

        $tokenGroups = [11 => 'D', 12 => 'D', 13 => 'E'];
        $partnerGroups = [1 => 'D', 2 => 'D', 3 => 'E'];
        $mixedConflicts = 0;

        foreach ($result as $entry) {
            if ($tokenGroups[$entry['dtid']] === $partnerGroups[$entry['tpid']]) {
                $mixedConflicts++;
            }
        }

        $this->assertSame(1, $mixedConflicts);
    }
}
