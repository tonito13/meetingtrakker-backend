<?php
declare(strict_types=1);

/**
 * AWS Configuration for MeetingTrakker Application
 * 
 * This file contains AWS S3 configuration for file storage.
 * Uses the same bucket and credentials as Skiltrakker.
 * Make sure to set the following environment variables:
 * - AWS_ACCESS_KEY_ID
 * - AWS_SECRET_ACCESS_KEY
 * - AWS_REGION
 * - AWS_BUCKET
 */
return [
    'AWS' => [
        'region' => env('AWS_REGION', 'ap-southeast-1'), // Asia Pacific (Singapore)
        'version' => 'latest',
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID', ''),
            'secret' => env('AWS_SECRET_ACCESS_KEY', ''),
        ],
        'bucket' => env('AWS_BUCKET', 'workmatica-products'), // Same bucket as Skiltrakker
        'folders' => [
            'employees' => 'employees/',
            // Add other folder types as needed for MeetingTrakker
        ],
        'settings' => [
            'ACL' => 'private',
            'StorageClass' => 'STANDARD',
            'ServerSideEncryption' => 'AES256'
        ]
    ]
];
