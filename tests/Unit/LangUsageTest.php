<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every translation key the code uses exists in German AND English, the German texts keep
 * their umlauts, and the English texts are real translations.
 *
 * Literal keys are collected from app/, resources/views and routes/:
 * - arguments of __(), trans(), trans_choice(), Lang::get/has/choice(), @lang and @choice,
 * - any 'namespace::file.key' string of a registered module namespace (keys handed to a
 *   helper and translated later), unless it names a view,
 * - 'booking.{group}.…' strings whose group is a top-level key of the booking translations
 *   (navigation entries and similar), unless it is a config key.
 * A literal ending in '.', '_' or '-' is a prefix completed at runtime: its group must exist
 * and hold keys starting with the rest.
 */
class LangUsageTest extends TestCase
{
    private const LOCALES = ['de', 'en'];

    /**
     * Keys whose English text must be a real translation (club review list): not empty and
     * not a copy of the German. A key ending in '.*' covers every text below it.
     */
    private const MUST_BE_TRANSLATED = [
        'mixed::messages.invalid_round_config',
        'mixed::messages.swap_mexicano',
        'mixed::messages.swap_same',
        'mixed::messages.swap_sitter_failed',
        'mixed::messages.round_state',
        'mixed::admin.round.swap_hint',
        'mixed::admin.round.swap_filler_hint',
        'mixed::admin.new_round.size_hint',
        'mixed::admin.new_round.distribution_hint',
        'mixed::messages.*',
        'pyramid::check.*',
        'pyramid::ui.not_found.*',
        'tournament-base::score.*',
        'tournament-base::mail.*',
        'booking.admin.nav.group_users',
        'booking.admin.users.accounts',
        'booking.admin.cfg.section_tournament_mail',
    ];

    /**
     * Typical ASCII transliterations of umlauts and ß in German texts.
     */
    private const TRANSLITERATIONS = '/\b(?:fuer|ueber|Ueber|koenn|moecht|waehl|Waehl|zurueck|Zurueck|loesch|Loesch|aender|Aender|pruef|Pruef|gruess|Gruess|groess|Groess|muess|moeglich|Moeglich|naechst|Naechst|spaeter|hinzufueg|Hinzufueg|Uebersicht|uebernehm|schliess|Schliess|gueltig|ungueltig|oeffn|Oeffn|faellig|Faellig|Gebuehr|bestaetig|Bestaetig|Eintraeg|eintraeg|Plaetze|plaetze|Maerz|waehrend|Spieltaeg|Gaeste|Gruesse|Strasse)/u';

    /**
     * @return array<string, string> namespace => path, plus '' for the app tree
     */
    private function trees(): array
    {
        /** @var array<string, string> $namespaces */
        $namespaces = app('translator')->getLoader()->namespaces();

        return ['' => lang_path()] + $namespaces;
    }

    /**
     * @return array<string, list<string>> key => files using it
     */
    private function usedKeys(): array
    {
        $namespaces = array_keys(app('translator')->getLoader()->namespaces());
        $this->assertContains('pyramid', $namespaces);
        $this->assertContains('mixed', $namespaces);
        $this->assertContains('tournament-base', $namespaces);

        $bookingGroups = array_keys(array_filter((array) __('booking', [], 'de'), 'is_array'));
        $call = '/(?:\b__|\btrans_choice|\btrans|Lang::get|Lang::has|Lang::choice|@lang|@choice)\(\s*([\'"])([^\'"$\{\}\s]+)\1\s*[,)]/';
        $namespaced = '/([\'"])((?:'.implode('|', array_map(static fn (string $ns): string => preg_quote($ns, '/'), $namespaces)).')::[A-Za-z0-9_.-]+)\1/';
        $booking = '/([\'"])(booking\.(?:'.implode('|', array_map(static fn (string $g): string => preg_quote($g, '/'), $bookingGroups)).')\.[A-Za-z0-9_.-]+)\1/';

        $keys = [];
        foreach ([app_path(), resource_path('views'), base_path('routes')] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', base_path())) + 1);

                foreach ([$call, $namespaced, $booking] as $index => $pattern) {
                    preg_match_all($pattern, $source, $matches);
                    foreach ($matches[2] as $key) {
                        if ($index === 1 && view()->exists($key)) {
                            continue;
                        }
                        if ($index === 2 && config()->has($key)) {
                            continue;
                        }
                        $keys[$key][] = $relative;
                    }
                }
            }
        }

