<?php

declare(strict_types=1);

namespace App\Modules\Members\Console;

use App\Modules\Members\Models\MemberDue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills "Bezahlt am" (member_dues.bezahlt_am) of existing paid dues from their payment.
 *
 * Touches a due only when all of this holds: bezahlt_am is still empty, the status is
 * "bezahlt", exactly one payment is linked, and that payment is not a CSV-import placeholder
 * (the member import records "paid in year X" as a payment dated 1 January, which is not a
 * real payment date). Everything else is counted and left alone; running it again is a no-op.
 */
class BackfillPaidOnCommand extends Command
{
    /** Note prefix of the placeholder payments written by members:import. */
    private const IMPORT_NOTE_PREFIX = 'CSV-Import:';

    protected $signature = 'members:backfill-paid-at {--dry-run : Nur zählen, nichts schreiben}';

    protected $description = 'Trägt „Bezahlt am“ bei bezahlten Beiträgen ohne Datum aus der einen verknüpften Zahlung nach';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = MemberDue::query()
            ->whereNull('bezahlt_am')
            ->with('payments:id,paid_at,note')
            ->get(['id', 'status', 'bezahlt_am']);

        $fill = [];
        $skipped = ['no_payment' => 0, 'several' => 0, 'import' => 0, 'not_paid' => 0];

        foreach ($candidates as $due) {
            $payments = $due->payments;

            if ($due->status !== 'bezahlt') {
                // Only an open due with a payment is worth reporting; plain open dues are expected.
                if ($payments->isNotEmpty()) {
                    $skipped['not_paid']++;
                }

                continue;
            }

            if ($payments->isEmpty()) {
                $skipped['no_payment']++;

                continue;
            }

            if ($payments->count() > 1) {
                $skipped['several']++;

                continue;
            }

            $payment = $payments->first();
            if (str_starts_with((string) $payment->note, self::IMPORT_NOTE_PREFIX)) {
                $skipped['import']++;

                continue;
            }

            $fill[$due->id] = $payment->paid_at->toDateString();
        }

        if (! $dryRun) {
            DB::transaction(function () use ($fill): void {
                foreach ($fill as $dueId => $date) {
                    // whereNull again: never overwrite a date set in the meantime.
                    MemberDue::query()->whereKey($dueId)->whereNull('bezahlt_am')->update(['bezahlt_am' => $date]);
                }
            });
        }

        $count = count($fill);
        $this->info($dryRun
            ? "Probelauf: {$count} Beiträge würden „Bezahlt am“ aus ihrer Zahlung erhalten."
            : "{$count} Beiträge aktualisiert.");
        $this->line('Übersprungen:');
        $this->line("  bezahlt, ohne Zahlung: {$skipped['no_payment']}");
        $this->line("  bezahlt, mehrere Zahlungen: {$skipped['several']}");
        $this->line("  bezahlt, nur CSV-Import-Platzhalter: {$skipped['import']}");
        $this->line("  Zahlung vorhanden, aber nicht als bezahlt markiert: {$skipped['not_paid']}");

        return self::SUCCESS;
    }
}
