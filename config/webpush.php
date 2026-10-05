<?php

return [
    'key_file' => storage_path('app/private/webpush.json'),
    'subject' => env('WEBPUSH_SUBJECT', env('APP_URL', 'http://localhost')),
];
