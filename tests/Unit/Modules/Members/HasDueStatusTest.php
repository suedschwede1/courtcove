<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Members;

use App\Modules\Members\Models\Concerns\HasDueStatus;
use App\Modules\Members\Models\MemberDue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The status rules of a due (paid, open, overdue, displayed status, outstanding rest) live in one
 * trait so member dues and household dues can never drift apart.
 */
class HasDueStatusTest extends TestCase
{
    #[Test]
    public function member_due_uses_the_shared_status_trait(): void
    {
        $this->assertContains(HasDueStatus::class, class_uses_recursive(MemberDue::class));
    }

    #[Test]
    public function display_status_turns_a_past_due_ausstehend_into_ueberfaellig(): void
    {
        $this->travelTo('2026-11-06');
        $due = new MemberDue(['status' => 'ausstehend', 'faellig_am' => '2026-03-01']);
        $this->assertSame('ueberfaellig', $due->displayStatus());
        $due->faellig_am = Carbon::parse('2026-12-01');
        $this->assertSame('ausstehend', $due->displayStatus());
    }

    #[Test]
    public function outstanding_amount_is_amount_minus_partial_amount_and_zero_when_paid(): void
    {
        $due = new MemberDue(['status' => 'ausstehend', 'amount' => '150.00', 'teilbetrag' => '50.00']);
        $due->setRelation('payments', new Collection);
        $this->assertSame('100.00', $due->outstandingAmount());

        $due->status = 'bezahlt';
        $this->assertSame('0.00', $due->outstandingAmount());
    }
}
