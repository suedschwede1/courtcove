<?php

declare(strict_types=1);

namespace App\Modules\Members\Console;

use App\Modules\Members\Models\FeeCategoryRate;
use App\Modules\Members\Models\FiscalYear;
use App\Modules\Members\Models\MemberChange;
use App\Modules\Members\Models\MemberDue;
use App\Modules\Members\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileDuesCommand extends Command
{
    protected $signature = 'members:reconcile-dues {--apply : Apply safe status/date repairs}
        {--repair-zero= : Comma-separated, explicitly reviewed unpaid placeholder due IDs}';

    protected $description = 'Audit contribution consistency; dry run unless --apply is supplied';

    public function handle(): int
    {
        $rawIds = (string) $this->option('repair-zero');
        if ($rawIds !== '' && ! preg_match('/^\d+(,\d+)*$/', $rawIds)) {
            $this->error('Use comma-separated numeric due IDs for --repair-zero.');

            return self::FAILURE;
        }
        $ids = $rawIds === '' ? [] : array_unique(array_map(intval(...), explode(',', $rawIds)));
        if (count($ids) !== MemberDue::whereKey($ids)->count()) {
            $this->error('At least one selected due does not exist; no changes applied.');

            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Applying reviewed repairs.' : 'Dry run: no data will be changed.');
        MemberDue::with('fiscalYear')->orderBy('id')->chunkById(100, function ($dues) use ($apply, $ids): void {
            foreach ($dues as $candidate) {
                DB::transaction(function () use ($candidate, $apply, $ids): void {
                    if ($apply && FiscalYear::lockForWrite($candidate->fiscal_year_id) !== null) {
                        $this->warn('Closed year: repair skipped for due #'.$candidate->id);

                        return;
                    }
                    $due = MemberDue::with('fiscalYear')->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                    $changes = [];
                    if ($due->payments()->exists() && $due->status !== 'bezahlt') {
                        $changes['status'] = 'bezahlt';
                    }
                    if ($due->faellig_am === null) {
                        $changes['faellig_am'] = ($due->fiscalYear->faellig_am ?? $due->fiscalYear->starts_on)->toDateString();
                    }
                    if ((float) $due->amount === 0.0) {
                        $this->warn('Due #'.$due->id.': zero amount; review source and tariff.');
                        if (in_array($due->id, $ids, true)) {
                            $rate = FeeCategoryRate::where('fee_category_id', $due->fee_category_id)
                                ->where('fiscal_year_id', $due->fiscal_year_id)->first();
                            if ($due->isPaid() || $rate === null) {
                                $this->warn('Due #'.$due->id.': amount repair refused (paid or no rate). Void an incorrect payment first.');
                            } else {
                                $changes['amount'] = $rate->amount;
                            }
                        }
                    }
                    if ($changes !== []) {
                        $this->line('Due #'.$due->id.': '.json_encode($changes));
                        if ($apply) {
                            $before = json_encode($due->only(['id', 'amount', 'status', 'faellig_am']));
                            $due->update($changes);
                            MemberChange::create(['member_id' => $due->member_id, 'action' => 'updated',
                                'before' => ['due' => $before], 'after' => ['due' => json_encode($due->fresh()->only(['id', 'amount', 'status', 'faellig_am']))]]);
                        }
                    }
                });
            }
        });

        Payment::whereNull('cancelled_at')->with('dues', 'householdDues')->chunkById(100, function ($payments): void {
            foreach ($payments as $payment) {
                $allocated = $payment->dues->concat($payment->householdDues)->sum(fn ($due): int => (int) round((float) $due->getAttribute('amount') * 100));
                if (($payment->dues->isEmpty() && $payment->householdDues->isEmpty()) || $allocated !== (int) round((float) $payment->amount * 100)) {
                    $this->warn('Payment #'.$payment->id.': amount/allocation mismatch; review the original posting before correction.');
                }
            }
        });

        return self::SUCCESS;
    }
}
