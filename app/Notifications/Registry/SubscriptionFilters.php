<?php

declare(strict_types=1);

namespace App\Notifications\Registry;

use InvalidArgumentException;

/**
 * Subscription filters of a notification type, derived from the `filters` data of its definition: the
 * validation rules of the account card form, the normalised value stored in `notification_subscriptions.filters`
 * and the current values for the form. Three kinds exist:
 *
 * - `weekdays`: list of ISO weekdays 1-7, stored under the filter key.
 * - `time_range`: start window, stored as `from` and `to` (H:i, from before to; both or none).
 * - `choice`: subset of `options`, stored under the filter key; `labelKey` names the group, `optionsKey` a lang
 *   array of option => label.
 *
 * Form fields live under `filters[<type slug>][<stored key>]`. Keys of the stored filters that no filter of
 * the type owns (for instance a court filter set elsewhere) are kept on save.
 */
final class SubscriptionFilters
{
    private const KINDS = ['weekdays', 'time_range', 'choice'];

    /**
     * @param  array<string, array<string, mixed>>  $filters
     *
     * @throws InvalidArgumentException
     */
    public static function assertValid(string $type, array $filters): void
    {
        foreach ($filters as $name => $filter) {
            $kind = $filter['kind'] ?? null;
            $valid = match ($kind) {
                'weekdays' => true,
                'time_range' => is_string($filter['from'] ?? null) && is_string($filter['to'] ?? null),
                'choice' => is_array($filter['options'] ?? null) && $filter['options'] !== [],
                default => false,
            };
            if (! $valid || ! in_array($kind, self::KINDS, true)) {
                throw new InvalidArgumentException("Invalid filter [$name] of notification type [$type].");
            }
        }
    }

    /** Input prefix of a type's filter fields (dot notation, as in validation and error keys). */
    public static function prefix(NotificationTypeDefinition $type): string
    {
        return 'filters.'.$type->slug();
    }

    /**
     * Validation rules of a type's filter fields.
     *
     * @return array<string, list<string>>
     */
    public static function rules(NotificationTypeDefinition $type): array
    {
        $p = self::prefix($type);
        $rules = [];
        foreach ($type->filters as $name => $filter) {
            switch ($filter['kind']) {
                case 'weekdays':
                    $rules["$p.$name"] = ['nullable', 'array', 'max:7'];
                    $rules["$p.$name.*"] = ['integer', 'between:1,7'];
                    break;
                case 'time_range':
                    [$from, $to] = [(string) $filter['from'], (string) $filter['to']];
                    $rules["$p.$from"] = ['nullable', 'date_format:H:i', "required_with:$p.$to", "before:$p.$to"];
                    $rules["$p.$to"] = ['nullable', 'date_format:H:i', "required_with:$p.$from"];
                    break;
                case 'choice':
                    $options = array_values((array) $filter['options']);
                    $ints = array_filter($options, 'is_int') === $options;
                    $rules["$p.$name"] = ['nullable', 'array', 'max:'.count($options)];
                    $rules["$p.$name.*"] = [...($ints ? ['integer'] : ['string']), 'in:'.implode(',', $options)];
                    break;
            }
        }

        return $rules;
    }

    /**
     * Readable names of the fields for validation messages.
     *
     * @return array<string, string>
     */
    public static function attributes(NotificationTypeDefinition $type): array
    {
        $p = self::prefix($type);
        $names = [];
        foreach ($type->filters as $name => $filter) {
            switch ($filter['kind']) {
                case 'weekdays':
                    $names["$p.$name"] = (string) __($filter['labelKey'] ?? 'booking.notifications.account_card.weekdays_label');
                    $names["$p.$name.*"] = $names["$p.$name"];
                    break;
                case 'time_range':
                    $names["$p.".$filter['from']] = (string) __('booking.notifications.account_card.time_from');
                    $names["$p.".$filter['to']] = (string) __('booking.notifications.account_card.time_to');
                    break;
                case 'choice':
                    $names["$p.$name"] = (string) __((string) ($filter['labelKey'] ?? $type->labelKey));
                    $names["$p.$name.*"] = $names["$p.$name"];
                    break;
            }
        }

        return $names;
    }

    /**
     * Stored filters from validated input: lists sorted and unique, empty selections left out, a time window
     * only with both ends; keys the type does not own are kept from `$existing`. No filter at all = null.
     *
     * @param  array<string, mixed>  $input  validated fields of this type (without prefix)
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>|null
     */
    public static function normalise(NotificationTypeDefinition $type, array $input, array $existing): ?array
    {
        $filters = array_diff_key($existing, array_flip(self::ownedKeys($type)));
        foreach ($type->filters as $name => $filter) {
            if ($filter['kind'] === 'time_range') {
                [$from, $to] = [(string) $filter['from'], (string) $filter['to']];
                if (($input[$from] ?? null) !== null && ($input[$to] ?? null) !== null) {
                    $filters[$from] = (string) $input[$from];
                    $filters[$to] = (string) $input[$to];
                }

                continue;
            }

            $ints = $filter['kind'] === 'weekdays' || array_filter((array) $filter['options'], 'is_int') === array_values((array) $filter['options']);
            $values = array_map(fn ($v) => $ints ? (int) $v : (string) $v, (array) ($input[$name] ?? []));
            $values = array_values(array_unique($values));
            sort($values);
            if ($values !== []) {
                $filters[$name] = $values;
            }
        }

        return $filters === [] ? null : $filters;
    }

    /**
     * Current values for the form, keyed by stored key: lists for weekdays and choices, strings for times.
     *
     * @param  array<string, mixed>|null  $stored
     * @return array<string, list<int|string>|string>
     */
    public static function values(NotificationTypeDefinition $type, ?array $stored): array
    {
        $stored ??= [];
        $values = [];
        foreach ($type->filters as $name => $filter) {
            if ($filter['kind'] === 'time_range') {
                $values[(string) $filter['from']] = (string) ($stored[$filter['from']] ?? '');
                $values[(string) $filter['to']] = (string) ($stored[$filter['to']] ?? '');

                continue;
            }
            $values[$name] = array_values(array_map(
                fn ($v) => is_numeric($v) ? (int) $v : (string) $v,
                (array) ($stored[$name] ?? []),
            ));
        }

        return $values;
    }

    /** @return list<string> stored keys the filters of a type own */
    public static function ownedKeys(NotificationTypeDefinition $type): array
    {
        $keys = [];
        foreach ($type->filters as $name => $filter) {
            if ($filter['kind'] === 'time_range') {
                $keys[] = (string) $filter['from'];
                $keys[] = (string) $filter['to'];
            } else {
                $keys[] = (string) $name;
            }
        }

        return $keys;
    }
}
