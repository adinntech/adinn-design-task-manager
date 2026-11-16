<?php

/*
 * Who may use the mobile app (API under /api/v1). Everyone else is refused at sign-in, and an
 * account that is already signed in on a phone has its token revoked on its next request.
 *
 * To open it up again without a deploy, set MOBILE_APP_ROLES in .env, e.g.
 * MOBILE_APP_ROLES=bd,designer,designer_head,admin (then `php artisan config:clear`).
 */
return [
    'allowed_roles' => array_values(array_filter(array_map('trim', explode(',', (string) env('MOBILE_APP_ROLES', 'bd,designer'))))),

    'blocked_message' => 'Admin and Designer Head accounts cannot use the mobile app. Please use the website instead.',
];
