<?php

return [
    'disk' => env('ADVERTISER_ASSET_DISK', 'public'),
    'maximum_image_kilobytes' => (int) env('ADVERTISER_IMAGE_MAX_KB', 10240),
    'maximum_video_kilobytes' => (int) env('ADVERTISER_VIDEO_MAX_KB', 51200),
    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
        'video/webm',
    ],
];
