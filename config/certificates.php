<?php

return [
    'number' => [
        'prefix' => env('CERTIFICATE_NUMBER_PREFIX', 'CERT'),
        'digits' => (int) env('CERTIFICATE_NUMBER_DIGITS', 6),
    ],

    'storage' => [
        'pdf_disk' => env('CERTIFICATE_PDF_DISK', 'local'),
        'pdf_directory' => env(
            'CERTIFICATE_PDF_DIRECTORY',
            'certificates/issued'
        ),
        'template_background_disk' => env(
            'CERTIFICATE_TEMPLATE_BACKGROUND_DISK',
            'public'
        ),
        'snapshot_background_disk' => env(
            'CERTIFICATE_SNAPSHOT_BACKGROUND_DISK',
            'local'
        ),
        'snapshot_background_directory' => env(
            'CERTIFICATE_SNAPSHOT_BACKGROUND_DIRECTORY',
            'certificates/template-backgrounds'
        ),
    ],

    'pdf' => [
        'minimum_bytes' => (int) env(
            'CERTIFICATE_PDF_MINIMUM_BYTES',
            5000
        ),
        'css_entry' => env(
            'CERTIFICATE_PDF_CSS_ENTRY',
            'resources/css/app.css'
        ),
    ],

    'gotenberg' => [
        'url' => rtrim(
            env('GOTENBERG_URL', 'http://127.0.0.1:3000'),
            '/'
        ),
        'endpoint' => '/forms/chromium/convert/html',
        'connect_timeout' => (int) env(
            'GOTENBERG_CONNECT_TIMEOUT',
            5
        ),
        'timeout' => (int) env('GOTENBERG_TIMEOUT', 45),
        'retries' => (int) env('GOTENBERG_RETRIES', 1),
        'retry_delay_ms' => (int) env(
            'GOTENBERG_RETRY_DELAY_MS',
            250
        ),
        'wait_for_expression' => env(
            'GOTENBERG_WAIT_FOR_EXPRESSION',
            'window.certificateReady === true'
        ),
    ],

    'queue' => env('CERTIFICATE_QUEUE', 'certificates'),
    'date_format' => env('CERTIFICATE_DATE_FORMAT', 'd M Y'),
];
