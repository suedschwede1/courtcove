<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use App\Modules\Members\Models\HouseholdSuggestionDismissal;
use App\Modules\Members\Models\Member;
use Illuminate\Support\Collection;

/**
 * "Mögliche Familien" (decision 8): groups of current members without household (aktiv or ruhend, not
 * anonymised) that share a normalised address (street + zip), e-mail or phone (last 9 digits). Groups are the
 * connected components of these links, size >= 2; a shared last name is only an additional reason, never a
 * link. Dismissed groups (by signature) are hidden. Read-only: never writes any table, never creates a household.
 */
final class HouseholdSuggestionFinder
{
    private const REASON_ORDER = ['address', 'email', 'phone', 'lastname'];

    /** @param  array<int, int|string>  $memberIds */
    public static function signature(array $memberIds): string
    {
        $ids = array_map(intval(...), $memberIds);
        sort($ids);

        return hash('sha256', implode(',', $ids));
    }

    /** @return Collection<int, HouseholdSuggestion> */
    public function find(): Collection
    {
        $members = Member::query()
            ->whereIn('status', ['aktiv', 'ruhend'])
            ->whereNull('anonymized_at')
            ->whereNull('household_id')
            ->orderBy('id')
            ->get(['id', 'lastname', 'street', 'zip', 'email', 'phone']);

        // key kind => normalised value => member ids
        $buckets = ['address' => [], 'email' => [], 'phone' => []];
        foreach ($members as $member) {
            foreach (['address' => self::address($member), 'email' => self::email($member->email), 'phone' => self::phone($member->phone)] as $kind => $key) {
                if ($key !== null) {
                    $buckets[$kind][$key][] = $member->id;
                }
            }
        }

        // Union-find over the strong links.
        $parent = [];
        $find = function (int $id) use (&$parent, &$find): int {
            $parent[$id] ??= $id;

            return $parent[$id] === $id ? $id : ($parent[$id] = $find($parent[$id]));
        };
        /** @var array<int, array<string, true>> $linkReasons member id => reasons it shares with someone */
        $linkReasons = [];
        foreach ($buckets as $kind => $groups) {
            foreach ($groups as $ids) {
                if (count($ids) < 2) {
                    continue;
                }
                foreach ($ids as $id) {
                    $linkReasons[$id][$kind] = true;
                    $parent[$find($id)] = $find($ids[0]);
                }
            }
        }

        $components = [];
        foreach (array_keys($linkReasons) as $id) {
            $components[$find($id)][] = $id;
        }

        $dismissed = HouseholdSuggestionDismissal::query()->pluck('signature')->flip();
        $lastNames = $members->pluck('lastname', 'id');

        return collect($components)
            ->filter(fn (array $ids): bool => count($ids) >= 2)
            ->map(function (array $ids) use ($linkReasons, $lastNames): HouseholdSuggestion {
                sort($ids);
                $reasons = [];
                foreach ($ids as $id) {
                    $reasons += $linkReasons[$id];
                }
                $names = collect($ids)->map(fn (int $id) => mb_strtolower(trim((string) $lastNames[$id])))->filter();
                if ($names->count() !== $names->unique()->count()) {
                    $reasons['lastname'] = true;
                }
                $ordered = array_values(array_filter(self::REASON_ORDER, fn (string $r): bool => isset($reasons[$r])));

                return new HouseholdSuggestion($ids, $ordered, self::signature($ids));
            })
            ->reject(fn (HouseholdSuggestion $s): bool => isset($dismissed[$s->signature]))
            ->sortBy([fn (HouseholdSuggestion $a, HouseholdSuggestion $b): int => count($b->reasons) <=> count($a->reasons) ?: $a->memberIds[0] <=> $b->memberIds[0]])
            ->values();
    }

    /** "musterweg 1|1000": lowercase, trimmed, whitespace collapsed, "str."/"strasse" → "straße"; null without street or zip. */
    private static function address(Member $member): ?string
    {
        $street = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $member->street) ?? ''));
        $zip = trim((string) $member->zip);
        if ($street === '' || $zip === '') {
            return null;
        }
        $street = preg_replace('/str(\.|asse)(?=\s|\d|$)/u', 'straße', $street) ?? $street;
        $street = preg_replace('/\s+(?=\d)/u', ' ', $street) ?? $street;

        return $street.'|'.$zip;
    }

    private static function email(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }

    /** The last 9 digits (national and international formats match); null with fewer than 9 digits. */
    private static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($digits) >= 9 ? substr($digits, -9) : null;
    }
}
