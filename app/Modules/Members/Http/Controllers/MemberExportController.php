<?php

declare(strict_types=1);

namespace App\Modules\Members\Http\Controllers;

use App\Modules\Members\Models\Member;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberExportController
{
    public function export(): StreamedResponse
    {
        $members = Member::query()->orderBy('lastname')->orderBy('firstname')->get();

        return response()->streamDownload(function () use ($members) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Vorname', 'Nachname', 'E-Mail', 'Eingetreten', 'Ausgetreten']);

            foreach ($members as $member) {
                fputcsv($handle, [
                    $member->firstname,
                    $member->lastname,
                    $member->email,
                    optional($member->joined_at)->format('Y-m-d'),
                    optional($member->left_at)->format('Y-m-d'),
                ]);
            }

            fclose($handle);
        }, 'mitglieder.csv', ['Content-Type' => 'text/csv']);
    }
}
