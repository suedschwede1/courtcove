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
 *
 * `--override=<rule>=<value>` (repeatable; rule = option key or the part after "booking.", value in the
 * display unit of the admin form, e.g. short_booking_window=15 for 15 minutes) is the explicit way to decide
 * a rule the courts disagree on. It writes that value as the global option, and reports which stored court
 * values it supersedes (table) and on which courts the effective value changes. Everything is validated
 * before anything is written. An option that is already set is only replaced when `--force` is given too;
 * `--force` alone never touches anything. `--dry-run` reports without writing.
 */
class AdoptBookingRulesCommand extends Command
{
    protected $signature = 'booking:adopt-booking-rules
        {--dry-run : Only report what would be adopted, write nothing}
        {--override=* : Set a rule explicitly, e.g. --override=short_booking_window=15 (value in the display unit, repeatable)}
        {--force : Together with --override: also replace an option that is already set}';

    protected $description = 'Adopt per-court booking settings as global options when all courts agree (or override a rule explicitly)';

    public function handle(BookingRules $rules): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $squares = Square::query()->orderBy('sid')->get();

        $overrides = $this->parseOverrides();
        if ($overrides === null) {
            return self::FAILURE;
        }

        if ($squares->isEmpty()) {
            $this->info('There are no courts: nothing to adopt.');

            return self::SUCCESS;
        }

        $failed = false;
        foreach (BookingRules::rules() as $rule) {
            $failed = ! (isset($overrides[$rule->optionKey])
                ? $this->override($rule, $overrides[$rule->optionKey], $rules, $squares, $dryRun)
                : $this->adopt($rule, $rules, $squares, $dryRun)) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, int>|null option key => value in the option's unit; null after reporting an error
     */
    private function parseOverrides(): ?array
    {
        $parsed = [];
        $valid = true;

        foreach ((array) $this->option('override') as $spec) {
            $spec = (string) $spec;
            [$key, $raw] = array_pad(explode('=', $spec, 2), 2, '');
            $key = trim($key);
            $raw = trim(str_replace(',', '.', $raw));

            $rule = $this->findRule($key);
            if ($rule === null) {
                $known = implode(', ', array_map(fn (BookingRule $r) => substr($r->optionKey, strlen('booking.')), BookingRules::rules()));
                $this->error("Unknown rule key \"{$key}\" in --override={$spec}. Known keys: {$known}.");
                $valid = false;

                continue;
            }

            if (preg_match('/^\d+(\.\d+)?$/', $raw) !== 1) {
                $this->error("Bad value \"{$raw}\" in --override={$spec}: a number >= 0 in {$rule->displayUnit} is required.");
                $valid = false;

                continue;
            }

            $parsed[$rule->optionKey] = (int) round((float) $raw * max(1, $rule->displayDivisor));
        }

        return $valid ? $parsed : null;
    }

    private function findRule(string $key): ?BookingRule
    {
        foreach (BookingRules::rules() as $rule) {
            if ($key !== '' && ($rule->optionKey === $key || $rule->optionKey === 'booking.'.$key)) {
                return $rule;
            }
        }

        return null;
    }

    /** @param Collection<int, Square> $squares */
    private function override(BookingRule $rule, int $value, BookingRules $rules, $squares, bool $dryRun): bool
    {
        $existing = $rules->globalValue($rule->optionKey);
        $shown = "{$value} {$rule->unit} ({$rule->display($value)} {$rule->displayUnit})";
        $force = (bool) $this->option('force');

        if ($existing === $value) {
            $this->line("{$rule->optionKey} ({$rule->label}): already set to {$existing} ({$rule->display($existing)} {$rule->displayUnit}), matches the override, skipped.");

            return true;
        }

        if ($existing !== null && ! $force) {
            $this->error("{$rule->optionKey} ({$rule->label}): already set to {$existing} ({$rule->display($existing)} {$rule->displayUnit}); use --force to overwrite it with the override.");

            return false;
        }

        $this->info("{$rule->optionKey} ({$rule->label}): override {$shown}.");
        if ($existing !== null) {
            $this->warn(($dryRun ? 'would overwrite' : 'overwriting')." {$existing} {$rule->unit} ({$rule->display($existing)} {$rule->displayUnit}) with {$shown}.");
        }

        $stored = $squares->mapWithKeys(fn (Square $square) => [(int) $square->sid => (int) $rule->courtValue($square)]);
        $superseded = $squares->filter(fn (Square $square) => $stored[(int) $square->sid] !== $value);

        if ($superseded->isEmpty()) {
            $this->line('All courts already store this value: no stored court value is superseded.');
        } else {
            $this->line('Stored court values superseded by the override:');
            $this->table(['sid', 'Court', 'Stored', $rule->displayUnit], $superseded->map(fn (Square $square) => [
                (string) $square->sid,
                (string) $square->name,
                (string) $stored[(int) $square->sid],
                $rule->display($stored[(int) $square->sid]),
            ])->values()->all());
        }

        // The effective value today is the existing option, else the court's own value.
        $changing = $squares->filter(fn (Square $square) => ($existing ?? $stored[(int) $square->sid]) !== $value);
        if ($changing->isEmpty()) {
            $this->line('Effective behaviour changes on no court.');
        } else {
            $list = $changing->map(fn (Square $square) => "{$square->sid} ({$rule->display($existing ?? $stored[(int) $square->sid])} -> {$rule->display($value)} {$rule->displayUnit})")->implode(', ');
            $this->line("Effective behaviour changes on {$changing->count()} court(s): {$list}");
        }

        if ($dryRun) {
            $this->info("{$rule->optionKey}: would set {$shown}.");

            return true;
        }

        Option::query()->updateOrCreate(['key' => $rule->optionKey, 'locale' => null], ['value' => (string) $value]);
        $this->info("{$rule->optionKey}: set to {$shown}.");

        return true;
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
            $this->error("{$rule->optionKey} ({$rule->label}): the courts disagree, not adopted. Set it under Konfiguration > Verhalten, or decide explicitly with --override={$rule->optionKey}=<{$rule->displayUnit}>.");
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
