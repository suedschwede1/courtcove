<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Draw;

use App\Models\User;
use App\Modules\Draw\Contracts\BeaconService;
use App\Modules\Draw\Models\Draw;
use App\Modules\Draw\Services\CommitRefused;
use App\Modules\Draw\Services\DrawCommitService;
use App\Modules\Draw\Services\DrawService;
use App\Modules\Draw\Services\DrawVerifier;
use App\Modules\Draw\Services\PayloadGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakeBeaconService;
use Tests\TestCase;

class DrawCommitServiceTest extends TestCase
{
    use RefreshDatabase;

    private const GENESIS = 1700000000;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(BeaconService::class, new FakeBeaconService(self::GENESIS, 30));
        Date::setTestNow(Date::createFromTimestamp(self::GENESIS + 999 * 30)); // current round 1000
        $this->admin = User::factory()->status('admin')->create();
    }

    /** @param array<string, string> $override */
    private function input(array $override = []): array
    {
        return $override + [
            'title' => 'KO-Raster A Demo',
            'entries' => "101\n102\nQ3\n104",
            'slots' => "S1\nS2\nS3\nS4",
            'rule' => 'Die gezogene Reihenfolge wird der Slot-Liste von oben nach unten zugeteilt.',
            'round' => '1100',
            'tid' => '',
        ];
    }

    /** @return list<array{0: string, 1: array<string, mixed>}> */
    private function refusal(array $input): array
    {
        try {
            app(DrawCommitService::class)->commitManual($input, $this->admin);
        } catch (CommitRefused $e) {
            return $e->errors;
        }

        $this->fail('no refusal');
    }

    #[Test]
    public function valid_input_commits_a_direct_draw(): void
    {
        $draw = app(DrawCommitService::class)->commitManual($this->input(['tid' => '7']), $this->admin);
        $draw = Draw::query()->findOrFail($draw->did);

        $this->assertSame('committed', $draw->status);
        $this->assertSame('direct', $draw->mode);
        $this->assertSame('drand:round:1100', $draw->beacon_source);
        $this->assertSame(7, (int) $draw->tid);
        $this->assertSame((int) $this->admin->uid, (int) $draw->created_uid);
        $this->assertSame(now()->format('Y-m-d H:i:s'), $draw->created);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $draw->slug);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $draw->server_seed);
        $this->assertNull($draw->final_seed);
        $this->assertSame([101, 102, 'Q3', 104], $draw->payloadArray()['entries']);
        $this->assertSame('{"entries":[101,102,"Q3",104],"slots":["S1","S2","S3","S4"],"rule":"Die gezogene Reihenfolge wird der Slot-Liste von oben nach unten zugeteilt."}', $draw->payload);

        $checks = app(DrawVerifier::class)->verify($draw)->checks;
        $this->assertTrue($checks['canonical']);
        $this->assertTrue($checks['commitment']);
    }

    #[Test]
    public function tid_zero_or_empty_is_stored_as_null(): void
    {
        $draw = app(DrawCommitService::class)->commitManual($this->input(['tid' => '0']), $this->admin);

        $this->assertNull(Draw::query()->findOrFail($draw->did)->tid);
    }

    #[Test]
    public function errors_are_collected_in_zf2_order(): void
    {
        $errors = $this->refusal($this->input(['title' => '  ', 'rule' => '', 'round' => '0', 'entries' => '101', 'slots' => "A2\nA2"]));

        $this->assertSame([
            ['draw::admin.commit.title_missing', []],
            ['draw::admin.commit.rule_missing', []],
            ['draw::admin.commit.round_invalid', []],
            ['draw::admin.commit.min_entries', []],
            ['draw::admin.commit.count_mismatch', ['entries' => 1, 'slots' => 2]],
            ['draw::admin.commit.duplicate_slots', []],
        ], $errors);

        $this->assertSame('Lose (1) und Slots (2) müssen gleich viele sein.', __('draw::admin.commit.count_mismatch', ['entries' => 1, 'slots' => 2]));
    }

    #[Test]
    public function duplicate_entries_are_reported(): void
    {
        $this->assertSame([['draw::admin.commit.duplicate_entries', []]], $this->refusal($this->input(['entries' => "101\n101\n102\n103"])));
    }

    #[Test]
    public function entry_regex_reports_only_the_first_offender(): void
    {
        $errors = $this->refusal($this->input(['entries' => "101\nab\n1.5\nQ123"]));

        $this->assertSame([['draw::admin.commit.invalid_entry', ['entry' => 'ab']]], $errors);
    }

    #[Test]
    public function numbers_that_would_not_survive_the_int_cast_are_refused(): void
    {
        $this->assertSame([['draw::admin.commit.entry_not_canonical', ['entry' => '0102']]], $this->refusal($this->input(['entries' => "101\n0102\nQ3\n104"])));
        $this->assertSame([['draw::admin.commit.entry_not_canonical', ['entry' => '99999999999999999999']]], $this->refusal($this->input(['entries' => "101\n99999999999999999999\nQ3\n104"])));
    }

    #[Test]
    public function verifier_unsafe_input_is_refused_after_the_field_checks(): void
    {
        $this->assertSame([['draw::admin.commit.numeric_slot', []]], $this->refusal($this->input(['slots' => "0\n1\n2\n3"])));
        $this->assertSame([['draw::admin.commit.line_separator', []]], $this->refusal($this->input(['rule' => "Regel\u{2028}zweite Zeile"])));
        $this->assertSame([['draw::admin.commit.invalid_utf8', []]], $this->refusal($this->input(['title' => "Titel \xC3"])));
    }

    #[Test]
    public function db_limits_are_checked(): void
    {
        $this->assertSame([['draw::admin.commit.title_too_long', ['max' => 160]]], $this->refusal($this->input(['title' => str_repeat('ä', 161)])));
        $this->assertSame([['draw::admin.commit.tid_invalid', []]], $this->refusal($this->input(['tid' => '4294967296'])));
        $this->assertSame([['draw::admin.commit.tid_invalid', []]], $this->refusal($this->input(['tid' => 'abc'])));

        $draw = app(DrawCommitService::class)->commitManual($this->input(['title' => str_repeat('ä', 160)]), $this->admin);
        $this->assertSame(160, mb_strlen($draw->title));
    }

    #[Test]
    public function past_round_is_refused_with_the_beacon_message(): void
    {
        $errors = $this->refusal($this->input(['round' => '1000']));

        $this->assertSame([['draw::admin.round_not_future', ['round' => 1000, 'current' => 1000]]], $errors);
        $this->assertSame('drand-Runde 1000 ist nicht zukünftig (aktuelle Runde: 1000).', __('draw::admin.round_not_future', ['round' => 1000, 'current' => 1000]));
    }

    #[Test]
    public function an_unreachable_beacon_is_refused_with_its_message(): void
    {
        $fake = new FakeBeaconService(self::GENESIS, 30);
        $fake->unreachable = true;
        $this->app->instance(BeaconService::class, $fake);

        $this->assertSame([['draw::admin.commit.beacon_failed', ['message' => 'no drand endpoint reachable']]], $this->refusal($this->input()));
    }

    #[Test]
    public function nothing_is_written_on_any_refusal(): void
    {
        foreach ([
            ['title' => ''],
            ['entries' => "101\nab\nQ3\n104"],
            ['slots' => "0\n1\n2\n3"],
            ['rule' => "a\u{2029}b"],
            ['round' => '999'],
        ] as $override) {
            $this->refusal($this->input($override));
        }

        $this->assertSame(0, Draw::query()->count());
    }

    #[Test]
    public function slugs_are_unique_hex_and_give_up_after_twenty_attempts(): void
    {
        $service = app(DrawCommitService::class);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $service->uniqueSlug());

        $calls = 0;
        $stuck = new DrawCommitService(app(DrawService::class), app(BeaconService::class), app(PayloadGuard::class), function () use (&$calls): string {
            $calls++;

            return 'aaaaaaaaaaaa';
        });
        Draw::factory()->create(['slug' => 'aaaaaaaaaaaa']);

        try {
            $stuck->uniqueSlug();
            $this->fail('no exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('could not generate a unique slug', $e->getMessage());
        }
        $this->assertSame(20, $calls);
    }
}
