<?php

declare(strict_types=1);

return array_merge(
    require __DIR__ . '/booking/public.php',
    require __DIR__ . '/booking/account.php',
    require __DIR__ . '/booking/admin.php',
    require __DIR__ . '/booking/repeat.php',
    require __DIR__ . '/booking/mail.php',
    require __DIR__ . '/booking/validation.php',
    require __DIR__ . '/booking/messages.php',
);
