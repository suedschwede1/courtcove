<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Models\Option;
use Illuminate\Database\QueryException;

/**
 * Shared lookup for configurable mail headers: admin option (bs_options, set on the admin
 * "Texte" page) if set and non-empty > the given config keys in order (.env) > ''.
 */
trait ResolvesMailOptions
{
    /**
     * First non-empty value: the admin option, then the config keys in the given order.
     */
    private function value(string $option, string ...$configKeys): string
    {
        $value = $this->option($option);

        foreach ($configKeys as $key) {
            if ($value !== '') {
                break;
            }

            $config = config($key);
            $value = is_scalar($config) ? trim((string) $config) : '';
        }

        return $value;
    }

    /**
     * A missing options table (fresh install before migrating, bare unit tests) must not
     * stop a mail; the config and default values apply then.
     */
    private function option(string $key): string
    {
        try {
            $value = Option::getValue($key, '');
        } catch (QueryException) {
            return '';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
