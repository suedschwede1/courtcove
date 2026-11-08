<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\Member;
use App\Modules\Members\Models\MemberChange;
use App\Modules\Members\Models\MemberHousehold;
use Illuminate\Support\Carbon;

/**
 * Human-readable lines for one member change ("Status: Aktiv → Ruhend", "Telefon geändert", "Angelegt").
 *
 * Personal fields only show their values when the caller reveals them (the detail page of a member
 * that is not anonymised); otherwise the line just says the field changed, so old personal data never
 * leaves the snapshot (GDPR).
 */
final class MemberChangeSummary
{
    /** Fields holding personal data. */
    public const PERSONAL = ['firstname', 'lastname', 'birthdate', 'gender', 'email', 'phone', 'street', 'zip', 'city', 'nuliga_id', 'notiz', 'user_id'];

    /** Snapshot keys that are bookkeeping, not member data. */
    private const IGNORED = ['anonymized_at'];

    private const DATES = ['birthdate', 'joined_at', 'left_at'];

    /** field => label key */
    private const LABELS = [
        'member_number' => 'members::admin.members.member_number',
        'nuliga_id' => 'members::admin.members.nuliga_id',
        'firstname' => 'members::admin.members.firstname',
        'lastname' => 'members::admin.members.lastname',
        'birthdate' => 'members::admin.members.birthdate',
        'gender' => 'members::admin.members.gender',
        'email' => 'members::admin.members.email',
        'phone' => 'members::admin.members.phone',
        'street' => 'members::admin.members.street',
        'zip' => 'members::admin.members.zip',
        'city' => 'members::admin.members.city',
        'fee_category_id' => 'members::admin.members.fee_category',
        'joined_at' => 'members::admin.members.joined_at',
        'left_at' => 'members::admin.members.left_at',
        'status' => 'members::admin.members.status',
        'notiz' => 'members::admin.members.notiz',
        'user_id' => 'members::admin.members.user_account',
        'household_id' => 'members::admin.members.household',
        'connected_to_member_id' => 'members::admin.connection.connected_to',
    ];

    /**
     * @param  array<int|string, string>  $categoryNames  fee category id => name, to show categories by name
     * @return list<string>
     */
    public static function lines(MemberChange $change, bool $revealPersonal, array $categoryNames = []): array
    {
        if ($change->action === 'created') {
            return [(string) __('members::admin.history.created')];
        }
        if ($change->action === 'anonymized') {
            return [(string) __('members::admin.history.anonymized')];
        }
        if ($change->action === 'household_payer') {
            return [self::payerLine($change, $revealPersonal)];
        }
        if ($change->action === 'connection') {
            return [self::connectionLine($change, $revealPersonal)];
        }
        if ($change->action === MemberConnectionService::ENDED_ACTION) {
            return [$revealPersonal
                ? (string) __('members::admin.connection.history_ended', ['name' => self::memberName($change->after['connection_ended_by'] ?? null)])
                : (string) __('members::admin.connection.history_changed')];
        }
        if ($change->action === MemberConnectionService::SWAP_ACTION) {
            if (! $revealPersonal) {
                return [(string) __('members::admin.connection.history_changed')];
            }
            $key = ($change->after['connected_to_member_id'] ?? null) === null ? 'history_swapped_vollzahler' : 'history_swapped_anschluss';

            return [(string) __('members::admin.connection.'.$key, ['name' => self::memberName($change->after['swapped_with'] ?? null)])];
        }
        if ($change->action === PaymentCanceller::CHANGE_ACTION) {
            return [self::paymentCancelledLine($change)];
        }

        $lines = [];
        foreach ($change->changedFields() as ['field' => $field, 'old' => $old, 'new' => $new]) {
            if (in_array($field, self::IGNORED, true)) {
                continue;
            }

            $label = isset(self::LABELS[$field]) ? (string) __(self::LABELS[$field]) : $field;

            // Household labels are built from last names: personal data like the name fields.
            if ((in_array($field, self::PERSONAL, true) || in_array($field, ['household_id', 'connected_to_member_id'], true)) && ! $revealPersonal) {
                $lines[] = (string) __('members::admin.history.changed', ['field' => $label]);

                continue;
            }

            $lines[] = (string) __('members::admin.history.change', [
                'field' => $label,
                'old' => self::format($field, $old, $categoryNames),
                'new' => self::format($field, $new, $categoryNames),
            ]);
        }

        return $lines === [] ? [(string) __('members::admin.history.no_changes')] : $lines;
    }

