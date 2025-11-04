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

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'jwt' => [
        'issuer' => env('JWT_ISS', 'https://solucomp.com/AuthCenter/'),
        'refresh_ttl' => env('JWT_REFRESH_TTL', 1209600),
        'ttl' => env('JWT_TTL', 300),
        'private_key_path' => env('JWT_PRIVATE_KEY_PATH'),
        'public_key_path' => env('JWT_PUBLIC_KEY_PATH', 'oauth-keys/public.pem'),
    ],

    'sync' => [
        'secret' => env('SYNC_SECRET'),
    ],

    // ETS integration secret used for secure unlock operations
    'ets' => [
        'secret' => env('ETS_SECRET'),
    ],

    'domain_urls' => [
        'ccms' => env('CCMS_BASE_URL', 'http://localhost:8000'),
        'jobfinder' => env('JOB_FINDER_BASE_URL', 'http://localhost:8003'),
        'solucomp' => env('SOLUCOMP_BASE_URL', 'http://localhost:8004'),
    ],

    'base_url' => env('CCMS_BASE_URL', 'http://localhost:8000/api'),
    'job_finder_base_url' => env('JOB_FINDER_BASE_URL', 'http://localhost:8003/api'),
    'solucomp_base_url' => env('SOLUCOMP_BASE_URL', 'http://localhost:8004/api'),
    'samsung_base_url' => env('SAMSUNG_BASE_URL', 'http://localhost:8005/api'),
    'job_finder_sync' => env('JOB_FINDER_SYNC', 'http://localhost:8003/api/auth-sync'),
    'ccms_sync' => env('CCMS_SYNC', 'http://localhost:8000/api/auth-sync'),
    'ets_sync' => env('ETS_SYNC', 'http://localhost:8006/api/auth-sync'),

    'delete_user_routes' => [
        'ccms'      => env('CCMS_DELETE_SYNC'),
        'jobfinder' => env('JOB_FINDER_DELETE_SYNC'),
        'solucomp'  => env('SOLUCOMP_DELETE_SYNC'),
    ],

    'new_user_sync_routes' => [
        'ccms'      => env('CCMS_USER_SYNC'),
        'jobfinder' => env('JOB_FINDER_USER_SYNC'),
        'solucomp'  => env('SOLUCOMP_USER_SYNC'),
    ],

    'recaptcha' => [
        'secret' => env('RECAPTCHA_SECRET_KEY'),
    ],

    'solucomp_page_permissions' => [
        'permissions' => env('SOLUCOMP_USERPAGE_PERMISSION'),
        'page_permissions' => env('SOLUCOMP_PAGE_PERMISSION'),
    ],

    'sso' => [
        'shared_token' => env('SSO_SHARED_TOKEN'),
    ],

    'deactivate_domain' => [
        'ccms' => env('CCMS_DEACTIVATE_INFO'),
        'jobfinder' => env('JOB_FINDER_DEACTIVATE_INFO'),
    ],



];
