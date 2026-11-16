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
        'max_files' => 1,
        'ai_max_files' => 3,
        'max_pixels' => 16000000,
        'poll_timeout_seconds' => 100,
        'mercadopago' => [
            'access_token' => '',
        ],
        'db' => [
            'host' => '127.0.0.1',
            'name' => 'convertlh',
            'user' => 'root',
            'pass' => '',
            'charset' => 'utf8mb4',
        ],
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

function clh_cookie_path()
{
    $script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/';
    $dir = str_replace('\\', '/', dirname($script));
    $dir = rtrim($dir, '/');
    if ($dir === '' || $dir === '.') {
        return '/';
    }
    return $dir . '/';
}

function clh_theme_ids()
{
    return ['naranja', 'cobre', 'ambar'];
}

function clh_current_theme()
{
    if (isset($_COOKIE['clh_theme']) && in_array($_COOKIE['clh_theme'], clh_theme_ids(), true)) {
        return $_COOKIE['clh_theme'];
    }
    return 'naranja';
}

function clh_remember_theme($theme)
{
    if (!in_array($theme, clh_theme_ids(), true)) {
        return;
    }
    setcookie('clh_theme', $theme, [
        'expires' => time() + 3600 * 24 * 400,
        'path' => clh_cookie_path(),
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['clh_theme'] = $theme;
}

function clh_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clh_device_id()
{
    $current = isset($_COOKIE['clh_device']) ? (string) $_COOKIE['clh_device'] : '';
    if (!preg_match('/^[a-f0-9]{32}$/', $current)) {
        $current = bin2hex(random_bytes(16));
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie('clh_device', $current, [
                'expires' => time() + 3600 * 24 * 400,
                'path' => clh_cookie_path(),
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE['clh_device'] = $current;
    }
    return $current;
}

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => clh_cookie_path(),
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    clh_device_id();
}
