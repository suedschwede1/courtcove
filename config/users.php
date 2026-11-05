<?php

declare(strict_types=1);

return [
    /*
    | Where the permission of a user (member / assist / admin) is stored.
    |
    | false (default): merged into bs_users.status as the old system expects
    |   (status "admin" / "assist" = enabled account with that permission).
    | true: bs_users.role holds the permission and bs_users.status only the
    |   account state. Run
   ERROR  There are no commands defined in the "users" namespace.   once after switching.
    */
    'split_role' => (bool) env('USERS_SPLIT_ROLE', false),
];
