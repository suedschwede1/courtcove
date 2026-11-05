<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off move from the merged storage (role folded into bs_users.status) to the
 * split one: status 'admin' / 'assist' becomes role 'admin' / 'assist' with status
 * 'enabled'. Every other row keeps its status and role (default 'member'), so a
 * second run changes nothing. Refuses unless config users.split_role is on,
 * because the old system and the merged mode still read the role from status.
 */
class SplitUserRoleCommand extends Command
{
    protected $signature = 'users:split-role
        {--dry-run : Only report the counts, write nothing}';

    protected $description = 'Move admin/assist from bs_users.status into bs_users.role (needs USERS_SPLIT_ROLE=true)';

    private const ROLE_STATUSES = ['admin', 'assist'];

    public function handle(): int
    {
        if (! (bool) config('users.split_role', false)) {
            $this->error('Refused: the split role storage is off. Set USERS_SPLIT_ROLE=true (config users.split_role) first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->line('Rows to split (status → role):');
        $pending = 0;
        foreach (self::ROLE_STATUSES as $role) {
            $count = DB::table('bs_users')->where('status', $role)->count();
            $pending += $count;
            $this->line("  {$role}: {$count}");
        }

        if ($dryRun) {
            $this->info("Dry run: would update {$pending} row(s), nothing written.");
            $this->reportRoles();

            return self::SUCCESS;
        }

        $updated = DB::transaction(function (): int {
            $updated = 0;
            foreach (self::ROLE_STATUSES as $role) {
                $updated += DB::table('bs_users')->where('status', $role)->update(['role' => $role, 'status' => 'enabled']);
            }

            return $updated;
        });

        $this->info("Updated {$updated} row(s).");
        $this->reportRoles();

        return self::SUCCESS;
    }

    private function reportRoles(): void
    {
        $this->line('Current counts (status / role):');
        DB::table('bs_users')
            ->select('status', 'role', DB::raw('COUNT(*) AS total'))
            ->groupBy('status', 'role')
            ->orderBy('status')
            ->orderBy('role')
            ->get()
            ->each(fn (object $row) => $this->line("  {$row->status} / {$row->role}: {$row->total}"));
    }
}
