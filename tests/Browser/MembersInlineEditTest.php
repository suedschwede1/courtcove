<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Modules\Members\Models\FeeCategory;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Tests\Browser\Support\CreatesTestData;
use Tests\DuskTestCase;

final class MembersInlineEditTest extends DuskTestCase
{
    use CreatesTestData;
    use DatabaseMigrations;

    #[Test]
    public function phone_saves_on_enter(): void
    {
        $admin = $this->createAdminUser('dusk_members');
        $id = $this->createMember()->id;

        try {
            $this->browse(function (Browser $browser) use ($admin, $id): void {
                $browser->loginAs($admin)
                    ->visit('/members')
                    ->click('td[data-field="phone"]')
                    ->waitFor('td[data-field="phone"] input')
                    ->clear('td[data-field="phone"] input')
                    ->type('td[data-field="phone"] input', '0123 456')
                    ->keys('td[data-field="phone"] input', '{enter}')
                    ->waitUsing(5, 100, fn () => Member::find($id)?->phone === '0123 456')
                    ->assertSeeIn('td[data-field="phone"]', '0123 456');
            });
        } finally {
            $this->deleteTestUser('dusk_members');
        }
    }

    #[Test]
    public function an_empty_last_name_keeps_the_editor_open(): void
    {
        $admin = $this->createAdminUser('dusk_members');
        $member = $this->createMember();

        try {
            $this->browse(function (Browser $browser) use ($admin): void {
                $lastname = 'td[data-field="firstname"] input[aria-label="Nachname"]';
                $browser->loginAs($admin)
                    ->visit('/members')
                    ->click('td[data-field="firstname"]')
                    ->waitFor($lastname)
                    ->clear($lastname)
                    ->keys($lastname, '{enter}')
                    ->waitFor('td[data-field="firstname"] p.ds-field__error', 5)
                    ->assertVisible($lastname);
            });

            $this->assertSame('Muster', $member->fresh()?->lastname);
        } finally {
            $this->deleteTestUser('dusk_members');
        }
    }

    #[Test]
    public function status_can_be_changed_in_the_table(): void
    {
        $admin = $this->createAdminUser('dusk_members');
        $id = $this->createMember()->id;

        try {
            $this->browse(function (Browser $browser) use ($admin, $id): void {
                $browser->resize(1440, 900)
                    ->loginAs($admin)
                    ->visit('/members')
                    ->click('td[data-field="status"]')
                    ->waitFor('td[data-field="status"] select')
                    ->select('td[data-field="status"] select', 'ruhend')
                    ->waitUsing(5, 100, fn () => Member::find($id)?->status === 'ruhend');
            });
        } finally {
            $this->deleteTestUser('dusk_members');
        }
    }

    #[Test]
    public function phone_cards_open_the_member(): void
    {
        $admin = $this->createAdminUser('dusk_members');
        $id = $this->createMember()->id;

        try {
            $this->browse(function (Browser $browser) use ($admin, $id): void {
                $browser->resize(390, 844)
                    ->loginAs($admin)
                    ->visit('/members')
                    ->assertMissing('td[data-field="status"]')
                    ->click('a.mbr-member-card')
                    ->waitForLocation('/members/'.$id.'/edit');
            });
        } finally {
            $this->deleteTestUser('dusk_members');
        }
    }

    #[Test]
    public function due_status_saves_on_dues_page(): void
    {
        $admin = $this->createAdminUser('dusk_members');
        $due = $this->createDue($this->createMember());

        try {
            $this->browse(function (Browser $browser) use ($admin, $due): void {
                $browser->loginAs($admin)
                    ->visit('/members/dues?fiscal_year_id='.$due->fiscal_year_id)
                    ->click('td[data-field="status"]')
                    ->waitFor('td[data-field="status"] select')
                    ->select('td[data-field="status"] select', 'bezahlt')
                    ->waitUsing(5, 100, fn () => MemberDue::find($due->id)?->status === 'bezahlt');
            });
        } finally {
            $this->deleteTestUser('dusk_members');
        }
    }

    #[Test]
    public function due_teilbetrag_saves_on_edit_page(): void
    {
        $admin = $this->createAdminUser('dusk_members');
        $member = $this->createMember();
        $due = $this->createDue($member);

        try {
            $this->browse(function (Browser $browser) use ($admin, $member, $due): void {
                $browser->loginAs($admin)
                    ->visit('/members/'.$member->id.'/edit')
                    // Teilbetrag sits in the details row of the compact dues card.
                    ->click('td.mbr-due-toggle button')
                    ->waitFor('td[data-field="teilbetrag"]')
                    ->click('td[data-field="teilbetrag"]')
                    ->waitFor('td[data-field="teilbetrag"] input')
                    ->clear('td[data-field="teilbetrag"] input')
                    ->type('td[data-field="teilbetrag"] input', '50')
                    ->keys('td[data-field="teilbetrag"] input', '{enter}')
                    ->waitUsing(5, 100, fn () => (float) MemberDue::find($due->id)?->teilbetrag === 50.0)
                    ->waitFor('td[data-field="teilbetrag"] .mbr-partial');
            });
        } finally {
            $this->deleteTestUser('dusk_members');
        }
    }

    private function createMember(): Member
    {
        return Member::factory()->create([
            'firstname' => 'Anna',
            'lastname' => 'Muster',
            'status' => 'aktiv',
            'email' => 'anna.muster@example.com',
            'phone' => '0699 000',
        ]);
    }

    private function createDue(Member $member): MemberDue
    {
        $category = FeeCategory::create(['name' => 'Vollzahler']);
        $fiscalYear = FiscalYear::create(['name' => '2025/26', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);

        return MemberDue::create([
            'member_id' => $member->id,
            'fiscal_year_id' => $fiscalYear->id,
            'fee_category_id' => $category->id,
            'amount' => 120,
        ]);
    }
}
