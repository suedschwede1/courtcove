<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TournamentBase;

use App\Models\User;
use App\Modules\TournamentBase\Support\MemberSearch;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Club decision (deviation from ZF2): the picker only offers accounts that are real, usable members.
 */
class MemberSearchStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function placeholder_and_blocked_accounts_are_excluded_like_deleted_and_disabled_ones(): void
    {
        foreach (['enabled', 'assist', 'admin', 'deleted', 'disabled', 'placeholder', 'blocked'] as $status) {
            User::factory()->create(['alias' => 'Status '.$status, 'email' => $status.'@example.test', 'status' => $status]);
        }

        $found = array_column((new MemberSearch)->search('Status '), 'alias');

        $this->assertSame(['Status admin', 'Status assist', 'Status enabled'], $found);
    }

    #[Test]
    public function an_excluded_account_is_not_found_by_uid_or_email_either(): void
    {
        $blocked = User::factory()->create(['alias' => 'Gesperrt', 'email' => 'gesperrt@example.test', 'status' => 'blocked']);
        $placeholder = User::factory()->create(['alias' => 'Platzhalter', 'email' => 'platzhalter@example.test', 'status' => 'placeholder']);

        $search = new MemberSearch;

        foreach ([$blocked, $placeholder] as $user) {
            $this->assertSame([], $search->search((string) $user->uid));
            $this->assertSame([], $search->search((string) $user->email));
        }
    }
}
