<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationConsent;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSubscription;
use App\Models\NotificationUsageMonthly;
use App\Models\Option;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppMessage;
use App\Notifications\Registry\NotificationPurposeDefinition;
use App\Notifications\Registry\NotificationRegistry;
use App\Notifications\Registry\NotificationTypeDefinition;
use App\Notifications\Types\NotificationTypes;
use App\Services\Notifications\ConsentRefused;
use App\Services\Notifications\ConsentService;
use App\Services\Notifications\MessageContent;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationStatistics;
use App\Services\Notifications\SubjectResolver;
use App\Services\Notifications\Transport\FakeTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\TestCase;

/**
 * Proof of extensibility: a new notification type with its own consent purpose is registered from the test
 * (definition + resolver + texts + template option only) and runs through consent, outbox channel,
 * dispatcher, budget, webhooks and revocation without any change to those classes.
 */
class DummyNotificationTypeTest extends TestCase
{
    use BuildsConsentFixtures;
    use RefreshDatabase;

    private const TYPE = 'dummy.reminder';

    private const FOLLOWUP = 'dummy.followup';

    private const PURPOSE = 'dummy_purpose';

    private const HEADER = 'X-Test-Signature';

    private const TOKEN = 'token-test';

    private const BASE = 'https://hooks.example.test';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'Europe/Vienna'));

        foreach (['de', 'en'] as $locale) {
            app('translator')->addLines([
                'notifications.dummy.label' => 'Dummy',
                'notifications.dummy.description' => 'Dummy type of the test',
                'notifications.dummy.purpose' => 'Dummy purpose',
                'notifications.dummy.purpose_description' => 'Dummy purpose of the test',
                'notifications.dummy.consent' => 'I agree to dummy messages.',
                'notifications.dummy.version' => 'dummy-v7',
            ], $locale, 'testing');
        }

        app(NotificationRegistry::class)->register(
            new NotificationTypeDefinition(
                key: self::TYPE,
                labelKey: 'testing::notifications.dummy.label',
                descriptionKey: 'testing::notifications.dummy.description',
                templateKey: 'dummy_reminder',
                resolver: DummyResolver::class,
                purpose: self::PURPOSE,
                priority: 25,
                optIn: true,
            ),
            new NotificationPurposeDefinition(
                key: self::PURPOSE,
                labelKey: 'testing::notifications.dummy.purpose',
                descriptionKey: 'testing::notifications.dummy.purpose_description',
                consentTextKey: 'testing::notifications.dummy.consent',
                consentVersionKey: 'testing::notifications.dummy.version',
                gateCondition: static fn (User $user): ?string => $user->getMeta('dummy-ok') === '1' ? null : 'dummy_off',
            ),
        );
        app(NotificationRegistry::class)->register(new NotificationTypeDefinition(
            key: self::FOLLOWUP,
            labelKey: 'testing::notifications.dummy.label',
            descriptionKey: 'testing::notifications.dummy.description',
            templateKey: 'dummy_followup',
            resolver: DummyResolver::class,
            purpose: self::PURPOSE,
            priority: 35,
            defaultEnabled: false,
            followupOf: self::TYPE,
        ));

        $this->opt('whatsapp.enabled', '1');
        $this->opt('whatsapp.driver', 'fake');
        $this->opt('whatsapp.budget_monthly', '1.00');
        $this->opt('whatsapp.price_estimate', '0.05');
        $this->opt('whatsapp.price.'.self::TYPE, '0.12');
        $this->opt('whatsapp.template.dummy_reminder.de', 'HXtest-dummy');
        $this->opt('whatsapp.template.dummy_followup.de', 'HXtest-dummy-followup');
        $this->opt('whatsapp.template.player_search_opened.de', 'HXtest-opened');
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

    /** Account with a mobile number and the dummy prerequisite, but without the player exchange. */
    private function dummyUser(string $phone = '+43 660 0000000'): User
    {
        $user = $this->eligibleUser($phone);
        $user->setMeta('player-exchange', null);
        $user->setMeta('dummy-ok', '1');

        return $user->fresh();
    }

    private function dummyOptIn(User $user): NotificationConsent
    {
        return app(ConsentService::class)->optIn($user, self::PURPOSE, 'de');
    }

    private function queue(User $user, string $type = self::TYPE): void
    {
        app(WhatsAppChannel::class)->send($user, new DummyNotification($type));
    }

    private function fake(): FakeTransport
    {
        return app(FakeTransport::class);
    }

    private function subscribed(User $user, string $type): bool
    {
        return NotificationSubscription::query()->where('user_id', $user->uid)->where('type', $type)->exists();
    }

    private function activeConsent(User $user, string $purpose): ?NotificationConsent
    {
        return app(ConsentService::class)->activeConsent($user, $purpose);
    }

    #[Test]
    public function opt_in_records_the_purpose_text_version_without_the_player_exchange(): void
    {
        $user = $this->dummyUser();

        $consent = $this->dummyOptIn($user);

        $this->assertSame(self::PURPOSE, $consent->purpose);
        $this->assertSame('dummy-v7', $consent->consent_text_version);
        $this->assertTrue($this->subscribed($user, self::TYPE));
        $this->assertFalse($this->subscribed($user, self::FOLLOWUP));
        $this->assertNull($this->activeConsent($user, NotificationConsent::PURPOSE_PLAYER_SEARCH));

        // The player search purpose still needs the exchange: its gate condition is its own.
        try {
            app(ConsentService::class)->optIn($user, NotificationConsent::PURPOSE_PLAYER_SEARCH, 'de');
            $this->fail('player search consent without the exchange');
        } catch (ConsentRefused $e) {
            $this->assertSame('exchange_off', $e->reason);
        }
        $this->assertNotNull($this->activeConsent($user, self::PURPOSE));
    }

    #[Test]
    public function purpose_gate_condition_refuses_its_own_reason(): void
    {
        $user = $this->dummyUser();
        $user->setMeta('dummy-ok', null);

        try {
            $this->dummyOptIn($user->fresh());
            $this->fail('dummy consent without its prerequisite');
        } catch (ConsentRefused $e) {
            $this->assertSame('dummy_off', $e->reason);
        }

        $gate = app(ConsentService::class)->gate($user->fresh(), false, self::PURPOSE);
        $this->assertSame('dummy_off', $gate->reason);
    }

    #[Test]
    public function dispatcher_sends_the_dummy_type_and_books_its_own_price(): void
    {
        $user = $this->dummyUser();
        $this->dummyOptIn($user);

        $this->queue($user);
        $row = NotificationLogEntry::sole();
        $this->assertSame(self::TYPE, $row->type);
        $this->assertSame(25, $row->priority);

        $report = app(NotificationDispatcher::class)->run();

        $row->refresh();
        $this->assertSame(NotificationLogEntry::SENT, $row->status, (string) $row->skip_reason);
        $this->assertSame(1, $report->sent);
        $this->assertSame(120_000, $row->cost_estimate_micros);
        $sent = $this->fake()->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('+436600000000', $sent[0]['to']);
        $this->assertSame('HXtest-dummy', $sent[0]['template']->contentId);
        $this->assertSame('dummy_reminder', $sent[0]['template']->key);
        $this->assertSame(['1' => 'dummy:'.$row->subject_id], $sent[0]['vars']);

        $usage = NotificationUsageMonthly::sole();
        $this->assertSame(1, $usage->messages_sent);
        $this->assertSame(120_000, $usage->estimated_micros);

        $stats = app(NotificationStatistics::class)->forType(self::TYPE);
        $this->assertSame(1, $stats['month']['sent']);
        $this->assertSame(1, $stats['month']['total']);
        $this->assertSame(1, $stats['last30']['total']);
    }

    #[Test]
    public function player_search_consent_alone_does_not_cover_the_dummy_purpose(): void
    {
        $user = $this->optedInUser();
        $user->setMeta('dummy-ok', '1');
        $this->queue($user->fresh());

        app(NotificationDispatcher::class)->run();

        $this->assertSame(NotificationLogEntry::NO_CONSENT, NotificationLogEntry::sole()->skip_reason);
        $this->assertSame([], $this->fake()->sent());
    }

    #[Test]
    public function disabled_type_is_not_queued_and_pending_rows_are_skipped(): void
    {
        $user = $this->dummyUser();
        $this->dummyOptIn($user);
        $this->queue($user);
        $this->opt('whatsapp.type.'.self::TYPE.'.enabled', '0');

        $this->queue($user);
        $this->assertSame(1, NotificationLogEntry::query()->count());

        $report = app(NotificationDispatcher::class)->run();

        $row = NotificationLogEntry::sole();
        $this->assertSame(NotificationLogEntry::SKIPPED, $row->status);
        $this->assertSame(NotificationLogEntry::TYPE_DISABLED, $row->skip_reason);
        $this->assertSame(1, $report->skipped[NotificationLogEntry::TYPE_DISABLED] ?? 0);
        $this->assertSame([], $this->fake()->sent());
        $this->assertSame(0, (int) NotificationUsageMonthly::query()->sum('estimated_micros'));
    }

    #[Test]
    public function followup_type_is_off_by_default_and_switched_on_by_its_option(): void
    {
        $user = $this->dummyUser();
        $this->dummyOptIn($user);

        $this->queue($user, self::FOLLOWUP);
        $this->assertSame(0, NotificationLogEntry::query()->count());

        $this->opt('whatsapp.type.'.self::FOLLOWUP.'.enabled', '1');
        $this->queue($user, self::FOLLOWUP);
        app(NotificationDispatcher::class)->run();

        $row = NotificationLogEntry::sole();
        $this->assertSame(NotificationLogEntry::SENT, $row->status);
        $this->assertSame(35, $row->priority);
        // No price of its own: the global estimate is booked.
        $this->assertSame(50_000, $row->cost_estimate_micros);
        $this->assertSame('HXtest-dummy-followup', $this->fake()->sent()[0]['template']->contentId);
    }

    #[Test]
    public function missing_template_skips_the_row(): void
    {
        $user = $this->dummyUser();
        $this->dummyOptIn($user);
        Option::where('key', 'whatsapp.template.dummy_reminder.de')->delete();
        $this->queue($user);

        app(NotificationDispatcher::class)->run();

        $row = NotificationLogEntry::sole();
        $this->assertSame(NotificationLogEntry::TEMPLATE_MISSING, $row->skip_reason);
        $this->assertNull($row->cost_estimate_micros);
        $this->assertSame(0, (int) NotificationUsageMonthly::query()->sum('estimated_micros'));
    }

    #[Test]
    public function unknown_type_in_a_row_is_skipped_as_subject_gone(): void
    {
        $user = $this->dummyUser();
        $this->dummyOptIn($user);
        NotificationLogEntry::create([
            'type' => 'gone.type', 'channel' => 'whatsapp', 'user_id' => $user->uid,
            'subject_type' => 'dummy', 'subject_id' => 1, 'dedupe_key' => 'gone:'.Str::uuid()->toString(),
            'status' => NotificationLogEntry::PENDING, 'priority' => 1, 'attempts' => 0, 'not_before' => Carbon::now(),
        ]);

        app(NotificationDispatcher::class)->run();

        $this->assertSame(NotificationLogEntry::SUBJECT_GONE, NotificationLogEntry::sole()->skip_reason);
    }

    #[Test]
    public function revoking_one_purpose_leaves_the_other_alone(): void
    {
        $user = $this->optedInUser();
        $user->setMeta('dummy-ok', '1');
        $user = $user->fresh();
        $this->dummyOptIn($user);
        $this->assertTrue($this->subscribed($user, NotificationTypes::PLAYER_SEARCH_OPENED));
        $this->assertTrue($this->subscribed($user, self::TYPE));

        app(ConsentService::class)->revoke($user, 'account', self::PURPOSE);

        $this->assertNull($this->activeConsent($user, self::PURPOSE));
        $this->assertFalse($this->subscribed($user, self::TYPE));
        $this->assertNotNull($this->activeConsent($user, NotificationConsent::PURPOSE_PLAYER_SEARCH));
        $this->assertTrue($this->subscribed($user, NotificationTypes::PLAYER_SEARCH_OPENED));

        $this->dummyOptIn($user);
        app(ConsentService::class)->revoke($user, 'account', NotificationConsent::PURPOSE_PLAYER_SEARCH);

        $this->assertNotNull($this->activeConsent($user, self::PURPOSE));
        $this->assertTrue($this->subscribed($user, self::TYPE));
        $this->assertFalse($this->subscribed($user, NotificationTypes::PLAYER_SEARCH_OPENED));
    }

    #[Test]
    public function stop_reply_revokes_every_purpose_of_the_number(): void
    {
        config([
            'whatsapp.signature_header' => self::HEADER,
            'whatsapp.auth_token' => self::TOKEN,
            'whatsapp.webhook_base_url' => self::BASE,
        ]);
        Cache::flush();
        $user = $this->optedInUser();
        $user->setMeta('dummy-ok', '1');
        $user = $user->fresh();
        $this->dummyOptIn($user);
        $other = $this->dummyUser('+43 660 0000001');
        $this->dummyOptIn($other);

        $this->inbound(['From' => 'whatsapp:+436600000000', 'Body' => 'STOP'])->assertOk();

        $this->assertSame(0, $this->activeConsentCount($user));
        $this->assertFalse($this->subscribed($user, self::TYPE));
        $this->assertFalse($this->subscribed($user, NotificationTypes::PLAYER_SEARCH_OPENED));
        $this->assertNotNull($this->activeConsent($other, self::PURPOSE));
    }

    /** @param  array<string, string>  $params */
    private function inbound(array $params): TestResponse
    {
        $path = '/webhooks/whatsapp/inbound';
        ksort($params);
        $data = self::BASE.$path;
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }
        $signature = base64_encode(hash_hmac('sha1', $data, self::TOKEN, true));
        $server = ['CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'HTTP_'.strtoupper(str_replace('-', '_', self::HEADER)) => $signature];

        return $this->call('POST', $path, $params, [], [], $server, http_build_query($params));
    }
}

/** Resolver of the dummy type: always relevant, one variable from the subject id. */
final class DummyResolver implements SubjectResolver
{
    public function stillRelevant(NotificationLogEntry $row): bool
    {
        return true;
    }

    public function build(NotificationLogEntry $row, User $user, string $locale): MessageContent
    {
        return new MessageContent(['1' => 'dummy:'.$row->subject_id], null, 'Dummy '.$row->subject_id);
    }
}

/** Notification of the dummy type for the outbox channel. */
final class DummyNotification extends Notification
{
    public function __construct(private readonly string $type) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [WhatsAppChannel::class];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $userId = $notifiable instanceof User ? (int) $notifiable->getKey() : 0;

        return new WhatsAppMessage(
            type: $this->type,
            variables: [],
            buttonSuffix: null,
            dedupeKey: $this->type.':'.$userId.':'.Str::uuid()->toString(),
            subjectType: 'dummy',
            subjectId: 42,
            notBefore: Carbon::now(),
        );
    }
}
