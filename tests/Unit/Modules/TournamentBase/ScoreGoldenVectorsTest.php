<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TournamentBase;

use App\Modules\TournamentBase\Services\InvalidScoreException;
use App\Modules\TournamentBase\Services\ScoreService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScoreGoldenVectorsTest extends TestCase
{
    private function vectors(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/draw/vectors.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function evaluate_matches_the_legacy_results(): void
    {
        $fix = ['muessen' => 'müssen', 'vollstaendig' => 'vollständig', 'Saetzen' => 'Sätzen', 'Punktestaende' => 'Punktestände',
            'Gamestaende' => 'Gamestände', 'gueltiges' => 'gültiges', 'Moeglich' => 'Möglich', 'moeglich' => 'möglich', 'ueber' => 'über'];
        $service = new ScoreService;
        $cases = $this->vectors()['score_evaluate'];
        $this->assertNotEmpty($cases);

        foreach ($cases as $i => $case) {
            if (isset($case['error'])) {
                try {
                    $service->evaluate($case['input']);
                    $this->fail("case $i: expected exception");
                } catch (InvalidScoreException $e) {
                    $this->assertSame(strtr($case['error']['message'], $fix), __($e->key, $e->replace, 'de'), "case $i");
                }

                continue;
            }
            $this->assertSame($case['result'], $service->evaluate($case['input']), "case $i");
        }
    }

    #[Test]
    public function describe_matches_the_legacy_results_and_has_english_texts(): void
    {
        $service = new ScoreService;
        $cases = $this->vectors()['score_describe'];
        $this->assertNotEmpty($cases);

        foreach ($cases as $i => $case) {
            $new = $service->describe($case['input'], $case['has_schedule']);
            $old = $case['result'];

            $this->assertSame([], $new['replace'], "case $i");
            $oldMessage = $old['message'];
            unset($old['message'], $new['replace']);
            $newMessage = $new['message'];
            unset($new['message']);
            $this->assertSame($old, $new, "case $i");

            if ($oldMessage === null) {
                $this->assertNull($newMessage, "case $i");

                continue;
            }
            $this->assertSame($oldMessage, __($newMessage, [], 'de'), "case $i");
            $this->assertNotSame($newMessage, trans($newMessage, [], 'en'), "case $i en");
        }
    }

    #[Test]
    public function every_score_key_exists_in_both_languages(): void
    {
        foreach (['de', 'en'] as $locale) {
            $lines = require dirname(__DIR__, 3).'/../app/Modules/TournamentBase/resources/lang/'.$locale.'/score.php';
            foreach (['sets_incomplete', 'mtb_after_two_sets', 'mtb_required', 'mtb_both_scores', 'set_range', 'set_invalid',
                'mtb_negative', 'mtb_min_ten', 'mtb_ten_nine', 'mtb_two_apart', 'mtb_draw', 'not_integer'] as $key) {
                $this->assertArrayHasKey($key, $lines, "$locale $key");
            }
            foreach (['schedule_only', 'enter_sets', 'set1_invalid', 'set2_invalid', 'mtb_must_be_empty', 'mtb_needed', 'mtb_invalid'] as $key) {
                $this->assertArrayHasKey($key, $lines['describe'], "$locale describe.$key");
            }
        }
    }
}
