<?php

declare(strict_types=1);

namespace App\Modules\Members\Console;

use App\Modules\Members\Support\MemberDepartureAccounts;
use Illuminate\Console\Command;

/**
 * Blocks the booking accounts of departed members (see MemberDepartureAccounts). Scheduled daily with a window
 * of recent departures (--since=14); run without --since once for the existing departures after go-live, first
 * with --dry-run. Prints member ids and counts only, never names or addresses.
 */
class DeactivateLeftAccountsCommand extends Command
{
    protected $signature = 'members:deactivate-left-accounts
        {--dry-run : Nur anzeigen, welche Konten betroffen wären, nichts ändern}
        {--since= : Nur Austritte der letzten N Tage (der Zeitplan nutzt 14); ohne Angabe alle}';

    protected $description = 'Sperrt die Benutzerkonten ausgetretener Mitglieder (Austrittsdatum erreicht); Admin-/Assistenzkonten bleiben aktiv';

    public function handle(MemberDepartureAccounts $accounts): int
    {
        $since = $this->option('since');
        if ($since !== null && (! ctype_digit((string) $since) || (int) $since < 1)) {
            $this->error('--since erwartet eine ganze Zahl ab 1 (Tage).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $report = $accounts->run($dryRun, $since === null ? null : (int) $since);

        $this->line($dryRun ? 'Probelauf: nichts wurde geändert.' : 'Lauf abgeschlossen.');
        $this->report($dryRun ? 'Würden gesperrt' : 'Gesperrt', $dryRun ? $report['would'] : $report['deactivated']);
        $this->report('Geschützt (Admin-/Assistenzkonto, bleibt aktiv)', $report['protected']);
        if ($report['failed'] !== []) {
            $this->report('Fehlgeschlagen', $report['failed']);
        }

        return $report['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @param  list<int>  $memberIds */
    private function report(string $label, array $memberIds): void
    {
        $this->line($label.': '.count($memberIds).($memberIds === [] ? '' : ' (Mitglieds-IDs: '.implode(', ', $memberIds).')'));
    }
}