    /** "Zahlende Person für Haushalt Familie Muster" / "Nicht mehr zahlende Person …"; without the label when hidden. */
    private static function payerLine(MemberChange $change, bool $revealPersonal): string
    {
        if (! $revealPersonal) {
            return (string) __('members::admin.history.household_payer_changed');
        }

        $new = $change->after['household_payer'] ?? null;
        $old = $change->before['household_payer'] ?? null;

        return $new !== null
            ? (string) __('members::admin.history.household_payer_set', ['household' => self::householdLabel($new)])
            : (string) __('members::admin.history.household_payer_cleared', ['household' => self::householdLabel($old)]);
    }

    /** "Zahlung vom 01.03.2026 über 120,00 € storniert (Doppelt gebucht)". */
    private static function paymentCancelledLine(MemberChange $change): string
    {
        $entry = (array) ($change->after['payment_cancelled'] ?? []);
        $paidAt = isset($entry['paid_at']) ? Carbon::parse((string) $entry['paid_at'])->format('d.m.Y') : '–';
        $reason = isset($entry['reason']) && $entry['reason'] !== '' ? ' ('.$entry['reason'].')' : '';

        return (string) __('members::admin.history.payment_cancelled', [
            'date' => $paidAt,
            'amount' => MembersFormat::money($entry['amount'] ?? 0),
            'reason' => $reason,
        ]);
    }

    /** "Anschlussmitglied: Helga Beispiel" / "Kein Anschlussmitglied mehr: …" on the Vollzahler; without names when hidden. */
    private static function connectionLine(MemberChange $change, bool $revealPersonal): string
    {
        if (! $revealPersonal) {
            return (string) __('members::admin.connection.history_changed');
        }
        $new = $change->after['anschluss_member'] ?? null;
        $old = $change->before['anschluss_member'] ?? null;

        return $new !== null
            ? (string) __('members::admin.connection.history_set', ['name' => self::memberName($new)])
            : (string) __('members::admin.connection.history_cleared', ['name' => self::memberName($old)]);
    }

    private static function memberName(mixed $id): string
    {
        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return '—';
        }

        return Member::query()->find((int) $id)?->fullName() ?? '#'.$id;
    }

    private static function householdLabel(mixed $id): string
    {
        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return '—';
        }
        $id = (int) $id;

        return MemberHousehold::query()->with('members')->find($id)?->label()
            ?? (string) __('members::admin.households.unnamed', ['id' => $id]);
    }

    /**
     * @param  array<int|string, string>  $categoryNames
     */
    private static function format(string $field, mixed $value, array $categoryNames): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (in_array($field, self::DATES, true)) {
            return Carbon::parse((string) $value)->format('d.m.Y');
        }

        return match ($field) {
            'gender' => (string) __('members::admin.members.gender_'.$value),
            'status' => (string) __('members::admin.members.status_'.match ((string) $value) {
                'ausgetreten' => 'departed',
                'ruhend' => 'ruhend',
                default => 'active',
            }),
            'fee_category_id' => $categoryNames[$value] ?? (string) $value,
            'household_id' => self::householdLabel($value),
            'connected_to_member_id' => self::memberName($value),
            default => is_scalar($value) ? (string) $value : (string) json_encode($value),
        };
    }
}
