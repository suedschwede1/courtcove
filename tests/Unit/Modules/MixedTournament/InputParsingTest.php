<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MixedTournament;

use App\Modules\MixedTournament\Domain\MixedRuleException;
use App\Modules\MixedTournament\Services\AgeCalculator;
use App\Modules\MixedTournament\Services\MemberCsvParser;
use App\Modules\MixedTournament\Support\ParticipantInput;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InputParsingTest extends TestCase
{
    #[Test]
    public function age_is_full_years_at_event_date(): void
    {
        $a = new AgeCalculator;
        $this->assertSame(25, $a->ageFromBirthDate('2000-06-15', '2026-06-14'));
        $this->assertSame(26, $a->ageFromBirthDate('2000-06-15', '2026-06-15'));
    }

    #[Test]
    public function future_birth_date_is_null(): void
    {
        $this->assertNull((new AgeCalculator)->ageFromBirthDate('2030-01-01', '2026-06-15'));
    }

    #[Test]
    public function invalid_date_is_null(): void
    {
        $a = new AgeCalculator;
        $this->assertNull($a->ageFromBirthDate('not a date', '2026-06-15'));
        $this->assertNull($a->ageFromBirthDate('2000-06-15', 'nonsense'));
        $this->assertNull($a->ageFromBirthDate(null, '2026-06-15'));
        $this->assertNull($a->ageFromBirthDate('', '2026-06-15'));
    }

    #[Test]
    public function normalize_age_bounds(): void
    {
        $a = new AgeCalculator;
        $this->assertNull($a->normalizeAge(null));
        $this->assertNull($a->normalizeAge(''));
        $this->assertSame(0, $a->normalizeAge('0'));
        $this->assertSame(120, $a->normalizeAge('120'));
        $this->assertSame(33, $a->normalizeAge(33));
        foreach (['121', '-1', 'x'] as $bad) {
            try {
                $a->normalizeAge($bad);
                $this->fail("expected exception for $bad");
            } catch (InvalidArgumentException $e) {
                $this->assertSame('age must be between 0 and 120', $e->getMessage());
            }
        }
    }

    #[Test]
    public function participant_input_rules(): void
    {
        $this->assertSame(
            ['label' => 'Spieler 01', 'gender' => 'f', 'itn' => 7.5, 'age' => 33, 'no_extra_match' => true],
            ParticipantInput::parse(['label' => '  Spieler 01 ', 'gender' => 'f', 'itn' => ' 7.5 ', 'age' => ' 33 ', 'no_extra_match' => '1']),
        );
        $this->assertSame(
            ['label' => 'Spieler 02', 'gender' => null, 'itn' => null, 'age' => null, 'no_extra_match' => false],
            ParticipantInput::parse(['label' => 'Spieler 02', 'gender' => 'x', 'itn' => '', 'age' => '']),
        );
        $this->assertSame('m', ParticipantInput::parse(['label' => 'A', 'gender' => 'm'])['gender']);
        $this->assertNull(ParticipantInput::parse(['label' => 'A', 'gender' => 'F'])['gender']);
        $this->assertSame(120, ParticipantInput::parse(['label' => 'A', 'age' => '120'])['age']);
        $this->assertFalse(ParticipantInput::parse(['label' => 'A', 'no_extra_match' => '0'])['no_extra_match']);
    }

    #[Test]
    public function participant_input_empty_label_is_name_required(): void
    {
        foreach ([[], ['label' => ''], ['label' => '   ']] as $post) {
            try {
                ParticipantInput::parse($post);
                $this->fail('expected MixedRuleException');
            } catch (MixedRuleException $e) {
                $this->assertSame('name_required', $e->key);
                $this->assertSame('name_required', $e->getMessage());
                $this->assertSame([], $e->replace);
            }
        }
    }

    #[Test]
    public function participant_input_age_out_of_range_is_age_range(): void
    {
        foreach (['121', '-1', 'x', '1.5', '12a'] as $age) {
            try {
                ParticipantInput::parse(['label' => 'A', 'age' => $age]);
                $this->fail("expected MixedRuleException for $age");
            } catch (MixedRuleException $e) {
                $this->assertSame('age_range', $e->key);
            }
        }
    }

    #[Test]
    public function parses_columns_by_name_in_any_order(): void
    {
        $r = (new MemberCsvParser)->parse("Geschlecht;Vorname;ITN;Nachname;GebDatum\nweiblich;Anna;ITN 5,2;Beispiel;01.02.1990\nmaennlich;Bert;;Muster;\n");
        $this->assertNull($r['missing_column']);
        $this->assertSame([
            ['label' => 'Beispiel Anna', 'gender' => 'f', 'itn' => 5.2, 'birth_date' => '1990-02-01'],
            ['label' => 'Muster Bert', 'gender' => 'm', 'itn' => null, 'birth_date' => null],
        ], $r['rows']);
    }

    #[Test]
    public function windows_1252_umlauts_become_utf8(): void
    {
        $r = (new MemberCsvParser)->parse("Nachname;Vorname\nBeispiel;J\xFCrgen");
        $this->assertSame('Beispiel Jürgen', $r['rows'][0]['label']);
    }

    #[Test]
    public function itn_has_at_most_two_digits_before_the_comma(): void
    {
        $r = (new MemberCsvParser)->parse("Nachname;Vorname;ITN\nA;B;ITN 17,1\nC;D;ITN 123,4\nE;F;ITN 1234,5");
        $this->assertSame([17.1, null, null], array_column($r['rows'], 'itn'));
    }

    #[Test]
    public function placeholder_birth_dates_give_no_age(): void
    {
        $a = new AgeCalculator;
        $this->assertSame(120, $a->ageFromBirthDate('1906-06-15', '2026-06-15'));
        $this->assertNull($a->ageFromBirthDate('1905-06-14', '2026-06-15'));
        $this->assertNull($a->ageFromBirthDate('1900-01-01', '2026-06-15'));
        $this->assertNull($a->ageFromBirthDate('0001-01-01', '2026-06-15'));
    }

    #[Test]
    public function bom_is_stripped(): void
    {
        $r = (new MemberCsvParser)->parse("\xEF\xBB\xBFNachname;Vorname\nMuster;Max");
        $this->assertNull($r['missing_column']);
        $this->assertSame('Muster Max', $r['rows'][0]['label']);
    }

    #[Test]
    public function itn_text_and_gender_and_birth_date(): void
    {
        $r = (new MemberCsvParser)->parse("Nachname;Vorname;Geschlecht;ITN;GebDatum\nA;B;unbekannt;ITN 7,1;03.04.1980\nC;D;weiblich;kein Wert;unbekannt");
        $this->assertSame(7.1, $r['rows'][0]['itn']);
        $this->assertNull($r['rows'][0]['gender']);
        $this->assertSame('1980-04-03', $r['rows'][0]['birth_date']);
        $this->assertNull($r['rows'][1]['itn']);
        $this->assertSame('f', $r['rows'][1]['gender']);
        $this->assertNull($r['rows'][1]['birth_date']);
    }

    #[Test]
    public function missing_required_column_is_reported(): void
    {
        $r = (new MemberCsvParser)->parse("Nachname;Geschlecht\nMuster;weiblich");
        $this->assertSame('Vorname', $r['missing_column']);
        $this->assertSame([], $r['rows']);

        $r = (new MemberCsvParser)->parse("Vorname;Geschlecht\nMax;weiblich");
        $this->assertSame('Nachname', $r['missing_column']);
    }

    #[Test]
    public function blank_lines_are_skipped(): void
    {
        $r = (new MemberCsvParser)->parse("Nachname;Vorname\r\n\r\nA;B\r\n   \r\nC;D\r\n\r\n");
        $this->assertSame(['A B', 'C D'], array_column($r['rows'], 'label'));
    }

    #[Test]
    public function quoted_semicolons_stay_in_the_field(): void
    {
        $r = (new MemberCsvParser)->parse("Nachname;Vorname\n\"Muster; Dr.\";Max");
        $this->assertSame('Muster; Dr. Max', $r['rows'][0]['label']);
    }

    #[Test]
    public function short_lines_and_empty_content_do_not_fail(): void
    {
        $r = (new MemberCsvParser)->parse("Nachname;Vorname;ITN\nMuster");
        $this->assertSame('Muster', $r['rows'][0]['label']);
        $this->assertNull($r['rows'][0]['itn']);

        $r = (new MemberCsvParser)->parse('');
        $this->assertSame('Nachname', $r['missing_column']);
    }
}
