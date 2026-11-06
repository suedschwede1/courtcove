<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Option;
use App\Models\Square;
use App\Services\BookingRules;
use App\Support\BookingRules\BookingRule;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Turns per-court booking settings into global options (list: BookingRules::rules()).
 *
 * Per rule: an option that is already set is left alone (idempotent, never overwritten); otherwise the value
 * is adopted only when ALL courts store the same value. When they disagree the rule is skipped with a
 * per-court table (no silent choice) and the command exits with failure. Writes options only; the court
 * columns/meta stay untouched (ZF2, rollback, fallback).
 */
class AdoptBookingRulesCommand extends Command
{
    protected $signature = 'booking:adopt-booking-rules
        {--dry-run : Only report what would be adopted, write nothing}';

    protected $description = 'Adopt per-court booking settings as global options when all courts agree';

    public function handle(BookingRules $rules): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $squares = Square::query()->orderBy('sid')->get();

        if ($squares->isEmpty()) {
            $this->info('There are no courts: nothing to adopt.');

            return self::SUCCESS;
        }

        $failed = false;
        foreach (BookingRules::rules() as $rule) {
            $failed = ! $this->adopt($rule, $rules, $squares, $dryRun) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @param Collection<int, Square> $squares */
    private function adopt(BookingRule $rule, BookingRules $rules, $squares, bool $dryRun): bool
    {
        $existing = $rules->globalValue($rule->optionKey);
        if ($existing !== null) {
            $this->line("{$rule->optionKey} ({$rule->label}): already set to {$existing} ({$rule->display($existing)} {$rule->displayUnit}), skipped.");

            return true;
        }

        $values = $squares->mapWithKeys(fn (Square $square) => [(int) $square->sid => (int) $rule->courtValue($square)]);

        if ($values->unique()->count() !== 1) {
            $this->error("{$rule->optionKey} ({$rule->label}): the courts disagree, not adopted. Set it under Konfiguration > Verhalten.");
            $this->table(['sid', 'Court', 'Stored', $rule->displayUnit], $squares->map(fn (Square $square) => [
                (string) $square->sid,
                (string) $square->name,
                (string) $values[(int) $square->sid],
                $rule->display($values[(int) $square->sid]),
            ])->all());

            return false;
        }

        $value = (int) $values->first();
        $shown = "{$value} {$rule->unit} ({$rule->display($value)} {$rule->displayUnit})";

        if ($dryRun) {
            $this->info("{$rule->optionKey} ({$rule->label}): would adopt {$shown} from {$squares->count()} court(s).");

            return true;
        }

        Option::query()->updateOrCreate(['key' => $rule->optionKey, 'locale' => null], ['value' => (string) $value]);
        $this->info("{$rule->optionKey} ({$rule->label}): adopted {$shown} from {$squares->count()} court(s).");

        return true;
    }
}
