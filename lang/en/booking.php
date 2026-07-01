<?php

declare(strict_types=1);

$translations = array_merge(
    require __DIR__ . '/booking/public.php',
    require __DIR__ . '/booking/account.php',
    require __DIR__ . '/booking/admin.php',
    require __DIR__ . '/booking/repeat.php',
    require __DIR__ . '/booking/mail.php',
    require __DIR__ . '/booking/validation.php',
    require __DIR__ . '/booking/messages.php',
);

// Optional, per-installation file (not tracked in git) to override individual
// translation strings without touching the files above. Keys use dot
// notation relative to this array, e.g. 'admin.bookings.title'.
// See booking/local.example.php.
$localFile = __DIR__ . '/booking/local.php';
if (file_exists($localFile)) {
    foreach (require $localFile as $key => $value) {
        data_set($translations, $key, $value);
    }
}

return $translations;
