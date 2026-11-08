<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\NotificationConsent;
use App\Models\NotificationSubscription;
use App\Models\Option;
use App\Models\User;
use App\Notifications\Registry\NotificationPurposeDefinition;
use App\Notifications\Registry\NotificationRegistry;
use App\Notifications\Registry\NotificationTypeDefinition;
use App\Notifications\Registry\PurposeGateCondition;
use App\Notifications\Types\NotificationTypes;
use App\Services\Notifications\ConsentService;
use App\Services\Notifications\Resolvers\AdminTestResolver;
use App\Services\Notifications\Transport\FakeTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Notifications\Concerns\BuildsConsentFixtures;
use Tests\TestCase;

/** Fail-closed rules around the registry: unknown purposes, broken gate conditions, retired purposes, admin test purpose. */
class RegistryHardeningTest extends TestCase
{
    use BuildsConsentFixtures;
    use RefreshDatabase;

    private function registry(): NotificationRegistry
    {
        return app(NotificationRegistry::class);
    }

    private function purpose(string $key, mixed $condition = null): NotificationPurposeDefinition
    {
        return new NotificationPurposeDefinition(
            key: $key,
            labelKey: 'booking.notifications.purposes.player_search.label',
            descriptionKey: 'booking.notifications.purposes.player_search.description',
            consentTextKey: 'booking.notifications.consent_text_v1',
            consentVersionKey: 'booking.notifications.consent_text_version',
            gateCondition: $condition,
        );
    }

    private function consentRow(User $user, string $purpose): NotificationConsent
    {
        return NotificationConsent::create([
            'user_id' => $user->uid,
            'channel' => 'whatsapp',
            'purpose' => $purpose,
            'phone_hash' => app(ConsentService::class)->phoneHash('+436600000000'),
            'phone_last2' => '00',
            'locale' => 'de',
            'consent_text_version' => 'test',
            'consented_at' => now(),
            'active_marker' => NotificationConsent::ACTIVE_MARKER,
        ]);
    }

    #[Test]
    public function gate_fails_closed_for_an_unregistered_purpose(): void
    {
        $user = $this->eligibleUser();

        $this->assertSame('unknown_purpose', app(ConsentService::class)->gate($user, false, 'retired_purpose')->reason);
        $this->assertSame('unknown_purpose', app(ConsentService::class)->gate($user, true, 'retired_purpose')->reason);
        $this->assertTrue(app(ConsentService::class)->gate($user, false, null)->passed);
    }

    #[Test]
    public function a_condition_class_that_is_no_gate_condition_is_refused_at_registration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->purpose('broken_purpose', \stdClass::class);
    }

    #[Test]
    public function a_condition_the_container_breaks_fails_closed(): void
    {
        app()->bind(HardeningCondition::class, fn () => new \stdClass);
        $this->registry()->registerPurpose($this->purpose('bound_purpose', HardeningCondition::class));
        $user = $this->eligibleUser();

        $this->assertSame('condition_invalid', app(ConsentService::class)->gate($user, false, 'bound_purpose')->reason);
    }

    #[Test]
    public function revoking_a_consent_of_a_retired_purpose_deletes_every_subscription_of_the_user(): void
    {
        $user = $this->optedInUser();
        $this->consentRow($user, 'retired_purpose')->forceFill(['phone_hash' => app(ConsentService::class)->phoneHash('+436600000099')])->save();
        NotificationSubscription::create(['user_id' => $user->uid, 'type' => 'retired.type', 'channel' => 'whatsapp']);

        app(ConsentService::class)->revoke($user, 'account', 'retired_purpose');

        $this->assertSame(0, NotificationSubscription::query()->where('user_id', $user->uid)->count());
    }

    #[Test]
    public function stop_on_a_retired_purpose_deletes_every_subscription_of_the_user(): void
    {
        $user = $this->eligibleUser();
        $this->consentRow($user, 'retired_purpose');
        NotificationSubscription::create(['user_id' => $user->uid, 'type' => 'retired.type', 'channel' => 'whatsapp']);
        NotificationSubscription::create(['user_id' => $user->uid, 'type' => NotificationTypes::PLAYER_SEARCH_OPENED, 'channel' => 'whatsapp']);

        $count = app(ConsentService::class)->revokeByPhoneHash(app(ConsentService::class)->phoneHash('+436600000000'), 'stop');

        $this->assertSame(1, $count);
        $this->assertSame(0, NotificationSubscription::query()->where('user_id', $user->uid)->count());
    }

    #[Test]
    public function the_admin_test_asks_for_the_consent_of_its_type_purpose(): void
    {
        foreach (['whatsapp.enabled' => '1', 'whatsapp.driver' => 'fake', 'whatsapp.budget_monthly' => '1.00', 'whatsapp.price_estimate' => '0.05', 'whatsapp.template.test.de' => 'HXtest-test'] as $key => $value) {
            Option::updateOrCreate(['key' => $key], ['value' => $value, 'locale' => null]);
        }
        $this->registry()->registerPurpose($this->purpose('admin_purpose'));
        $this->registry()->register(new NotificationTypeDefinition(
            key: NotificationTypes::ADMIN_TEST,
            labelKey: 'booking.notifications.types.test.label',
            descriptionKey: 'booking.notifications.types.test.description',
            templateKey: 'test',
            resolver: AdminTestResolver::class,
            purpose: 'admin_purpose',
            priority: 10,
            testType: true,
        ));
        $admin = User::factory()->create(['status' => 'admin']);
        $admin->setMeta('phone', '+43 660 0000000');
        $this->consentRow($admin, NotificationConsent::PURPOSE_PLAYER_SEARCH);

        $this->actingAsWithoutAdminMode($admin->fresh())->post('/admin/whatsapp/test')
            ->assertSessionHas('error', __('booking.admin.whatsapp.test_refused.no_consent'));
        $this->assertSame([], app(FakeTransport::class)->sent());

        $this->consentRow($admin, 'admin_purpose');
        $this->actingAsWithoutAdminMode($admin->fresh())->post('/admin/whatsapp/test')
            ->assertSessionHas('success', __('booking.admin.whatsapp.test_sent'));
        $this->assertCount(1, app(FakeTransport::class)->sent());
    }
}

/** A real gate condition class (the container is rebound in the test to hand back something else). */
final class HardeningCondition implements PurposeGateCondition
{
    public function failure(User $user): ?string
    {
        return null;
    }
}
