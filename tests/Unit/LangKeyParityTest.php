<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * German and English translations must match, so a missing key never silently falls back
 * (to German, or to the raw key) and a placeholder never stays unreplaced in one language.
 *
 * Covers the app's own tree (lang/de, lang/en) and every module tree found automatically
 * under app/Modules/{Module}/resources/lang, so a new module is checked without touching
 * this test. Per tree:
 * - every file exists in both languages (both directions),
 * - every key exists in both languages (both directions),
 * - every string has the same placeholders (:name) in both languages,
 * - a string is not empty in one language and filled in the other.
 */
class LangKeyParityTest extends TestCase
{
    /**
     * Not tracked in git (per installation); see the matching *.example.php files.
     */
    private const UNTRACKED = [
        'booking/local.php',
        'booking/quote_groups.php',
        'booking/quotes.local.php',
        'booking/quotes_named.local.php',
    ];

    /**
     * Files of the app tree that intentionally exist in one language only, with the reason.
     */
    private const ONE_LANGUAGE_ONLY = [
        // Quote groups are German only (App\Services\QuoteGroups reads lang/de only).
        'de/booking/quote_groups.example.php',
        // English validation messages come from the framework; checked against them below.
        'de/validation.php',
    ];

    /**
     * Strings that are deliberately empty in one language ("{file}::{key}").
     */
    private const EMPTY_IN_ONE_LANGUAGE = [
        // "10:00 Uhr" has no English counterpart: "10:00".
        'lang/de/booking/admin.php::admin.common.clock_suffix',
    ];

    /**
     * Strings whose caller passes the placeholders of every language and each language
     * picks its own ("{file}::{key}").
     */
    private const OWN_PLACEHOLDERS_PER_LANGUAGE = [
        // PyramidFormat::shortDate passes day, month, month_name and day_number: "14.08." / "August 14".
        'app/Modules/Pyramid/resources/lang/de/ui.php::fmt.short_date',
        // ZF2 wording "uebernommen:copied": the placeholder is glued to a word in German, the translator still replaces it.
        'app/Modules/Tournament/resources/lang/de/admin.php::lifecycle.created',
    ];

    /**
     * Aggregators that only merge the booking/*.php files (and an untracked local.php), and
     * per-installation examples that are templates, not translations.
     */
    private static function skipsKeyCheck(string $relative): bool
    {
        return $relative === 'booking.php' || str_ends_with($relative, '.example.php');
    }

