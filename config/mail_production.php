<?php

/**
 * Production Mail Configuration Override
 * This file forces SMTP mail configuration for production environments
 * 
 * IMPORTANT: This is a temporary solution. 
 * Properly configure environment variables in Render Dashboard instead.
 */

return [
    'default' => env('MAIL_MAILER', 'smtp'),
    
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.sendgrid.net'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME', 'apikey'),
            'password' => env('MAIL_PASSWORD', ''),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],
    ],
    
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@bagcomics.com'),
        'name' => env('MAIL_FROM_NAME', 'BAG Comics'),
    ],
];