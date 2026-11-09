# Modules

A module is a self-contained area of CourtCove (the tournament areas are modules). Modules register
themselves in a registry; the main menu, the module overview (`/modules`), the administration group "Module", the
rights list of the user form and the module sidebar read only from that registry. Adding a module needs **no change
to core navigation code**.

Code: `app/Support/Modules` (`ModuleRegistry`, `ModuleDefinition`, `ModuleAdminEntry`, `ModuleNavigation`,
`ModuleAreaNavigation`). Pattern: the same as the notification type registry
(`App\Notifications\Registry\NotificationRegistry`), registered in the `boot()` of a service provider.

## New module in 10 steps

1. **Folder and provider.** Create `app/Modules/<Name>/<Name>ServiceProvider.php` and add it to
   `bootstrap/providers.php`. Load views, translations and migrations there (`loadViewsFrom`,
   `loadTranslationsFrom`, `loadMigrationsFrom`).
2. **Routes.** Add `app/Modules/<Name>/routes.php`, loaded in `boot()` inside
   `Route::middleware(ModuleRoutes::middleware())->group(...)`. Name every route with one common prefix
   (for example `demo.index`, `demo.admin.index`); the prefix becomes the module's route pattern (`demo.*`).
3. **Login and rights.** Protect routes with the `tournament-base.login:<translation key>` middleware (or your own)
   and, for administration, `can:admin.<name>`. A module right is always a privileged right: it only counts while
   admin mode is on.
4. **Frame.** Let page views extend `layouts.modules` (area shell with the module sidebar). Fill the sections
   `title`, `admin-title` (phone bar) and `admin-content`. Print views do not use the frame.
5. **Sidebar (optional).** Implement `App\Support\Modules\ModuleNavigation`: `entries()` for the module's pages and
   `settingsEntries()` for its administration pages. Build entries with the `AreaNavBuilder` passed in
   (`$nav->item(key, label, url, activePatterns, visibleRule)`); every entry has exactly one visibility rule.
   Each module has its own sidebar; modules that pass the same `sidebarGroup` share one group. The module overview
   shows the groups of all modules, so the navigation class must hide entries the viewer may not see itself (the
   registry does not filter sidebar entries).
6. **Texts.** Add the module name, a one-line description and the label of the module right in German and English
   (own translation namespace). The registry stores translation keys, not texts.
7. **Register.** At the end of `boot()`:

   ```php
   $this->app->make(ModuleRegistry::class)->register(new ModuleDefinition(
       key: 'demo',                                   // lower case, unique, also the menu entry key
       name: 'demo::common.name',                     // translation keys
       icon: 'trophy',                                // icon of the design system
       description: 'demo::common.description',
       route: 'demo.index',                           // entry route; without it the module is not listed
       routePatterns: ['demo.*'],                     // all pages of the module
       navigation: DemoNavigation::class,             // optional sidebar
       adminEntries: [new ModuleAdminEntry('demo', 'demo.admin.index', 'admin.demo', 'demo::common.admin', ['demo.admin.*'])],
       privileges: ['admin.demo' => 'demo::common.privilege'],
       order: 100,                                    // position in menu, overview, sidebar, administration
   ));
   ```

   Optional arguments: `visible` (closure `?User => bool`, default: every logged-in user; return `true` for
   guests to make the module public), `url` (closure for the entry URL when it is not `route(...)`),
   `inMenu: false` (only overview and sidebar), `sidebarGroup`.
8. **Administration entry.** The `adminEntries` above appear in the administration group "Module". An entry shows
   for the viewers who pass its ability; the group disappears when no entry is visible. Module settings pages are
   just further admin entries.
9. **Rights.** The keys in `privileges` are added to `User::privileges()` (user form, validation, admin mode
   check). Check them with `$user->can('admin.demo')` or `can:admin.demo`. A right must be registered here,
   otherwise it cannot be assigned and `can:` on it is not treated as an admin-mode right. A key that the core or
   another module already defines is refused when registering. Flags stored for a right stay in the user's data
   when its module is removed.
10. **Test.** Copy the pattern of `tests/Feature/Modules/Registry/DummyModuleTest.php`: register the module, then
    assert that it shows up in the menu, the overview, the administration group, the rights list and the sidebar.
    Keep the suite (`php artisan test --parallel --exclude-group=mysql`) and `composer lint` green.

## What the registry decides

| Place | Source |
|---|---|
| Main menu (dropdown "Module", phone menu) | available modules with `inMenu`, visible for the viewer, by `order`, plus the link to the overview |
| Overview `/modules` | available modules visible for the viewer (name, icon, description, entry URL) |
| Administration group "Module" | `adminEntries` of available modules, each by its own ability |
| Rights list of the user form | `privileges` of all registered modules plus `User::CORE_PRIVILEGES` |
| Module sidebar | `navigation` of the module the request belongs to (`routePatterns`); elsewhere all modules |

"Available" means the entry route exists, so a module whose routes are not registered is silently left out.

## Not built yet

Switching a module on or off in the administration (an option that the registry would respect for menu, overview,
sidebar and a route guard) is not part of this version.