    private static function base(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 2));
    }

    /**
     * @return array<string, string> label => absolute tree path (holding de/ and en/)
     */
    private static function trees(): array
    {
        $base = self::base();
        $trees = ['lang' => $base.'/lang'];

        foreach (glob($base.'/app/Modules/*/resources/lang', GLOB_ONLYDIR) ?: [] as $tree) {
            $tree = str_replace('\\', '/', $tree);
            if (is_dir($tree.'/de') || is_dir($tree.'/en')) {
                $trees[substr($tree, strlen($base) + 1)] = $tree;
            }
        }

        return $trees;
    }

    /**
     * @return list<string> file paths relative to the language directory, sorted
     */
    private static function files(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($dir)), '/');
            if (! in_array($relative, self::UNTRACKED, true)) {
                $files[] = $relative;
            }
        }
        sort($files);

        return $files;
    }

    /** @return array<string, array{string}> */
    public static function treeProvider(): array
    {
        $cases = [];
        foreach (array_keys(self::trees()) as $label) {
            $cases[$label] = [$label];
        }

        return $cases;
    }

    /** @return array<string, array{string, string}> */
    public static function langFileProvider(): array
    {
        $cases = [];
        foreach (self::trees() as $label => $tree) {
            foreach (self::files($tree.'/de') as $relative) {
                if (! self::skipsKeyCheck($relative) && is_file($tree.'/en/'.$relative)) {
                    $cases[$label.'/de/'.$relative] = [$tree.'/de/'.$relative, $tree.'/en/'.$relative];
                }
            }
        }

        // German validation messages against the framework's English ones.
        $cases['lang/de/validation.php (framework en)'] = [
            self::base().'/lang/de/validation.php',
            self::base().'/vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php',
        ];

        return $cases;
    }

    #[Test]
    public function the_module_trees_are_discovered(): void
    {
        $trees = array_keys(self::trees());

        foreach (['Pyramid', 'MixedTournament', 'TournamentBase', 'Members'] as $module) {
            $this->assertContains('app/Modules/'.$module.'/resources/lang', $trees);
        }
        $this->assertGreaterThan(20, count(self::langFileProvider()));
    }

    #[Test]
    #[DataProvider('treeProvider')]
    public function every_file_exists_in_both_languages(string $label): void
    {
        $tree = self::trees()[$label];
        $de = self::files($tree.'/de');
        $en = self::files($tree.'/en');
        $this->assertNotSame([], $de, 'no German files');

        $exempt = $label === 'lang' ? self::ONE_LANGUAGE_ONLY : [];
        $missingInEn = array_values(array_filter(array_diff($de, $en), static fn (string $f): bool => ! in_array('de/'.$f, $exempt, true)));
        $missingInDe = array_values(array_filter(array_diff($en, $de), static fn (string $f): bool => ! in_array('en/'.$f, $exempt, true)));

        $this->assertSame([], $missingInEn, 'missing in en');
        $this->assertSame([], $missingInDe, 'missing in de');
    }

    #[Test]
    #[DataProvider('langFileProvider')]
    public function de_and_en_files_have_the_same_keys_and_placeholders(string $de, string $en): void
    {
        $this->assertFileExists($en, 'missing en counterpart');

        $deLines = self::flatten(require $de);
        $enLines = self::flatten(require $en);
        $file = substr(str_replace('\\', '/', $de), strlen(self::base()) + 1);

        if (str_contains($en, '/vendor/')) {
            // The framework file carries a sample custom message and leaves the attribute
            // names to the app; German attribute names have no English counterpart (English
            // derives them from the field name).
            $enLines = array_filter($enLines, static fn (string $k): bool => ! str_starts_with($k, 'custom.') && $k !== 'attributes', ARRAY_FILTER_USE_KEY);
            $deLines = array_filter($deLines, static fn (string $k): bool => ! str_starts_with($k, 'attributes.'), ARRAY_FILTER_USE_KEY);
        }

        $this->assertSame([], array_values(array_diff(array_keys($deLines), array_keys($enLines))), 'missing in en');
        $this->assertSame([], array_values(array_diff(array_keys($enLines), array_keys($deLines))), 'missing in de');

        $placeholderMismatches = [];
        $emptyMismatches = [];
        foreach ($deLines as $key => $deLine) {
            $enLine = $enLines[$key];

            $id = $file.'::'.$key;

            if (is_string($deLine) && is_string($enLine)) {
                $differing = self::differingPlaceholders($deLine, $enLine);
                if ($differing !== [] && ! in_array($id, self::OWN_PLACEHOLDERS_PER_LANGUAGE, true)) {
                    $placeholderMismatches[] = $key.': '.implode(', ', $differing);
                }
                if ((trim($deLine) === '') !== (trim($enLine) === '') && ! in_array($id, self::EMPTY_IN_ONE_LANGUAGE, true)) {
                    $emptyMismatches[] = $key;
                }
            } elseif (is_array($deLine) !== is_array($enLine) || ($deLine === []) !== ($enLine === [])) {
                $emptyMismatches[] = $key;
            }
        }

        $this->assertSame([], $placeholderMismatches, 'placeholders differ');
        $this->assertSame([], $emptyMismatches, 'empty in one language only (or list vs. text)');
    }

    #[Test]
    public function placeholders_are_compared_like_the_translator_replaces_them(): void
    {
        $this->assertSame([], self::differingPlaceholders(':Name spielt am :date um 10:30 (https://club.example)', ':date: :NAME plays at 10:30'));
        $this->assertSame([], self::differingPlaceholders(':field: :old → :new', ':field: :old → :new'));
        $this->assertSame([], self::differingPlaceholders('Satz 1:1 - Ergebnis 6:4', 'Set 1:1 - score 6:4'));
        // The translator replaces :size inside ":sizeer" ("4er-Gruppe").
        $this->assertSame([], self::differingPlaceholders(':sizeer-Gruppe', 'groups of :size'));
        $this->assertSame(['name'], self::differingPlaceholders('Hallo :name', 'Hello'));
        $this->assertSame(['count', 'number'], self::differingPlaceholders(':count Plätze', ':number courts'));
    }

    /**
     * Lists (for example the booking quotes) are one leaf: their items differ in number per language.
     *
     * @param  array<array-key, mixed>  $array
     * @return array<string, mixed>
     */
    private static function flatten(array $array, string $prefix = ''): array
    {
        $lines = [];
        foreach ($array as $key => $value) {
            $path = $prefix.$key;
            if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                $lines += self::flatten($value, $path.'.');
            } else {
                $lines[$path] = $value;
            }
        }

        return $lines;
    }

    /**
     * Placeholder names used in one line but not the other, sorted.
     *
     * Mirrors the translator: names match case-insensitively (:Name, :NAME) and a name is
     * also replaced when letters follow it (":sizeer" holds :size), so a token that merely
     * extends another candidate is not a name of its own.
     *
     * @return list<string>
     */
    private static function differingPlaceholders(string $a, string $b): array
    {
        $tokens = [];
        foreach ([$a, $b] as $line) {
            preg_match_all('/(?<![\w:\/]):([a-zA-Z_][a-zA-Z0-9_]*)/', $line, $matches);
            array_push($tokens, ...array_map('strtolower', $matches[1]));
        }
        $tokens = array_values(array_unique($tokens));
        $names = array_filter($tokens, static function (string $token) use ($tokens): bool {
            foreach ($tokens as $other) {
                if ($other !== $token && str_starts_with($token, $other)) {
                    return false;
                }
            }

            return true;
        });

        $differing = [];
        foreach ($names as $name) {
            $pattern = '/(?<![\w:\/]):'.preg_quote($name, '/').'/i';
            if ((preg_match($pattern, $a) === 1) !== (preg_match($pattern, $b) === 1)) {
                $differing[] = $name;
            }
        }
        sort($differing);

        return $differing;
    }
}
