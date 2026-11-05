<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\UserController;
use App\Models\Booking;
use App\Models\Reservation;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Admin user list: filter by the date of the last booking (von/bis, "Ohne Buchung"),
 * placeholders hidden by default, pagination keeping the filter, invalid input.
 */
final class UserListFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function flags(): array
    {
        return ['flag off' => [false], 'flag on' => [true]];
    }

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin', 'alias' => 'Zed Admin']);
    }

    /** A user with the given status/role in the storage the flag selects. */
    private function makeUser(string $alias, string $role = 'member', string $status = 'enabled'): User
    {
        $attributes = User::splitsRole()
            ? ['status' => $status, 'role' => $role]
            : ['status' => User::storedStatus($status, $role)];

        return User::factory()->create($attributes + ['alias' => $alias]);
    }

    private function book(User $user, string $date, string $status = 'single'): void
    {
        $booking = Booking::factory()->create(['uid' => $user->uid, 'status' => $status]);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => $date]);
    }

    /** @return list<string> Aliases of the listed rows, in list order. */
    private function names(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        // Title cell: initials span, then the alias span.
        $nodes = (new DOMXPath($dom))->query('//tbody/tr/td[contains(@class,"ds-cell--title")]/span/span[2]');
        $this->assertNotFalse($nodes);

        $aliases = [];
        foreach ($nodes as $node) {
            $aliases[] = trim((string) $node->textContent);
        }

        return $aliases;
    }

    /** Users with last bookings on several dates, plus some without any active booking. */
    private function seedBookings(): void
    {
        $this->book($this->makeUser('Ann March'), '2026-03-01');
        $this->book($this->makeUser('Ben June'), '2026-06-15');
        $this->book($this->makeUser('Cid September'), '2026-09-30');
        $fay = $this->makeUser('Fay August');
        $this->book($fay, '2026-06-20');
        $this->book($fay, '2026-08-01');
        $this->makeUser('Dan Never');
        $this->book($this->makeUser('Eve Cancelled'), '2026-06-15', 'cancelled');
    }

    #[Test]
    public function the_date_range_filters_by_the_last_booking_and_is_inclusive(): void
    {
        $admin = $this->admin();
        $this->seedBookings();

        $html = $this->actingAs($admin)->get('/admin/users?from=2026-06-15&to=2026-09-30')->assertOk()->getContent();
        // Fay's June booking is not her last one (August is), she counts by August.
        $this->assertSame(['Ben June', 'Cid September', 'Fay August'], $this->names($html));

        $html = $this->actingAs($admin)->get('/admin/users?from=2026-06-15&to=2026-06-15')->assertOk()->getContent();
        $this->assertSame(['Ben June'], $this->names($html));

        $html = $this->actingAs($admin)->get('/admin/users?to=2026-06-30')->assertOk()->getContent();
        $this->assertSame(['Ann March', 'Ben June'], $this->names($html));

        $html = $this->actingAs($admin)->get('/admin/users?from=2026-08-01')->assertOk()->getContent();
        $this->assertSame(['Cid September', 'Fay August'], $this->names($html));
    }

    #[Test]
    public function without_booking_lists_users_with_no_active_booking(): void
    {
        $admin = $this->admin();
        $this->seedBookings();

        $html = $this->actingAs($admin)->get('/admin/users?no_booking=1')->assertOk()->getContent();

        // Only cancelled bookings count as none (same rule as the "Letzte Buchung" column).
        $this->assertSame(['Dan Never', 'Eve Cancelled', 'Zed Admin'], $this->names($html));
    }

    #[Test]
    public function without_booking_wins_over_the_date_range(): void
    {
        $admin = $this->admin();
        $this->seedBookings();

        $response = $this->actingAs($admin)->get('/admin/users?no_booking=1&from=2026-06-01&to=2026-06-30')->assertOk();

        $this->assertSame(['Dan Never', 'Eve Cancelled', 'Zed Admin'], $this->names($response->getContent()));
        // The ignored dates are not echoed back into the (disabled) fields.
        $response->assertSee('name="from" id="users-from" value=""', false)
            ->assertSee('name="to" id="users-to" value=""', false)
            ->assertSee('x-bind:disabled="noBooking"', false)
            ->assertDontSee(__('booking.admin.users.filter_invalid'));
    }

    #[Test]
    #[DataProvider('flags')]
    public function the_date_filter_combines_with_status_permission_and_search(bool $flag): void
    {
        config(['users.split_role' => $flag]);
        $admin = $this->admin();
        $this->book($this->makeUser('Ada Admin', 'admin'), '2026-06-10');
        $this->book($this->makeUser('Abe Assist', 'assist'), '2026-06-11');
        $this->book($this->makeUser('Mia Member'), '2026-06-12');
        $this->book($this->makeUser('Bo Blocked', 'member', 'blocked'), '2026-06-13');
        $this->book($this->makeUser('Old Member'), '2025-01-01');
        $this->makeUser('Nina None');
        $this->makeUser('Nils Blocked', 'member', 'blocked');

        $range = 'from=2026-06-01&to=2026-06-30';
        $get = fn (string $query): array => $this->names($this->actingAs($admin)->get('/admin/users?'.$query)->assertOk()->getContent());

        $this->assertSame(['Abe Assist', 'Ada Admin', 'Bo Blocked', 'Mia Member'], $get($range));
        $this->assertSame(['Ada Admin'], $get($range.'&role=admin'));
        $this->assertSame(['Abe Assist'], $get($range.'&role=assist'));
        $this->assertSame(['Bo Blocked', 'Mia Member'], $get($range.'&role=member'));
        $this->assertSame(['Abe Assist', 'Ada Admin', 'Mia Member'], $get($range.'&status=enabled'));
        $this->assertSame(['Bo Blocked'], $get($range.'&status=blocked'));
        $this->assertSame(['Mia Member'], $get($range.'&status=enabled&role=member&q=Mia'));
        $this->assertSame(['Nils Blocked'], $get('no_booking=1&status=blocked'));
        $this->assertSame(['Nils Blocked', 'Nina None'], $get('no_booking=1&role=member'));
        $this->assertSame(['Zed Admin'], $get('no_booking=1&role=admin'));
    }

    #[Test]
    #[DataProvider('flags')]
    public function placeholders_are_hidden_unless_asked_for(bool $flag): void
    {
        config(['users.split_role' => $flag]);
        $admin = $this->admin();
        $this->makeUser('Pia Placeholder', 'member', 'placeholder');
        $this->makeUser('Pat Member');
        $this->makeUser('Del Deleted', 'member', 'deleted');
        $hidden = __('booking.admin.users.placeholders_hidden');

        $response = $this->actingAs($admin)->get('/admin/users?q=')->assertOk();
        $this->assertSame(['Pat Member', 'Zed Admin'], $this->names($response->getContent()));
        $response->assertSee($hidden)
            ->assertSee('href="'.e(route('admin.users.index', ['placeholders' => '1'])).'"', false)
            // The total on the list page does not count hidden placeholders either.
            ->assertSee(__('booking.admin.users.results_count', ['count' => 2]));

        // Same with a permission filter: placeholders are members by effective role.
        $response = $this->actingAs($admin)->get('/admin/users?role=member&no_booking=1')->assertOk();
        $this->assertSame(['Pat Member'], $this->names($response->getContent()));
        $response->assertSee('href="'.e(route('admin.users.index', ['role' => 'member', 'no_booking' => '1', 'placeholders' => '1'])).'"', false);

        $response = $this->actingAs($admin)->get('/admin/users?status=placeholder')->assertOk();
        $this->assertSame(['Pia Placeholder'], $this->names($response->getContent()));
        $response->assertDontSee($hidden);

        $response = $this->actingAs($admin)->get('/admin/users?placeholders=1')->assertOk();
        $this->assertSame(['Pat Member', 'Pia Placeholder', 'Zed Admin'], $this->names($response->getContent()));
        $response->assertDontSee($hidden)->assertSee(__('booking.admin.users.results_count', ['count' => 3]));

        // An explicit other status shows that status only; the switch does not add placeholders to it.
        $this->assertSame(['Pat Member', 'Zed Admin'], $this->names($this->actingAs($admin)->get('/admin/users?status=enabled&placeholders=1')->getContent()));
        $this->assertSame(['Del Deleted'], $this->names($this->actingAs($admin)->get('/admin/users?status=deleted')->getContent()));
    }

    #[Test]
    public function the_hint_is_not_shown_before_a_search(): void
    {
        $this->actingAs($this->admin())->get('/admin/users')->assertOk()
            ->assertSee(__('booking.admin.search_hint'))
            ->assertDontSee(__('booking.admin.users.placeholders_hidden'));
    }

    #[Test]
    public function the_pager_links_keep_every_filter(): void
    {
        $admin = $this->admin();
        foreach (range(1, UserController::PER_PAGE + 2) as $i) {
            $this->book($this->makeUser(sprintf('Pager %03d', $i)), '2026-05-'.sprintf('%02d', ($i % 28) + 1));
        }
        $this->book($this->makeUser('Pager Late'), '2026-07-01');

        $query = 'q=Pager&role=member&status=enabled&from=2026-05-01&to=2026-05-31&placeholders=1';
        $response = $this->actingAs($admin)->get('/admin/users?'.$query)->assertOk();
        $html = $response->getContent();

        $this->assertCount(UserController::PER_PAGE, $this->names($html));
        $response->assertSee(__('booking.admin.users.results_count', ['count' => UserController::PER_PAGE + 2]))
            ->assertSee(__('booking.admin.users.results_range', ['first' => 1, 'last' => UserController::PER_PAGE, 'total' => UserController::PER_PAGE + 2]));

        $this->assertSame(1, preg_match('/<a class="ds-pagination__item" href="([^"]+)" rel="next"/', $html, $match));
        $next = html_entity_decode($match[1]);
        parse_str((string) parse_url($next, PHP_URL_QUERY), $params);
        $this->assertSame([
            'q' => 'Pager', 'status' => 'enabled', 'role' => 'member', 'from' => '2026-05-01', 'to' => '2026-05-31', 'placeholders' => '1', 'page' => '2',
        ], $params);

        $page2 = $this->names($this->actingAs($admin)->get($next)->assertOk()->getContent());
        $this->assertSame(['Pager 051', 'Pager 052'], $page2);
    }

    #[Test]
    public function the_pager_links_keep_without_booking_and_drop_invalid_values(): void
    {
        $admin = $this->admin();
        foreach (range(1, UserController::PER_PAGE + 1) as $i) {
            $this->makeUser(sprintf('Idle %03d', $i));
        }

        $html = $this->actingAs($admin)->get('/admin/users?q=Idle&no_booking=1&from=2026-01-01&to=nonsense')->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<a class="ds-pagination__item" href="([^"]+)" rel="next"/', $html, $match));
        parse_str((string) parse_url(html_entity_decode($match[1]), PHP_URL_QUERY), $params);
        $this->assertSame(['q' => 'Idle', 'no_booking' => '1', 'page' => '2'], $params);
    }

    #[Test]
    public function a_page_costs_a_fixed_number_of_queries(): void
    {
        $admin = $this->admin();
        $count = function () use ($admin): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get('/admin/users?from=2026-01-01&to=2026-12-31&role=member')->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->book($this->makeUser('Few One'), '2026-04-01');
        $few = $count();

        foreach (range(1, UserController::PER_PAGE + 5) as $i) {
            $this->book($this->makeUser('Many '.$i), '2026-04-02');
        }
        $full = $count();

        $this->assertSame($few, $full);
        // A fixed handful (signed-in user and locale, page count, page rows, risk scores), nothing per row.
        $this->assertSame(5, $full);
    }

    /** @return array<string, array{string, string, string}> */
    public static function invalidInput(): array
    {
        $invalidDate = 'booking.admin.users.filter_date_invalid';

        return [
            'garbage from' => ['from=yesterday', 'from', $invalidDate],
            'impossible date' => ['to=2026-02-30', 'to', $invalidDate],
            'wrong format' => ['from=14.06.2026', 'from', $invalidDate],
            'array from' => ['from[]=2026-06-01', 'from', $invalidDate],
            'array to' => ['to[a]=2026-06-01', 'to', $invalidDate],
            'to before from' => ['from=2026-06-30&to=2026-06-01', 'to', 'booking.admin.users.filter_date_order'],
        ];
    }

    #[Test]
    #[DataProvider('invalidInput')]
    public function invalid_dates_show_an_error_and_fall_back_to_no_date_filter(string $query, string $field, string $key): void
    {
        $admin = $this->admin();
        $this->seedBookings();

        $response = $this->actingAs($admin)->get('/admin/users?'.$query)->assertOk();

        // Under the field and once more in the shell's error banner.
        $response->assertSee('<p class="ds-field__error" id="users-'.$field.'-error">'.e(__($key)).'</p>', false);
        $this->assertSame(2, substr_count((string) $response->getContent(), e(__($key))));
        // Fallback: the date range is dropped, every (non-placeholder) user is listed.
        $this->assertCount(7, $this->names($response->getContent()));
    }

    #[Test]
    public function well_formed_dates_in_the_wrong_order_stay_in_the_fields(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/users?from=2026-06-30&to=2026-06-01&q=')->assertOk();

        $response->assertSee('name="from" id="users-from" value="2026-06-30"', false)
            ->assertSee('name="to" id="users-to" value="2026-06-01"', false);
        // ...but the pager would not carry them: the effective filter has no date range.
        $this->assertStringNotContainsString('from=2026-06-30', (string) $response->getContent());
    }

    #[Test]
    public function array_shaped_values_of_the_other_filters_never_fail(): void
    {
        $admin = $this->admin();
        $this->makeUser('Pia Placeholder', 'member', 'placeholder');
        $this->makeUser('Pat Member');

        foreach (['q[]=Pat', 'status[]=enabled', 'role[x]=admin', 'no_booking[]=1', 'placeholders[]=1', 'q=&page[]=2'] as $query) {
            $response = $this->actingAs($admin)->get('/admin/users?'.$query)->assertOk();
            $this->assertSame(['Pat Member', 'Zed Admin'], $this->names($response->getContent()), $query);
        }
        $response = $this->actingAs($admin)->get('/admin/users?status=unknown&role=root&no_booking=yes&q=')->assertOk();
        // The shell's error banner shows the (identical) message once.
        $this->assertSame(1, substr_count((string) $response->getContent(), __('booking.admin.users.filter_invalid')));
        $this->assertSame(['Pat Member', 'Zed Admin'], $this->names($response->getContent()));
    }

    #[Test]
    public function english_sessions_get_english_labels_and_errors(): void
    {
        $admin = $this->admin();
        $admin->setMeta('locale', 'en');

        $this->actingAs($admin)->get('/admin/users?from=2026-06-30&to=2026-06-01')->assertOk()
            ->assertSee('Last booking from')
            ->assertSee('No booking')
            ->assertSee('Show placeholders')
            ->assertSee('Placeholders hidden')
            ->assertSee(e('"to" is before "from", the date range was ignored.'), false);
        $this->actingAs($admin)->get('/admin/users?from=nonsense&status=x')->assertOk()
            ->assertSee('Not a valid date, this filter was ignored.')
            ->assertSee('Invalid filter value, it was ignored.');
    }

    #[Test]
    public function german_sessions_get_german_labels(): void
    {
        $this->actingAs($this->admin())->get('/admin/users?to=nonsense')->assertOk()
            ->assertSee('Letzte Buchung von')
            ->assertSee('>bis<', false)
            ->assertSee('Ohne Buchung')
            ->assertSee('Platzhalter anzeigen')
            ->assertSee('Platzhalter ausgeblendet')
            ->assertSee('Kein gültiges Datum, dieser Filter wurde ignoriert.');
        $this->actingAs($this->admin())->get('/admin/users?from=2026-06-30&to=2026-06-01&role=x')->assertOk()
            ->assertSee(e('„bis“ liegt vor „von“, der Datumsbereich wurde ignoriert.'), false)
            ->assertSee('Ungültige Filterangabe, sie wurde ignoriert.');
    }
}
