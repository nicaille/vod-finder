<?php

$oauthBase = rtrim(env('APP_URL', 'http://localhost'), '/');
if (!str_contains($oauthBase, '://')) $oauthBase = 'https://'.$oauthBase;

return [
    'google' => [
        'enabled' => env('GOOGLE_LOGIN_ENABLED', false),
        'client_id' => env('GOOGLE_CLIENT_ID'), 'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', $oauthBase.'/auth/google/callback'),
    ],
    'microsoft' => [
        'enabled' => env('MICROSOFT_LOGIN_ENABLED', false),
        'client_id' => env('MICROSOFT_CLIENT_ID'), 'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', $oauthBase.'/auth/microsoft/callback'),
        'tenant' => 'common', 'include_avatar' => false, 'include_tenant_info' => false,
    ],
    'facebook' => [
        'enabled' => env('FACEBOOK_LOGIN_ENABLED', false),
        'client_id' => env('FACEBOOK_CLIENT_ID'), 'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', $oauthBase.'/auth/facebook/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
	
    'tmdb' => [
        'key'      => env('TMDB_API_KEY'),
        'country'  => env('TMDB_COUNTRY', 'FR'),
        'language' => env('TMDB_LANGUAGE', 'fr-FR'),
    ],
    
    // The official v4 client uses this unversioned gateway for RapidAPI keys.
    // The /v4 prefix belongs to the separate direct Movie of the Night API.
    'streaming_availability' => [
        'key'       => env('STREAMING_AVAILABILITY_KEY'),
        'direct_key' => env('STREAMING_AVAILABILITY_DIRECT_KEY'),
        'direct_monthly_quota' => env('STREAMING_AVAILABILITY_DIRECT_MONTHLY_QUOTA', 1000),
        'host'      => env('STREAMING_AVAILABILITY_HOST', 'streaming-availability.p.rapidapi.com'),
        'base_url'  => env('STREAMING_AVAILABILITY_BASE_URL', 'https://streaming-availability.p.rapidapi.com'),
        'country'   => env('STREAMING_AVAILABILITY_COUNTRY_DEFAULT', 'fr'),
        'language'  => env('STREAMING_AVAILABILITY_LANGUAGE', 'fr'),
    ],
];
