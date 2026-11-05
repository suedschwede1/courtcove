<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins every migrated admin page to the new shell: one h1, main#content, no legacy ui-*
 * classes, ds tables with labelled stacked cells, labelled controls and no inline styles.
 */
final class AdminPagesRenderTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Each closure creates the models it needs and returns the page URL.
     *
     * @return array<string, array{Closure(): string}>
     */
    public static function pages(): array
    {
        return [
            'dashboard' => [fn (): string => route('admin.dashboard')],
            'statistics' => [fn (): string => route('admin.statistics.index', ['searched' => 1])],
            'users_index' => [function (): string {
                User::factory()->create(['alias' => 'Pat Example', 'status' => 'enabled']);

                return route('admin.users.index', ['q' => 'Example']);
            }],
            'users_create' => [fn (): string => route('admin.users.create')],
            'users_edit' => [function (): string {
                $user = User::factory()->create(['alias' => 'Pat Example', 'status' => 'assist']);

                return route('admin.users.edit', $user);
            }],
            'booking_edit' => [function (): string {
                $square = Square::factory()->create(['name' => 'Court A']);
                $booking = Booking::factory()->create(['sid' => $square->sid, 'status' => 'subscription', 'quantity' => 4]);
                foreach (['2026-07-06', '2026-07-13', '2026-07-20'] as $date) {
                    Reservation::factory()->create(['bid' => $booking->bid, 'date' => $date, 'time_start' => '10:00:00', 'time_end' => '11:00:00']);
                }

                return route('admin.bookings.edit', [$booking, 'occurrence' => '2026-07-13']);
            }],
        ];
    }

    #[Test]
    #[DataProvider('pages')]
    public function page_renders_in_the_new_shell(Closure $url): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $target = $url();

        $response = $this->actingAs($admin)->get($target);
        $response->assertStatus(200);

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$response->getContent());
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $this->assertSame(1, $xpath->query('//h1')->length, 'exactly one h1');
        $this->assertSame(1, $xpath->query('//main[@id="content"]')->length, 'main#content exists');

        foreach ($xpath->query('//main//*[@class]') as $node) {
            $this->assertInstanceOf(DOMElement::class, $node);
            $this->assertDoesNotMatchRegularExpression('/(^|\s)ui-/', $node->getAttribute('class'), 'legacy ui-* class under main');
        }

        foreach ($xpath->query('//main//table') as $table) {
            $this->assertInstanceOf(DOMElement::class, $table);
            $this->assertMatchesRegularExpression('/(^|\s)ds-table(\s|$)/', $table->getAttribute('class'), 'table without ds-table');
        }

        foreach ($xpath->query('//main//table[contains(@class,"ds-table--stack")]//td') as $td) {
            $this->assertInstanceOf(DOMElement::class, $td);
            $this->assertNotSame('', trim($td->getAttribute('data-label')), 'stacked td without data-label');
        }

        foreach ($xpath->query('//input|//select|//textarea') as $control) {
            $this->assertInstanceOf(DOMElement::class, $control);
            if ($control->getAttribute('type') === 'hidden' || $control->getAttribute('id') === '') {
                continue;
            }
            $id = $control->getAttribute('id');
            $labelled = $xpath->query('//label[@for="'.$id.'"]')->length > 0
                || $xpath->query('ancestor::label', $control)->length > 0
                || $control->getAttribute('aria-label') !== '';
            $this->assertTrue($labelled, 'control #'.$id.' has no label');
        }

        $this->assertSame(0, $xpath->query('//main//*[@style]')->length, 'inline style under main');
    }
}
