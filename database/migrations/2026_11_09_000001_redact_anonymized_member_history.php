<?php

declare(strict_types=1);

use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Member::whereNotNull('anonymized_at')->chunkById(100, function ($members): void {
            DB::transaction(function () use ($members): void {
                foreach ($members as $member) {
                    // Repair records written before the shared field lock existed.
                    DB::table('members')->where('id', $member->id)->update([
                        'firstname' => 'Ehemaliges',
                        'lastname' => 'Mitglied #'.$member->member_number,
                        'email' => null, 'phone' => null, 'street' => null,
                        'zip' => null, 'city' => null, 'nuliga_id' => null, 'user_id' => null,
                    ]);
                    MemberChange::where('member_id', $member->id)->each(function (MemberChange $change): void {
                        $change->save();
                    });
                }
            });
        });
    }

    public function down(): void
    {
        // Erased personal data cannot and must not be reconstructed on rollback.
    }
};
