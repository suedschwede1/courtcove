<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserLastBookingColumnTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function book(User $user, string $date, string $status = 'single'): void
    {
        $booking = Booking::factory()->create(['uid' => $user->uid, 'status' => $status]);
        Reservation::factory()->create(['bid' => $booking->bid, 'date' => $date]);
    }

    private function cell(string $html, string $alias): string
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $nodes = (new DOMXPath($dom))->query('//tr[.//td[contains(@class,"ds-cell--title")][contains(.,"'.$alias.'")]]//td[@data-label="'.__('booking.admin.users.last_booking').'"]');
        $this->assertNotFalse($nodes);
        $this->assertSame(1, $nodes->length, "last booking cell for {$alias}");

        return trim((string) $nodes->item(0)?->textContent);
    }

    #[Test]
    public function the_list_shows_the_newest_non_cancelled_booking_date(): void
    {
        $admin = User::factory()->create(['status' => 'admin', 'alias' => 'Zed Admin']);
        $pat = User::factory()->create(['alias' => 'Pat Example']);
        $this->book($pat, '2026-03-01');
        $this->book($pat, '2026-09-14', 'subscription');
        $this->book($pat, '2026-12-24', 'cancelled');
        $sam = User::factory()->create(['alias' => 'Sam Example']);
        $this->book($sam, '2026-05-02', 'cancelled');
        $future = User::factory()->create(['alias' => 'Fay Future']);
        $this->book($future, '2027-01-15');

        $html = $this->actingAs($admin)->get('/admin/users?q=')->assertOk()
            ->assertSee(__('booking.admin.users.last_booking'))
            ->getContent();

        $this->assertSame('14.09.2026', $this->cell($html, 'Pat Example'));
        $this->assertSame('–', $this->cell($html, 'Sam Example'));
        $this->assertSame('15.01.2027', $this->cell($html, 'Fay Future'));
        $this->assertSame('–', $this->cell($html, 'Zed Admin'));
    }

    #[Test]
    public function the_cell_carries_its_label_for_the_phone_card_view(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);

        // x-ui.table stacks rows into cards on phones and prints each cell's data-label in front of it.
        $this->actingAs($admin)->get('/admin/users?q=')->assertOk()
            ->assertSee('<th scope="col">'.__('booking.admin.users.last_booking').'</th>', false)
            ->assertSee('<td data-label="'.__('booking.admin.users.last_booking').'">–</td>', false);
    }

    #[Test]
    public function english_sessions_get_the_english_date_format(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $this->book($admin, '2026-09-14');
        $admin->setMeta('locale', 'en');

        $html = $this->actingAs($admin)->get('/admin/users?q=')->assertOk()->getContent();

        $this->assertStringContainsString('Sep 14, 2026', $html);
    }

    #[Test]
    public function the_query_count_does_not_grow_with_the_number_of_users(): void
    {
        $admin = User::factory()->create(['status' => 'admin']);
        $count = function () use ($admin): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get('/admin/users?q=')->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $u = User::factory()->create();
        $this->book($u, '2026-04-01');
        $few = $count();

        foreach (range(1, 6) as $i) {
            $this->book(User::factory()->create(), '2026-04-0'.$i);
        }
        $many = $count();

        $this->assertSame($few, $many);
    }
}
