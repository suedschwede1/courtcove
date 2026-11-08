<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Mail\WhatsAppBudgetAlert;
use App\Models\NotificationConsent;
use App\Models\NotificationLogEntry;
use App\Models\NotificationUsageMonthly;
use App\Models\Option;
use App\Models\User;
use App\Models\UserMeta;
use App\Modules\Members\Models\Member;
use App\Notifications\AdminTestNotification;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Registry\NotificationRegistry;
use App\Notifications\Types\NotificationTypes;
use App\Services\Notifications\MessageContent;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\SubjectDeadline;
use App\Services\Notifications\SubjectResolver;
use App\Services\Notifications\Transport\FakeTransport;
use App\Services\Notifications\Transport\TransportResult;
use App\Services\Notifications\WhatsAppSettings;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase
{
    use BuildsConsentFixtures;
    use RefreshDatabase;

    private TestSearchResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'Europe/Vienna'));

        $this->resolver = new TestSearchResolver;
        app(NotificationRegistry::class)->useResolver(NotificationTypes::PLAYER_SEARCH_OPENED, $this->resolver);

        $this->opt('whatsapp.enabled', '1');
        $this->opt('whatsapp.driver', 'fake');
        $this->opt('whatsapp.budget_monthly', '1.00');
        $this->opt('whatsapp.price_estimate', '0.05');
        $this->opt('whatsapp.template.player_search_opened.de', 'HXtest-opened');
        $this->opt('whatsapp.template.test.de', 'HXtest-test');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function opt(string $key, string $value): void
    {
        Option::updateOrCreate(['key' => $key], ['value' => $value, 'locale' => null]);
    }

    private function fake(): FakeTransport
    {
        return app(FakeTransport::class);
    }

    private function dispatcher(): NotificationDispatcher
    {
        return app(NotificationDispatcher::class);
    }

    private function sender(): User
    {
        $sender = User::factory()->create(['status' => 'enabled']);
        $sender->setMeta('firstname', 'Erika');
        $sender->setMeta('lastname', 'Musterfrau');

        return $sender;
    }

    /** @param  array<string, mixed>  $attributes */
    private function row(User $user, array $attributes = []): NotificationLogEntry
    {
        $type = $attributes['type'] ?? NotificationTypes::PLAYER_SEARCH_OPENED;
        unset($attributes['type']);

        return NotificationLogEntry::create(array_merge([
            'type' => $type,
            'channel' => 'whatsapp',
            'user_id' => $user->uid,
            'subject_type' => 'player_search',
            'subject_id' => 7,
            'dedupe_key' => 'test:'.Str::uuid()->toString(),
            'status' => NotificationLogEntry::PENDING,
            'priority' => (int) app(NotificationRegistry::class)->type($type)?->priority,
            'subject_payload' => ['search_id' => 7, 'sender_id' => 0],
            'attempts' => 0,
            'not_before' => Carbon::now(),
        ], $attributes));
    }

    private function sentToday(User $user, int $count, string $at = '2026-11-10 08:00:00'): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->row($user, ['status' => NotificationLogEntry::SENT, 'sent_at' => Carbon::parse($at, 'Europe/Vienna')]);
        }
    }

    #[Test]
    public function sends_due_row_and_stores_sid(): void
    {
        $user = $this->optedInUser();
        $row = $this->row($user);

        $report = $this->dispatcher()->run();

        $row->refresh();
        $this->assertSame(NotificationLogEntry::SENT, $row->status);
        $this->assertStringStartsWith('fake-', (string) $row->provider_sid);
        $this->assertSame('2026-11-10 12:00:00', $row->sent_at?->format('Y-m-d H:i:s'));
        $this->assertSame(50_000, $row->cost_estimate_micros);
        $this->assertSame('00', $row->phone_last2);
        $this->assertSame(1, $report->sent);

        $sent = $this->fake()->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('+436600000000', $sent[0]['to']);
        $this->assertSame('HXtest-opened', $sent[0]['template']->contentId);
        $this->assertSame('player_search_opened', $sent[0]['template']->key);
        $this->assertSame('de', $sent[0]['template']->locale);
        $this->assertSame('search/7', $sent[0]['buttonSuffix']);

        $usage = NotificationUsageMonthly::sole();
        $this->assertSame(1, $usage->messages_sent);
        $this->assertSame(0, $usage->messages_reserved);
        $this->assertSame(50_000, $usage->estimated_micros);
    }

    #[Test]
    public function row_not_due_is_left_alone(): void
    {
        $user = $this->optedInUser();
        $row = $this->row($user, ['not_before' => Carbon::now()->addMinute()]);

        $this->dispatcher()->run();

        $this->assertSame(NotificationLogEntry::PENDING, $row->refresh()->status);
        $this->assertSame([], $this->fake()->sent());
    }

    /** @return array<string, array{string}> */
    public static function skipReasons(): array
    {
        return [
            'disabled' => ['disabled'],
            'subject_gone' => ['subject_gone'],
            'subject_gone without resolver' => ['no_resolver'],
            'subject_gone for an unregistered type' => ['unknown_type'],
            'type_disabled' => ['type_disabled'],
            'no_consent' => ['no_consent'],
            'gate (exchange off)' => ['gate'],
            'daily_limit' => ['daily_limit'],
            'budget' => ['budget'],
            'template_missing' => ['template_missing'],
        ];
    }

    #[Test]
    #[DataProvider('skipReasons')]
    public function skips_with_reason(string $case): void
    {
        $user = $case === 'no_consent' ? $this->eligibleUser() : $this->optedInUser();
        $type = NotificationTypes::PLAYER_SEARCH_OPENED;
        $expected = $case;

        switch ($case) {
            case 'disabled':
                $this->opt('whatsapp.enabled', '0');
                break;
            case 'subject_gone':
                $this->resolver->relevant = false;
                break;
            case 'no_resolver':
                // The follow-up type is off by default; switched on, its resolver finds no search 7.
                $this->opt('whatsapp.followup_closed', '1');
                $type = NotificationTypes::PLAYER_SEARCH_CLOSED;
                $expected = 'subject_gone';
                break;
            case 'unknown_type':
                $type = 'retired.type';
                $expected = 'subject_gone';
                break;
            case 'type_disabled':
                $this->opt('whatsapp.type.'.NotificationTypes::PLAYER_SEARCH_OPENED.'.enabled', '0');
                break;
            case 'gate':
                // Raw update: no hook runs, the send-time gate keeps the row out.
                UserMeta::query()->where('uid', $user->uid)->where('key', 'player-exchange')->update(['value' => '0']);
                break;
            case 'daily_limit':
                $this->sentToday($user, 3);
                break;
            case 'budget':
                $this->opt('whatsapp.budget_monthly', '0.04');
                break;
            case 'template_missing':
                Option::where('key', 'whatsapp.template.player_search_opened.de')->delete();
                break;
        }

        $row = $this->row($user, ['type' => $type]);
        $report = $this->dispatcher()->run();

        $row->refresh();
        $this->assertSame(NotificationLogEntry::SKIPPED, $row->status);
        $this->assertSame($expected, $row->skip_reason);
        $this->assertSame([], $this->fake()->sent());
        $this->assertSame(1, $report->skipped[$expected] ?? 0);
        // A skipped row never keeps budget.
        $this->assertSame(0, (int) NotificationUsageMonthly::query()->sum('estimated_micros'));
        $this->assertSame(0, (int) NotificationUsageMonthly::query()->sum('messages_reserved'));
    }

    #[Test]
    public function daily_limit_counts_the_vienna_day_and_not_other_users(): void
    {
        $user = $this->optedInUser();
        $this->sentToday($user, 3, '2026-11-09 23:30:00');
        $other = $this->optedInUser('+43 660 0000011');
        $this->sentToday($other, 3);
        $row = $this->row($user);

        $this->dispatcher()->run();

        $this->assertSame(NotificationLogEntry::SENT, $row->refresh()->status);
    }

    #[Test]
    public function admin_test_ignores_daily_limit(): void
    {
        $user = $this->optedInUser();
        $this->sentToday($user, 3);
        app(WhatsAppChannel::class)->send($user, new AdminTestNotification);

        $this->dispatcher()->run();

        $row = NotificationLogEntry::where('type', NotificationTypes::ADMIN_TEST)->sole();
        $this->assertSame(NotificationLogEntry::SENT, $row->status);
        $sent = $this->fake()->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('HXtest-test', $sent[0]['template']->contentId);
        $this->assertSame('test', $sent[0]['template']->key);
        $this->assertSame(trans('booking.notifications.whatsapp.test', [], 'de'), $sent[0]['template']->freeText);
    }

    #[Test]
    public function admin_test_skips_the_player_exchange_rule(): void
    {
        $user = $this->optedInUser();
        UserMeta::query()->where('uid', $user->uid)->where('key', 'player-exchange')->update(['value' => '0']);
        app(WhatsAppChannel::class)->send($user, new AdminTestNotification);

        $this->dispatcher()->run();

        $this->assertSame(NotificationLogEntry::SENT, NotificationLogEntry::sole()->status);
    }

    /** Only the booking account counts: no member record, or a dormant, departed or anonymised one, still gets the message. */
    #[Test]
    public function account_without_member_or_with_any_member_state_gets_the_message(): void
    {
        $users = [
            'none' => $this->optedInUser('+43 660 0000021'),
            'ruhend' => $this->optedInUser('+43 660 0000022'),
            'ausgetreten' => $this->optedInUser('+43 660 0000023'),
            'anonymisiert' => $this->optedInUser('+43 660 0000024'),
        ];
        $this->linkMember($users['ruhend'], ['status' => 'ruhend']);
        $this->linkMember($users['ausgetreten'], ['status' => 'ausgetreten', 'left_at' => '2026-10-31']);
        $this->linkMember($users['anonymisiert'], ['status' => 'ausgetreten', 'left_at' => '2020-01-01', 'anonymized_at' => '2026-11-01 10:00:00']);
        $this->assertSame(0, Member::query()->where('user_id', $users['none']->uid)->count());
        $this->opt('whatsapp.budget_monthly', '10.00');
        $rows = array_map(fn (User $u): NotificationLogEntry => $this->row($u), $users);

        $this->dispatcher()->run();

        foreach ($rows as $case => $row) {
            $this->assertSame(NotificationLogEntry::SENT, $row->refresh()->status, $case);
        }
        $this->assertEqualsCanonicalizing(
            ['+436600000021', '+436600000022', '+436600000023', '+436600000024'],
            array_column($this->fake()->sent(), 'to'),
        );
    }

    #[Test]
    public function blocked_or_disabled_account_gets_no_message(): void
    {
        $blocked = $this->optedInUser('+43 660 0000031');
        $disabled = $this->optedInUser('+43 660 0000032');
        $this->linkMember($blocked);
        $this->linkMember($disabled);
        $rows = [$this->row($blocked), $this->row($disabled)];
        // Raw updates: no hook revokes, the send-time gate must hold even for an active member.
        User::query()->whereKey($blocked->uid)->update(['status' => 'blocked']);
        User::query()->whereKey($disabled->uid)->update(['status' => 'disabled']);

        $this->dispatcher()->run();

        foreach ($rows as $row) {
            $this->assertSame(NotificationLogEntry::SKIPPED, $row->refresh()->status);
            $this->assertSame('gate', $row->skip_reason);
        }
        $this->assertSame([], $this->fake()->sent());
    }

    #[Test]
    public function phone_changed_via_bulk_path_is_not_sent_and_revokes(): void
    {
        $user = $this->optedInUser();
        UserMeta::query()->where('uid', $user->uid)->where('key', 'phone')->update(['value' => '+43 660 0000033']);
        $row = $this->row($user);

        $this->dispatcher()->run();

        $row->refresh();
        $this->assertSame(NotificationLogEntry::SKIPPED, $row->status);
        $this->assertSame('phone_changed', $row->skip_reason);
        $this->assertSame([], $this->fake()->sent());
        $this->assertSame(0, $this->activeConsentCount($user));
        $this->assertSame('phone_changed', $this->lastRevokedVia($user));
    }

    #[Test]
    public function blocked_by_bulk_update_is_not_sent(): void
    {
        $user = $this->optedInUser();
        User::query()->whereIn('uid', [$user->uid])->update(['status' => 'blocked']);
        $row = $this->row($user);

        $this->dispatcher()->run();

        $row->refresh();
        $this->assertSame(NotificationLogEntry::SKIPPED, $row->status);
        $this->assertSame('gate', $row->skip_reason);
        $this->assertSame([], $this->fake()->sent());
    }

    #[Test]
    public function retry_backoff_1_5_15_then_failed_after_three_attempts(): void
    {
        $user = $this->optedInUser();
        $row = $this->row($user);
        $this->fake()->willReturn(TransportResult::failed('http_503', true));

        foreach ([1, 5, 15] as $attempt => $minutes) {
            $this->dispatcher()->run();
            $row->refresh();
            $this->assertSame(NotificationLogEntry::PENDING, $row->status);
            $this->assertSame($attempt + 1, $row->attempts);
            $this->assertSame(Carbon::now()->addMinutes($minutes)->format('Y-m-d H:i:s'), $row->not_before->format('Y-m-d H:i:s'));

            // Not due yet: nothing happens before the backoff has passed.
            $this->dispatcher()->run();
            $this->assertSame($attempt + 1, $row->refresh()->attempts);

            Carbon::setTestNow(Carbon::now()->addMinutes($minutes));
        }

        $this->dispatcher()->run();

        $row->refresh();
        $this->assertSame(NotificationLogEntry::FAILED, $row->status);
        $this->assertSame('http_503', $row->error_code);
        $this->assertCount(4, $this->fake()->sent());
        // Rejected messages give their reservation back.
        $usage = NotificationUsageMonthly::sole();
        $this->assertSame(0, $usage->estimated_micros);
        $this->assertSame(0, $usage->messages_reserved);
    }

    #[Test]
    public function retry_not_after_deadline(): void
    {
        $user = $this->optedInUser();
        $row = $this->row($user);
        $this->resolver->deadline = Carbon::now()->addMinutes(4);
        $this->fake()->willReturn(TransportResult::failed('timeout', true));

        $this->dispatcher()->run(); // +1 min, still before the deadline
        $this->assertSame(NotificationLogEntry::PENDING, $row->refresh()->status);

        Carbon::setTestNow(Carbon::now()->addMinute());
        $this->dispatcher()->run(); // +5 min would pass the deadline

        $row->refresh();
        $this->assertSame(NotificationLogEntry::FAILED, $row->status);
        $this->assertSame('timeout', $row->error_code);
        $this->assertCount(2, $this->fake()->sent());
    }

    #[Test]
    public function permanent_error_fails_without_retry(): void
    {
        $user = $this->optedInUser();
        $row = $this->row($user);
        $this->fake()->willReturn(TransportResult::failed('21211'));

        $report = $this->dispatcher()->run();
        Carbon::setTestNow(Carbon::now()->addHour());
        $this->dispatcher()->run();

        $row->refresh();
        $this->assertSame(NotificationLogEntry::FAILED, $row->status);
        $this->assertSame('21211', $row->error_code);
        $this->assertSame(1, $report->failed);
        $this->assertCount(1, $this->fake()->sent());
        $this->assertSame(0, NotificationUsageMonthly::sole()->estimated_micros);
    }

    #[Test]
    public function auth_failure_pauses_and_stops_the_run(): void
    {
        $user = $this->optedInUser();
        $rows = [$this->row($user), $this->row($user), $this->row($user)];
        $this->fake()->willReturn(TransportResult::failed('http_401', false, true));

        $report = $this->dispatcher()->run();

        $this->assertSame('auth_failed', Option::getValue('whatsapp.paused_reason'));
        $this->assertSame('auth_failed', $report->stoppedReason);
        $this->assertCount(1, $this->fake()->sent());
        foreach ($rows as $row) {
            $this->assertSame(NotificationLogEntry::PENDING, $row->refresh()->status);
        }
        $this->assertSame(0, NotificationUsageMonthly::sole()->estimated_micros);

        // While paused nothing is claimed; the rows wait for "resume".
        $this->dispatcher()->run();
        $this->assertCount(1, $this->fake()->sent());
        $this->assertSame(3, NotificationLogEntry::where('status', NotificationLogEntry::PENDING)->count());
    }

    #[Test]
    public function stale_sending_row_is_not_resent(): void
    {
        $user = $this->optedInUser();
        $stale = $this->row($user, ['status' => NotificationLogEntry::SENDING, 'cost_estimate_micros' => 50_000]);
        DB::table('notification_log')->where('id', $stale->id)->update(['updated_at' => Carbon::now()->subMinutes(6)]);
        DB::table('notification_usage_monthly')->insert([
            'month' => '2026-11', 'channel' => 'whatsapp', 'messages_reserved' => 1, 'estimated_micros' => 50_000,
        ]);
        $fresh = $this->row($user, ['status' => NotificationLogEntry::SENDING]);
        DB::table('notification_log')->where('id', $fresh->id)->update(['updated_at' => Carbon::now()->subMinutes(2)]);

        $report = $this->dispatcher()->run();

        $stale->refresh();
        $this->assertSame(NotificationLogEntry::FAILED, $stale->status);
        $this->assertSame('stale', $stale->error_code);
        $this->assertSame(NotificationLogEntry::SENDING, $fresh->refresh()->status);
        $this->assertSame([], $this->fake()->sent());
        $this->assertSame(1, $report->stale);
        // The reservation is released; whether it was sent is unknown, so the cost is kept.
        $usage = NotificationUsageMonthly::sole();
        $this->assertSame(0, $usage->messages_reserved);
        $this->assertSame(50_000, $usage->estimated_micros);
    }

    #[Test]
    public function variables_are_rebuilt_from_subject_at_send_time(): void
    {
        $user = $this->optedInUser();
        $sender = $this->sender();
        $row = $this->row($user, ['subject_payload' => ['search_id' => 7, 'sender_id' => $sender->uid]]);

        $this->dispatcher()->run();

        $this->assertSame(NotificationLogEntry::SENT, $row->refresh()->status);
        $sent = $this->fake()->sent();
        $this->assertSame('Erika Musterfrau', $sent[0]['vars']['1']);
        $this->assertStringContainsString('Erika Musterfrau', $sent[0]['template']->freeText);

        $dump = (string) json_encode(DB::table('notification_log')->get()->all(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Erika', $dump);
        $this->assertStringNotContainsString('Musterfrau', $dump);
        $this->assertStringNotContainsString('sucht', $dump);
    }

    #[Test]
    public function fair_order_prefers_users_with_fewer_messages(): void
    {
        $busy = $this->optedInUser();
        $quiet = $this->optedInUser('+43 660 0000022');
        // The recipient resolver staggers not_before (1 s per step); a later id with an earlier slot goes first.
        $busyRow = $this->row($busy, ['not_before' => Carbon::now()->subSecond()]);
        $quietRow = $this->row($quiet, ['not_before' => Carbon::now()->subSeconds(2)]);

        $this->dispatcher()->run(1);

        $this->assertSame(NotificationLogEntry::SENT, $quietRow->refresh()->status);
        $this->assertSame(NotificationLogEntry::PENDING, $busyRow->refresh()->status);
        $this->assertSame('+436600000022', $this->fake()->sent()[0]['to']);
    }

    #[Test]
    public function priority_goes_before_slot(): void
    {
        $user = $this->optedInUser();
        $search = $this->row($user, ['not_before' => Carbon::now()->subMinute()]);
        app(WhatsAppChannel::class)->send($user, new AdminTestNotification);

        $this->dispatcher()->run(1);

        $this->assertSame(NotificationLogEntry::PENDING, $search->refresh()->status);
        $this->assertSame('test', $this->fake()->sent()[0]['template']->key);
    }

    #[Test]
    public function max_per_run_is_respected(): void
    {
        $this->opt('whatsapp.max_per_run', '2');
        $this->opt('whatsapp.daily_limit_per_user', '10');
        $user = $this->optedInUser();
        foreach (range(1, 3) as $i) {
            $this->row($user);
        }

        $report = $this->dispatcher()->run();

        $this->assertSame(2, $report->sent);
        $this->assertCount(2, $this->fake()->sent());
        $this->assertSame(1, NotificationLogEntry::where('status', NotificationLogEntry::PENDING)->count());

        $this->dispatcher()->drain(1, 20);
        $this->assertCount(3, $this->fake()->sent());
    }

    #[Test]
    public function consent_for_another_purpose_counts_as_no_consent(): void
    {
        $user = $this->optedInUser();
        NotificationConsent::query()->where('user_id', $user->uid)->update(['purpose' => 'other']);
        $row = $this->row($user);

        $this->dispatcher()->run();

        $this->assertSame('no_consent', $row->refresh()->skip_reason);
    }

    #[Test]
    public function an_exception_while_building_fails_the_row_and_the_run_goes_on(): void
    {
        $user = $this->optedInUser();
        $broken = $this->row($user);
        $good = $this->row($user);
        $this->resolver->throwFor = $broken->id;

        $report = $this->dispatcher()->run();

        $broken->refresh();
        $this->assertSame(NotificationLogEntry::FAILED, $broken->status);
        $this->assertSame('exception', $broken->error_code);
        $this->assertSame(NotificationLogEntry::SENT, $good->refresh()->status);
        $this->assertSame(1, $report->sent);
        $this->assertSame(0, NotificationUsageMonthly::sole()->messages_reserved);
    }

    #[Test]
    public function a_failing_settle_after_an_accepted_send_keeps_the_row_sent(): void
    {
        $user = $this->optedInUser();
        $row = $this->row($user);
        NotificationUsageMonthly::updating(function (NotificationUsageMonthly $usage): void {
            if ($usage->isDirty('messages_sent')) {
                throw new \RuntimeException('settle broken');
            }
        });

        $report = $this->dispatcher()->run();

        $row->refresh();
        $this->assertSame(NotificationLogEntry::SENT, $row->status);
        $this->assertStringStartsWith('fake-', (string) $row->provider_sid);
        $this->assertNull($row->error_code);
        $this->assertSame(1, $report->sent);
        // The reservation is not freed: the message did go out.
        $usage = NotificationUsageMonthly::sole();
        $this->assertSame(1, $usage->messages_reserved);
        $this->assertSame(50_000, $usage->estimated_micros);

        Carbon::setTestNow(Carbon::now()->addMinutes(10));
        $this->dispatcher()->run();
        $this->assertCount(1, $this->fake()->sent());
    }

    #[Test]
    public function a_failing_store_after_an_accepted_send_leaves_the_row_sending_and_never_resends(): void
    {
        $user = $this->optedInUser();
        $row = $this->row($user);
        NotificationLogEntry::updating(function (NotificationLogEntry $entry): void {
            if ($entry->status === NotificationLogEntry::SENT) {
                throw new \RuntimeException('store broken');
            }
        });

        $this->dispatcher()->run();

        $stored = DB::table('notification_log')->where('id', $row->id)->first();
        $this->assertSame(NotificationLogEntry::SENDING, $stored->status);
        $this->assertNull($stored->error_code);
        $this->assertCount(1, $this->fake()->sent());
        $usage = NotificationUsageMonthly::sole();
        $this->assertSame(1, $usage->messages_reserved);
        $this->assertSame(50_000, $usage->estimated_micros);

        $this->dispatcher()->run();
        $this->assertCount(1, $this->fake()->sent());

        // The stale sweep resolves it: failed, cost kept, reservation settled as accepted.
        Carbon::setTestNow(Carbon::now()->addMinutes(6));
        $this->dispatcher()->run();

        $row->refresh();
        $this->assertSame(NotificationLogEntry::FAILED, $row->status);
        $this->assertSame('stale', $row->error_code);
        $this->assertCount(1, $this->fake()->sent());
        $usage->refresh();
        $this->assertSame(0, $usage->messages_reserved);
        $this->assertSame(1, $usage->messages_sent);
        $this->assertSame(50_000, $usage->estimated_micros);
    }

    #[Test]
    public function a_pause_during_the_run_puts_the_next_row_back_and_ends_the_run(): void
    {
        $user = $this->optedInUser();
        $first = $this->row($user);
        $second = $this->row($user);
        $this->resolver->onBuild = fn () => app(WhatsAppSettings::class)->pause('auth_failed');

        $report = $this->dispatcher()->run();

        $this->assertSame(NotificationLogEntry::SENT, $first->refresh()->status);
        $second->refresh();
        $this->assertSame(NotificationLogEntry::PENDING, $second->status);
        $this->assertNull($second->skip_reason);
        $this->assertSame('paused', $report->stoppedReason);
        $this->assertCount(1, $this->fake()->sent());
    }

    #[Test]
    public function daily_limit_counts_rows_being_sent(): void
    {
        $user = $this->optedInUser();
        foreach (range(1, 3) as $i) {
            $this->row($user, ['status' => NotificationLogEntry::SENDING]);
        }
        $row = $this->row($user);

        $this->dispatcher()->run();

        $this->assertSame('daily_limit', $row->refresh()->skip_reason);
    }

    #[Test]
    public function daily_limit_does_not_count_admin_tests(): void
    {
        $user = $this->optedInUser();
        foreach (range(1, 3) as $i) {
            $this->row($user, [
                'type' => NotificationTypes::ADMIN_TEST,
                'status' => NotificationLogEntry::SENT,
                'sent_at' => Carbon::now()->subHour(),
            ]);
        }
        $row = $this->row($user);

        $this->dispatcher()->run();

        $this->assertSame(NotificationLogEntry::SENT, $row->refresh()->status);
    }

    #[Test]
    public function budget_alert_is_sent_after_settling(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test']);
        $this->opt('whatsapp.budget_monthly', '0.05');
        $this->opt('whatsapp.budget_alert_email', 'admin@example.test');
        $user = $this->optedInUser();
        $this->row($user);

        $this->dispatcher()->run();

        Mail::assertSent(WhatsAppBudgetAlert::class, fn (WhatsAppBudgetAlert $mail): bool => $mail->hasTo('admin@example.test'));
        $this->assertNotNull(NotificationUsageMonthly::sole()->warned_100_at);
    }

    #[Test]
    public function stale_rows_are_settled_outside_the_row_transaction(): void
    {
        $user = $this->optedInUser();
        $stale = $this->row($user, ['status' => NotificationLogEntry::SENDING, 'cost_estimate_micros' => 50_000]);
        DB::table('notification_log')->where('id', $stale->id)->update(['updated_at' => Carbon::now()->subMinutes(6)]);
        DB::table('notification_usage_monthly')->insert([
            'month' => '2026-11', 'channel' => 'whatsapp', 'messages_reserved' => 1, 'estimated_micros' => 50_000,
        ]);
        $baseline = DB::transactionLevel();
        $levels = [];
        NotificationUsageMonthly::updating(function () use (&$levels): void {
            $levels[] = DB::transactionLevel();
        });

        $this->dispatcher()->run();

        $this->assertSame('stale', $stale->refresh()->error_code);
        // Only settle()'s own transaction is open, never the row transaction around it.
        $this->assertSame([$baseline + 1], $levels);
        $this->assertSame(0, NotificationUsageMonthly::sole()->messages_reserved);
    }
}

/** Stand-in for the player search resolver (Task 10): rebuilds the name from the sender's user id. */
final class TestSearchResolver implements SubjectDeadline, SubjectResolver
{
    public bool $relevant = true;

    public ?CarbonInterface $deadline = null;

    public ?int $throwFor = null;

    /** @var (\Closure(): mixed)|null */
    public ?\Closure $onBuild = null;

    public function stillRelevant(NotificationLogEntry $row): bool
    {
        return $this->relevant;
    }

    public function deadline(NotificationLogEntry $row): ?CarbonInterface
    {
        return $this->deadline;
    }

    public function build(NotificationLogEntry $row, User $user, string $locale): MessageContent
    {
        if ($row->id === $this->throwFor) {
            throw new \RuntimeException('broken subject');
        }
        if ($this->onBuild !== null) {
            ($this->onBuild)();
            $this->onBuild = null;
        }
        $sender = User::find($row->subject_payload['sender_id'] ?? 0);
        $name = $sender === null ? '?' : trim($sender->getMeta('firstname').' '.$sender->getMeta('lastname'));

        return new MessageContent(
            variables: ['1' => $name, '2' => 'Sa 14.11. 10:00'],
            buttonSuffix: 'search/7',
            freeText: $name.' sucht Mitspieler',
        );
    }
}
