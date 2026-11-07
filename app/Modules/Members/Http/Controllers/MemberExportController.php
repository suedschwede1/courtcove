<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\Member;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberExportController
{
    public function export(): StreamedResponse
    {
        $members = Member::query()->with('household.members', 'connectedTo')->orderBy('lastname')->orderBy('firstname')->get();

        return response()->streamDownload(function () use ($members) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            // Header literals follow the existing hard-coded German CSV header (consumed by the club's spreadsheets).
            fputcsv($handle, ['Vorname', 'Nachname', 'E-Mail', 'Eingetreten', 'Ausgetreten', 'Haushalt', 'Zahlende Person', 'Verbunden mit']);

            foreach ($members as $member) {
                $household = $member->household;
                fputcsv($handle, [
                    $member->firstname,
                    $member->lastname,
                    $member->email,
                    optional($member->joined_at)->format('Y-m-d'),
                    optional($member->left_at)->format('Y-m-d'),
                    $household?->label() ?? '',
                    $household !== null && $household->payer_member_id === $member->id ? 'ja' : '',
                    $member->connectedTo?->fullName() ?? '',
                ]);
            }

            fclose($handle);
        }, 'mitglieder.csv', ['Content-Type' => 'text/csv']);
    }
}
