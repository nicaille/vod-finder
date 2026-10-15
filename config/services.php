<?php

return [

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