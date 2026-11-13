<?php

/**
 * Copiá este archivo como config.php y pegá tu token de Replicate.
 * config.php no se sirve por la web y no se versiona.
 */
return [
    'replicate_token' => '',
    'max_upload_bytes' => 8 * 1024 * 1024,
    'max_files' => 8,
    'max_pixels' => 16000000,
    'poll_timeout_seconds' => 180,
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'convertlh',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'models' => [
        'remove_bg' => [
            'owner' => 'sprited',
            'name' => 'birefnet',
            'image_field' => 'image',
            'extra' => [
                'output_format' => 'cutout',
            ],
        ],
        'upscale' => [
            'owner' => 'nightmareai',
            'name' => 'real-esrgan',
            'image_field' => 'image',
            'scale_field' => 'scale',
            'extra' => [
                'face_enhance' => false,
            ],
        ],
    ],
];
