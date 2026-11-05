<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Production runs under the /booking URL base path (public_html/booking -> public/).
 * Rendered action/fetch URLs must honour it.
 */
class BasePathUrlsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withServerVariables([
            'SCRIPT_NAME' => '/booking/index.php',
            'SCRIPT_FILENAME' => base_path('public/index.php'),
            'PHP_SELF' => '/booking/index.php',
        ]);
    }

    #[Test]
    public function calendar_modal_urls_honor_the_booking_base_path(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get('/booking/calendar')
            ->assertOk()
            ->getContent();

        // @js() escapes slashes (http:\/\/host\/booking\/...); decode so the URLs read naturally.
        $html = str_replace('\/', '/', $html);

        $this->assertStringNotContainsString("fetch('/bookings", $html);
        $this->assertStringNotContainsString("'/bookings/'", $html);

        preg_match_all('#x-bind:action="([^"]*)"#', $html, $actions);
        $bookingActions = array_values(array_filter($actions[1], static fn (string $a): bool => str_contains($a, 'bookings')));
        $this->assertNotEmpty($bookingActions, 'calendar modals should render booking form actions');

        foreach ($bookingActions as $action) {
            $this->assertMatchesRegularExpression('#^(https?://[^/]+)?/booking/bookings#', ltrim($action, "'\" "), $action);
        }

        $this->assertMatchesRegularExpression('#fetch\(\s*[\'"](https?://[^/]+)?/booking/bookings/players#', $html);
        $this->assertMatchesRegularExpression('#href="(https?://[^/]+)?/booking/my-bookings"#', $html);
    }
}
