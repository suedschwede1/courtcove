<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tournament;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TournamentVectorsFileTest extends TestCase
{
    private const FORMAT_KEYS = [
        'version', 'match_format', 'ranking', 'points_per_win', 'tiebreak_scope', 'criteria', 'runner_up', 'mode', 'phases',
        'key', 'type', 'label', 'seed_range', 'heads', 'groups', 'size', 'draw', 'slot_order', 'qualify', 'from', 'rule',
        'pool', 'count', 'seeding', 'order', 'lines', 'seeded', 'bye', 'pools', 'pool_codes', 'prefix', 'start', 'protect',
        'no_same_pool_in_round_1', 'swap_rules', 'if_same_group_as_line', 'seed', 'swap_with_seed', 'timing',
    ];

    /** @return array<string, mixed> */
    private function vectors(): array
    {
        return json_decode((string) file_get_contents(base_path('tests/Fixtures/tournament/vectors.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function vectors_carry_provenance_and_every_section(): void
    {
        $v = $this->vectors();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $v['meta']['old_repo_commit']);
        $this->assertStringStartsWith('8.4.', $v['meta']['php_version']);
        $this->assertNotEmpty($v['meta']['generated_at']);
        $this->assertNotEmpty($v['meta']['files']);
        foreach (['format', 'generator', 'standings', 'runner_up', 'resolver', 'lot', 'manager'] as $k) {
            $this->assertNotEmpty($v[$k], $k);
        }
        $names = array_column($v['lot'], 'name');
        foreach (['first-executed', 'first-discarded', 'all-discarded', 'no-draw', 'only-committed', 'single-tied', 'unsorted-tied',
            'manual-wins', 'manual-empty-list-falls-back', 'scope-group', 'scope-place-1', 'scope-place-2', 'garbage-lot-orders'] as $name) {
            $this->assertContains($name, $names);
        }
    }

    #[Test]
    public function lot_cases_carry_the_hmac_only_when_a_seed_exists(): void
    {
        foreach ($this->vectors()['lot'] as $case) {
            $this->assertSame($case['expected_seed'] === null, $case['expected_hmac'] === null, $case['name']);
            if ($case['expected_seed'] !== null) {
                $this->assertSame(hash_hmac('sha256', $case['scope'], $case['expected_seed']), $case['expected_hmac'], $case['name']);
            }
        }
    }

    #[Test]
    public function format_section_covers_the_fixture_formats_and_the_invalid_variants(): void
    {
        $byName = array_column($this->vectors()['format'], null, 'name');

        foreach (['reference', 'small-two-groups', 'three-groups-with-bye'] as $name) {
            $this->assertArrayHasKey('ok', $byName[$name]['validate'], $name);
        }
        foreach (['invalid-bracket-size-12', 'invalid-group-size-5', 'invalid-pool-violates-round-1-protection', 'invalid-unknown-phase-type'] as $name) {
            $this->assertArrayHasKey('error', $byName[$name]['validate'], $name);
        }
    }

    #[Test]
    public function reference_format_matches_the_vector_input(): void
    {
        $byName = array_column($this->vectors()['format'], null, 'name');
        $file = json_decode((string) file_get_contents(base_path('tests/Fixtures/tournament/formats/reference.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($file, $byName['reference']['format']);
        $this->assertSame('pre', $file['phases'][3]['draw']['timing']);
    }

    /** @return array<string, array{string}> */
    public static function formatFiles(): array
    {
        return [
            'reference' => ['reference'],
            'small-two-groups' => ['small-two-groups'],
            'three-groups-with-bye' => ['three-groups-with-bye'],
        ];
    }

    #[Test]
    #[DataProvider('formatFiles')]
    public function format_fixtures_contain_no_personal_data(string $name): void
    {
        $raw = (string) file_get_contents(base_path("tests/Fixtures/tournament/formats/$name.json"));

        $this->assertStringNotContainsString('@', $raw);
        $this->assertSame(0, preg_match('/\d{6,}/', $raw));

        $this->assertSame([], $this->unknownKeys(json_decode($raw, true, flags: JSON_THROW_ON_ERROR), 'root'), $name);
    }

    /**
     * Keys must come from the format vocabulary. Numeric keys (seed => line) and the pool names under
     * `pools` / `pool_codes` are data, not vocabulary.
     *
     * @return list<string>
     */
    private function unknownKeys(mixed $node, string $path): array
    {
        if (! is_array($node)) {
            return [];
        }

        $unknown = [];
        foreach ($node as $key => $value) {
            $isPoolName = in_array(substr(strrchr(".$path", '.'), 1), ['pools', 'pool_codes'], true);
            if (! is_int($key) && ! ctype_digit((string) $key) && ! $isPoolName && ! in_array($key, self::FORMAT_KEYS, true)) {
                $unknown[] = "$path.$key";
            }
            $unknown = array_merge($unknown, $this->unknownKeys($value, "$path.$key"));
        }

        return $unknown;
    }

    #[Test]
    public function player_labels_in_the_vectors_are_fictional(): void
    {
        $raw = (string) file_get_contents(base_path('tests/Fixtures/tournament/vectors.json'));

        preg_match_all('/"label": "([^"]*)"/', $raw, $m);
        foreach (array_unique($m[1]) as $label) {
            $this->assertMatchesRegularExpression('/^(Demo Spieler \d{2}|Oberer Bewerb|Unterer Bewerb|KO-Bewerb [AB]|Gruppenphase|KO-Runde|Vorrunde|KO-Runde mit Freilos)$/', $label);
        }
        $this->assertStringNotContainsString('@', $raw);
    }
}
