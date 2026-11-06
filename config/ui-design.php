<?php

declare(strict_types=1);

return [
    // Reviewed inventory of legacy findings (see docs/DESIGN-ASSURANCE.md).
    'baseline' => base_path('ui-design-baseline.json'),

    // Every Blade/CSS source the design checks cover.
    'roots' => array_merge(
        [resource_path('views'), resource_path('css'), public_path('css')],
        glob(base_path('app/Modules/*/resources/views'), GLOB_ONLYDIR) ?: [],
        glob(base_path('Modules/*/resources/views'), GLOB_ONLYDIR) ?: [],
    ),

    // Migrated paths can never use legacy exceptions. All restyled views and
    // application CSS are migrated; only the legacy public/css sheet is inventoried.
    'migrated' => ['resources/', 'app/Modules/', 'Modules/'],
];
