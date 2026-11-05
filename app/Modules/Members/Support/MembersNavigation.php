<?php

declare(strict_types=1);

namespace App\Modules\Members\Support;

use Illuminate\Http\Request;

/**
 * Navigation of the standalone Members area: one row of tabs under the page header
 * (overview, members, dues, mail, settings) and, on the settings pages, a second row
 * with the settings pages. An entry is active when the current route matches one of
 * its patterns.
 */
final class MembersNavigation
{
    /**
     * tab key => [route, active route patterns, label key]; the settings tab takes the patterns of SETTINGS.
     *
     * @var array<string, array{0: string, 1: list<string>, 2: string}>
     */
    private const TABS = [
        'dashboard' => ['members.dashboard', ['members.dashboard'], 'members::admin.dashboard.title'],
        'members' => ['members.index', ['members.index', 'members.create', 'members.edit', 'members.payments.*'], 'members::admin.members.title'],
        'dues' => ['members.dues.index', ['members.dues.*'], 'members::admin.dues.title'],
        'mail' => ['members.mail.index', ['members.mail.index', 'members.mail.templates.*'], 'members::admin.mail.title'],
        'settings' => ['members.fiscal-years.index', [], 'members::admin.settings.title'],
    ];

    /**
     * Pages of the settings tab: key => [route, active route patterns, label key]
     *
     * @var array<string, array{0: string, 1: list<string>, 2: string}>
     */
    private const SETTINGS = [
        'fiscal_years' => ['members.fiscal-years.index', ['members.fiscal-years.*'], 'members::admin.fiscal_years.title'],
        'fee_categories' => ['members.fee-categories.index', ['members.fee-categories.*'], 'members::admin.fee_categories.title'],
        'functions' => ['members.functions.index', ['members.functions.*'], 'members::admin.functions.title'],
        'mail_config' => ['members.mail.config', ['members.mail.config'], 'members::admin.mail.config_title'],
        'dsgvo' => ['members.dsgvo.index', ['members.dsgvo.*'], 'members::admin.dsgvo.title'],
    ];

    /**
     * The module's tab row; the settings tab is active on every settings page.
     *
     * @return list<array{key: string, label: string, url: string, active: bool}>
     */
    public static function tabs(Request $request): array
    {
        $tabs = [];
        foreach (self::TABS as $key => [$route, $patterns, $label]) {
            $tabs[] = self::item($request, $key, $route, $key === 'settings' ? self::settingsPatterns() : $patterns, $label);
        }

        return $tabs;
    }

    /**
     * The second row on the settings pages; null on every other page.
     *
     * @return list<array{key: string, label: string, url: string, active: bool}>|null
     */
    public static function settings(Request $request): ?array
    {
        if (! $request->routeIs(...self::settingsPatterns())) {
            return null;
        }

        $items = [];
        foreach (self::SETTINGS as $key => [$route, $patterns, $label]) {
            $items[] = self::item($request, $key, $route, $patterns, $label);
        }

        return $items;
    }

    /**
     * All active route patterns of the module ('members') or of its settings pages ('configuration'),
     * for the admin sidebar entries.
     *
     * @return list<string>
     */
    public static function patterns(string $section): array
    {
        if ($section === 'configuration') {
            return self::settingsPatterns();
        }

        $patterns = [];
        foreach (self::TABS as $key => [, $tabPatterns]) {
            array_push($patterns, ...$tabPatterns);
        }

        return $patterns;
    }

    /**
     * @return list<string>
     */
    private static function settingsPatterns(): array
    {
        $patterns = [];
        foreach (self::SETTINGS as [, $itemPatterns]) {
            array_push($patterns, ...$itemPatterns);
        }

        return $patterns;
    }

    /**
     * @param  list<string>  $patterns
     * @return array{key: string, label: string, url: string, active: bool}
     */
    private static function item(Request $request, string $key, string $route, array $patterns, string $label): array
    {
        return [
            'key' => $key,
            'label' => (string) __($label),
            'url' => route($route),
            'active' => $patterns !== [] && $request->routeIs(...$patterns),
        ];
    }
}
