<?php

class Formats
{
    public static function all()
    {
        return [
            'png' => ['label' => 'PNG', 'mime' => 'image/png', 'alpha' => true],
            'jpg' => ['label' => 'JPG', 'mime' => 'image/jpeg', 'alpha' => false],
            'webp' => ['label' => 'WEBP', 'mime' => 'image/webp', 'alpha' => true],
            'bmp' => ['label' => 'BMP', 'mime' => 'image/bmp', 'alpha' => false],
            'gif' => ['label' => 'GIF', 'mime' => 'image/gif', 'alpha' => false],
            'tiff' => ['label' => 'TIFF', 'mime' => 'image/tiff', 'alpha' => true],
            'ico' => ['label' => 'ICO', 'mime' => 'image/x-icon', 'alpha' => true],
            'avif' => ['label' => 'AVIF', 'mime' => 'image/avif', 'alpha' => true],
        ];
    }

    public static function catalog()
    {
        $out = [];
        foreach (self::all() as $id => $meta) {
            $meta['ok'] = self::canWrite($id);
            $meta['reason'] = $meta['ok'] ? '' : self::reason($id);
            $out[$id] = $meta;
        }
        return $out;
    }

    public static function canWrite($id)
    {
        if ($id === 'png') {
            return function_exists('imagepng');
        }
        if ($id === 'jpg') {
            return function_exists('imagejpeg');
        }
        if ($id === 'webp') {
            return function_exists('imagewebp');
        }
        if ($id === 'bmp') {
            return function_exists('imagebmp');
        }
        if ($id === 'gif') {
            return function_exists('imagegif');
        }
        if ($id === 'avif') {
            return function_exists('imageavif');
        }
        return false;
    }

    public static function reason($id)
    {
        $name = strtoupper((string) $id);
        if ($name === 'TIFF' || $name === 'ICO' || $name === 'AVIF') {
            return 'Este PHP no puede escribir ' . $name . '. No hay Imagick ni soporte de GD para ese formato.';
        }
        return 'Este PHP no puede escribir ' . $name . '.';
    }

    public static function inputMimes()
    {
        return [
            'image/jpeg' => true,
            'image/png' => true,
            'image/webp' => true,
            'image/gif' => true,
            'image/bmp' => true,
            'image/x-ms-bmp' => true,
        ];
    }
}
