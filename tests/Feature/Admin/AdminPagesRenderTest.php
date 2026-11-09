<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Event;
use App\Models\LoginHistory;
use App\Models\Reservation;
use App\Models\Square;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
            'booking_create' => [function (): string {
                $square = Square::factory()->create(['name' => 'Court A']);

                return route('admin.bookings.create', ['sid' => $square->sid, 'date' => '2026-07-13', 'time_start' => '10:00', 'time_end' => '11:00']);
            }],
            'booking_edit' => [function (): string {
                $square = Square::factory()->create(['name' => 'Court A']);
                $booking = Booking::factory()->create(['sid' => $square->sid, 'status' => 'subscription', 'quantity' => 4]);
                foreach (['2026-07-06', '2026-07-13', '2026-07-20'] as $date) {
                    Reservation::factory()->create(['bid' => $booking->bid, 'date' => $date, 'time_start' => '10:00:00', 'time_end' => '11:00:00']);
                }

                return route('admin.bookings.edit', [$booking, 'occurrence' => '2026-07-13']);
            }],
            'login_history' => [function (): string {
                $member = User::factory()->create(['alias' => 'Pat Example']);
                LoginHistory::record((int) $member->uid, '203.0.113.5', 'AT', 'success');

                return route('admin.login-history.index');
            }],
            'user_login_history' => [function (): string {
                $member = User::factory()->create(['alias' => 'Pat Example']);
                LoginHistory::record((int) $member->uid, '203.0.113.5', 'AT', 'failed');

                return route('admin.users.login-history', $member);
            }],
            'api_tokens' => [function (): string {
                $member = User::factory()->create(['alias' => 'Pat Example']);
                $member->createToken('Example phone');

                return route('admin.api-tokens.index');
            }],
            'security' => [fn (): string => route('admin.security.edit')],
            'testmail' => [fn (): string => route('admin.testmail.index')],
            'booking_show' => [function (): string {
                $square = Square::factory()->create(['name' => 'Court A']);
                $booking = Booking::factory()->create(['sid' => $square->sid, 'status' => 'single']);
                Reservation::factory()->create(['bid' => $booking->bid, 'date' => '2026-07-13', 'time_start' => '10:00:00', 'time_end' => '11:00:00']);

                return route('admin.bookings.show', $booking);
            }],
            'bookings_index' => [function (): string {
                $square = Square::factory()->create(['name' => 'Court A']);
                $booking = Booking::factory()->create(['sid' => $square->sid, 'status' => 'single']);
                Reservation::factory()->create(['bid' => $booking->bid, 'date' => '2026-07-13', 'time_start' => '10:00:00', 'time_end' => '11:00:00']);

                return route('admin.bookings.index', ['searched' => 1]);
            }],
            'events_index' => [function (): string {
                Event::factory()->create();

                return route('admin.events.index', ['q' => '']);
            }],
            'squares_index' => [function (): string {
                Square::factory()->create(['name' => 'Court A']);

                return route('admin.squares.index');
            }],
            'events_create' => [fn (): string => route('admin.events.create', ['sid' => 1, 'date_start' => '2026-07-05', 'time_start' => '10:00', 'date_end' => '2026-07-05', 'time_end' => '12:00'])],
            'events_create_popup' => [fn (): string => route('admin.events.create', ['popup' => 1])],
            'events_edit' => [function (): string {
                $event = Event::factory()->create();

                return route('admin.events.edit', $event);
            }],
            'squares_create' => [fn (): string => route('admin.squares.create')],
            'squares_edit' => [function (): string {
                $square = Square::factory()->create(['name' => 'Court A']);

                return route('admin.squares.edit', $square);
            }],
            'booking_create_popup' => [function (): string {
                $square = Square::factory()->create(['name' => 'Court A']);

                return route('admin.bookings.create', ['popup' => 1, 'sid' => $square->sid, 'date' => '2026-07-13', 'time_start' => '10:00', 'time_end' => '11:00']);
            }],
            'config_edit' => [fn (): string => route('admin.config.edit')],
            'config_behavior' => [fn (): string => route('admin.config.behavior.edit')],
            'database' => [function (): string {
                Storage::fake('local');
                (new DatabaseBackupService)->create();
                DB::table('migrations')->where('migration', '2026_06_28_155630_create_jobs_table')->delete();
                Schema::table('bs_users', function (Blueprint $table): void {
                    $table->dropColumn('last_ip');
                });

                return route('admin.database.index');
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
