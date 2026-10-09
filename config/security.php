<?php

return [
    'admin_confirmation_seconds' => 1800,
    // Optional additional protection. Empty keeps mobile/dynamic-IP administrators usable.
    'admin_allowed_ips' => array_values(array_filter(array_map('trim', explode(',', env('ADMIN_ALLOWED_IPS', ''))))),
];