        return array_map(static fn (array $files): array => array_values(array_unique($files)), $keys);
    }

    #[Test]
    public function every_used_key_exists_in_german_and_english(): void
    {
        $keys = $this->usedKeys();
        $this->assertGreaterThan(500, count($keys), 'key scan found too little');

        $missing = [];
        foreach ($keys as $key => $files) {
            foreach (self::LOCALES as $locale) {
                if (preg_match('/^(.+)\.([A-Za-z0-9_-]*[_-])?$/', $key, $prefix) === 1) {
                    // 'group.' or 'group.status_' completed at runtime: the group must hold such keys.
                    $group = Lang::get($prefix[1], [], $locale, false);
                    $start = $prefix[2] ?? '';
                    $exists = is_array($group) && array_filter(array_keys($group), static fn ($k): bool => str_starts_with((string) $k, $start)) !== [];
                } else {
                    $exists = Lang::has($key, $locale, false);
                }
                if (! $exists) {
                    $missing[] = "{$locale}: {$key} (".implode(', ', $files).')';
                }
            }
        }

        $this->assertSame([], $missing);
    }

    #[Test]
    public function the_key_scan_sees_calls_helper_keys_and_prefixes(): void
    {
        $keys = $this->usedKeys();

        $this->assertArrayHasKey('pyramid::mail.prefix', $keys);
        $this->assertArrayHasKey('tournament-base::mail.signature', $keys);
        $this->assertArrayHasKey('booking.admin.nav.group_users', $keys);
        $this->assertArrayHasKey('mixed::common.status.', $keys);
        $this->assertArrayNotHasKey('pyramid::mail.notification', $keys, 'view names are not keys');
    }

    #[Test]
    public function german_texts_keep_their_umlauts(): void
    {
        $found = [];
        foreach ($this->strings('de') as $id => $text) {
            if (preg_match(self::TRANSLITERATIONS, $text, $match) === 1) {
                $found[] = "{$id}: {$match[0]}";
            }
        }

        $this->assertSame([], $found);
    }

    #[Test]
    public function english_texts_contain_no_german_letters(): void
    {
        $found = [];
        foreach ($this->strings('en') as $id => $text) {
            if (preg_match('/[äöüÄÖÜß]/u', $text) === 1) {
                $found[] = "{$id}: {$text}";
            }
        }

        $this->assertSame([], $found);
    }

    #[Test]
    public function reviewed_keys_have_a_real_english_translation(): void
    {
        $problems = [];
        foreach (self::MUST_BE_TRANSLATED as $pattern) {
            $base = str_ends_with($pattern, '.*') ? substr($pattern, 0, -2) : $pattern;
            $de = Lang::get($base, [], 'de', false);
            $en = Lang::get($base, [], 'en', false);
            $deLines = is_array($de) ? Arr::dot($de) : [$base => $de];
            $enLines = is_array($en) ? Arr::dot($en) : [$base => $en];

            if ($de === $base || $deLines === []) {
                $problems[] = "{$pattern}: missing in de";

                continue;
            }
            foreach ($deLines as $key => $deLine) {
                $enLine = $enLines[$key] ?? null;
                if (! is_string($enLine) || trim($enLine) === '' || $enLine === $base) {
                    $problems[] = "{$pattern} {$key}: no English text";
                } elseif ($enLine === $deLine) {
                    $problems[] = "{$pattern} {$key}: English is a copy of the German";
                }
            }
        }

        $this->assertSame([], $problems);
    }

    /**
     * All string translations of one language, keyed "namespace::file.key".
     *
     * @return array<string, string>
     */
    private function strings(string $locale): array
    {
        $strings = [];
        foreach ($this->trees() as $namespace => $path) {
            $dir = $path.'/'.$locale;
            if (! is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $dir)) + 1);
                $values = require $file->getPathname();
                if (! is_array($values)) {
                    continue;
                }
                foreach (Arr::dot($values) as $key => $value) {
                    if (is_string($value)) {
                        $strings[($namespace === '' ? '' : $namespace.'::').$relative.':'.$key] = $value;
                    }
                }
            }
        }

        return $strings;
    }
}
