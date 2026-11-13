<?php

define('CLH_ROOT', dirname(__DIR__));

class UserException extends RuntimeException
{
}

function clh_config()
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'replicate_token' => '',
        'max_upload_bytes' => 8 * 1024 * 1024,
        'max_files' => 8,
        'max_pixels' => 16000000,
        'poll_timeout_seconds' => 100,
        'models' => [
            'remove_bg' => [
                'owner' => 'cjwbw',
                'name' => 'rembg',
                'image_field' => 'image',
                'extra' => [],
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

    $path = CLH_ROOT . '/config.php';
    if (!is_file($path)) {
        $path = CLH_ROOT . '/config.example.php';
    }

    $loaded = require $path;
    if (!is_array($loaded)) {
        $loaded = [];
    }

    $config = array_replace_recursive($defaults, $loaded);
    return $config;
}

function clh_tmp_root()
{
    $dir = CLH_ROOT . '/storage/tmp';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new UserException('No se pudo crear la carpeta temporal.');
    }
    if (!is_writable($dir)) {
        throw new UserException('La carpeta temporal no tiene permiso de escritura.');
    }
    return $dir;
}
