<?php

class Converter
{
    public static function load($path)
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new UserException('Este PHP no tiene GD. Sin GD no se pueden convertir imágenes.');
        }

        $binary = @file_get_contents($path);
        if ($binary === false || $binary === '') {
            throw new UserException('No se pudo leer el archivo.');
        }

        $image = @imagecreatefromstring($binary);
        if (!$image) {
            throw new UserException('El archivo no es una imagen que este servidor pueda leer. Usá PNG, JPG, WEBP, GIF o BMP.');
        }

        return self::toTruecolor($image);
    }

    public static function flip($image, $direction)
    {
        if (!function_exists('imageflip')) {
            throw new UserException('Este PHP no puede espejar imágenes.');
        }
        if ($direction === 'vertical') {
            $mode = IMG_FLIP_VERTICAL;
        } elseif ($direction === 'both') {
            $mode = IMG_FLIP_BOTH;
        } else {
            $mode = IMG_FLIP_HORIZONTAL;
        }
        if (!imageflip($image, $mode)) {
            throw new UserException('No se pudo espejar la imagen.');
        }
        return $image;
    }

    public static function cropToContent($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 2 || $height < 2) {
            return ['image' => $image, 'notice' => ''];
        }

        $useAlpha = self::hasTransparency($image);
        $background = null;
        if (!$useAlpha) {
            $background = self::cornerColor($image);
            if ($background === null) {
                return [
                    'image' => $image,
                    'notice' => 'No recorté por color: las esquinas no son del mismo tono.',
                ];
            }
        }

        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (!self::isContent($image, $x, $y, $useAlpha, $background)) {
                    continue;
                }
                if ($x < $minX) {
                    $minX = $x;
                }
                if ($y < $minY) {
                    $minY = $y;
                }
                if ($x > $maxX) {
                    $maxX = $x;
                }
                if ($y > $maxY) {
                    $maxY = $y;
                }
            }
        }

        if ($maxX < $minX || $maxY < $minY) {
            $notice = $useAlpha
                ? 'El recorte no encontró contenido, así que dejé la imagen original.'
                : 'No recorté: no hay un margen distinto del color de las esquinas.';
            return [
                'image' => $image,
                'notice' => $notice,
            ];
        }

        $cropWidth = $maxX - $minX + 1;
        $cropHeight = $maxY - $minY + 1;
        if ($cropWidth < 2 || $cropHeight < 2) {
            return [
                'image' => $image,
                'notice' => 'El recorte dejó casi nada de imagen, así que no lo apliqué.',
            ];
        }
        if ($cropWidth === $width && $cropHeight === $height) {
            return ['image' => $image, 'notice' => ''];
        }

        $cropped = imagecreatetruecolor($cropWidth, $cropHeight);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        $clear = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
        imagefilledrectangle($cropped, 0, 0, $cropWidth, $cropHeight, $clear);
        imagecopy($cropped, $image, 0, 0, $minX, $minY, $cropWidth, $cropHeight);

        return ['image' => $cropped, 'notice' => ''];
    }

    public static function flatten($image, $hex)
    {
        $rgb = self::hexToRgb($hex);
        $width = imagesx($image);
        $height = imagesy($image);
        $flat = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($flat, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($flat, 0, 0, $width, $height, $background);
        imagealphablending($flat, true);
        imagecopy($flat, $image, 0, 0, 0, 0, $width, $height);
        imagealphablending($flat, false);
        imagesavealpha($flat, false);
        return $flat;
    }

    public static function encode($image, $format, $quality, $fill)
    {
        $catalog = Formats::all();
        if (!isset($catalog[$format]) || !Formats::canWrite($format)) {
            throw new UserException(Formats::reason($format));
        }

        $working = $image;
        if (!$catalog[$format]['alpha']) {
            $working = self::flatten($image, $fill);
        } else {
            imagealphablending($working, false);
            imagesavealpha($working, true);
        }

        $quality = max(0, min(100, (int) $quality));
        $tmp = tempnam(sys_get_temp_dir(), 'clhout');
        if ($tmp === false) {
            throw new UserException('No se pudo crear un archivo temporal.');
        }

        $ok = false;
        if ($format === 'png') {
            $level = (int) round((100 - $quality) * 9 / 100);
            $ok = imagepng($working, $tmp, $level);
        } elseif ($format === 'jpg') {
            $ok = imagejpeg($working, $tmp, max(1, $quality));
        } elseif ($format === 'webp') {
            $ok = imagewebp($working, $tmp, max(0, $quality));
        } elseif ($format === 'bmp') {
            $ok = imagebmp($working, $tmp, true);
        } elseif ($format === 'gif') {
            $ok = imagegif($working, $tmp);
        } elseif ($format === 'avif' && function_exists('imageavif')) {
            $ok = imageavif($working, $tmp, max(0, $quality));
        }

        if ($working !== $image && is_resource($working)) {
            imagedestroy($working);
        }

        if (!$ok) {
            @unlink($tmp);
            throw new UserException('No se pudo guardar el formato ' . strtoupper($format) . '.');
        }

        $binary = file_get_contents($tmp);
        @unlink($tmp);
        if ($binary === false || $binary === '') {
            throw new UserException('El archivo de salida quedó vacío.');
        }

        return $binary;
    }

    public static function applyDpi($binary, $format, $dpi)
    {
        $dpi = (int) $dpi;
        if ($dpi <= 0) {
            return $binary;
        }
        if ($format === 'png') {
            return self::setPngDpi($binary, $dpi);
        }
        if ($format === 'jpg') {
            return self::setJpegDpi($binary, $dpi);
        }
        return $binary;
    }

    public static function sniff($binary)
    {
        if (substr($binary, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return 'png';
        }
        if (substr($binary, 0, 2) === "\xFF\xD8") {
            return 'jpg';
        }
        if (strlen($binary) >= 12 && substr($binary, 0, 4) === 'RIFF' && substr($binary, 8, 4) === 'WEBP') {
            return 'webp';
        }
        if (substr($binary, 0, 6) === 'GIF87a' || substr($binary, 0, 6) === 'GIF89a') {
            return 'gif';
        }
        return 'png';
    }

    public static function dimensions($binary)
    {
        $info = @getimagesizefromstring($binary);
        if (!$info) {
            return [0, 0];
        }
        return [(int) $info[0], (int) $info[1]];
    }

    public static function normalizeHex($hex)
    {
        $hex = strtolower(trim((string) $hex));
        if (!preg_match('/^#[0-9a-f]{6}$/', $hex)) {
            throw new UserException('El color tiene que ser un hexadecimal como #ffffff.');
        }
        return $hex;
    }

    private static function toTruecolor($image)
    {
        if (imageistruecolor($image)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            return $image;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $true = imagecreatetruecolor($width, $height);
        imagealphablending($true, false);
        imagesavealpha($true, true);
        $clear = imagecolorallocatealpha($true, 0, 0, 0, 127);
        imagefilledrectangle($true, 0, 0, $width, $height, $clear);
        imagecopy($true, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);
        return $true;
    }

    private static function hasTransparency($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        for ($x = 0; $x < $width; $x++) {
            if (self::pixelTransparent($image, $x, 0) || self::pixelTransparent($image, $x, $height - 1)) {
                return true;
            }
        }
        for ($y = 0; $y < $height; $y++) {
            if (self::pixelTransparent($image, 0, $y) || self::pixelTransparent($image, $width - 1, $y)) {
                return true;
            }
        }

        $step = max(1, (int) floor(max($width, $height) / 280));
        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                if (self::pixelTransparent($image, $x, $y)) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function pixelTransparent($image, $x, $y)
    {
        $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
        return $alpha > 20;
    }

    private static function cornerColor($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $points = [
            [0, 0],
            [$width - 1, 0],
            [0, $height - 1],
            [$width - 1, $height - 1],
        ];
        $colors = [];
        foreach ($points as $point) {
            $colors[] = imagecolorat($image, $point[0], $point[1]) & 0xFFFFFF;
        }
        foreach ($colors as $color) {
            if (!self::colorsNear($colors[0], $color, 28)) {
                return null;
            }
        }
        return $colors[0];
    }

    private static function isContent($image, $x, $y, $useAlpha, $background)
    {
        $color = imagecolorat($image, $x, $y);
        if ($useAlpha) {
            $alpha = ($color >> 24) & 0x7F;
            return $alpha <= 80;
        }
        return !self::colorsNear($background, $color & 0xFFFFFF, 28);
    }

    private static function colorsNear($left, $right, $tolerance)
    {
        $lr = ($left >> 16) & 255;
        $lg = ($left >> 8) & 255;
        $lb = $left & 255;
        $rr = ($right >> 16) & 255;
        $rg = ($right >> 8) & 255;
        $rb = $right & 255;
        return abs($lr - $rr) <= $tolerance
            && abs($lg - $rg) <= $tolerance
            && abs($lb - $rb) <= $tolerance;
    }

    private static function hexToRgb($hex)
    {
        $hex = self::normalizeHex($hex);
        return [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];
    }

    private static function setPngDpi($binary, $dpi)
    {
        $signature = "\x89PNG\r\n\x1a\n";
        if (substr($binary, 0, 8) !== $signature) {
            return $binary;
        }

        $ppm = (int) round($dpi * 39.370078740157);
        if ($ppm < 1) {
            $ppm = 1;
        }
        $data = pack('NNC', $ppm, $ppm, 1);
        $chunk = pack('N', 9) . 'pHYs' . $data . hash('crc32b', 'pHYs' . $data, true);

        $position = 8;
        $length = strlen($binary);
        $output = $signature;
        $inserted = false;

        while ($position + 12 <= $length) {
            $chunkLength = unpack('N', substr($binary, $position, 4))[1];
            $type = substr($binary, $position + 4, 4);
            $whole = 12 + $chunkLength;
            if ($chunkLength < 0 || $position + $whole > $length) {
                return $binary;
            }
            if ($type === 'pHYs') {
                $position += $whole;
                continue;
            }
            $output .= substr($binary, $position, $whole);
            $position += $whole;
            if ($type === 'IHDR' && !$inserted) {
                $output .= $chunk;
                $inserted = true;
            }
        }

        return $inserted ? $output : $binary;
    }

    private static function setJpegDpi($binary, $dpi)
    {
        if (substr($binary, 0, 2) !== "\xFF\xD8") {
            return $binary;
        }

        $dpi = max(1, min(65535, (int) $dpi));
        $density = pack('n', $dpi) . pack('n', $dpi);
        $position = 2;
        $length = strlen($binary);

        while ($position + 4 < $length) {
            if ($binary[$position] !== "\xFF") {
                break;
            }
            $marker = $binary[$position + 1];
            if ($marker === "\xD8" || $marker === "\xD9" || $marker === "\xDA") {
                break;
            }
            $segmentLength = unpack('n', substr($binary, $position + 2, 2))[1];
            if ($segmentLength < 2 || $position + 2 + $segmentLength > $length) {
                break;
            }
            if ($marker === "\xE0" && substr($binary, $position + 4, 5) === "JFIF\x00") {
                $binary[$position + 11] = "\x01";
                return substr_replace($binary, $density, $position + 12, 4);
            }
            $position += 2 + $segmentLength;
        }

        $app0 = "\xFF\xE0\x00\x10" . "JFIF\x00" . "\x01\x01\x01" . $density . "\x00\x00";
        return "\xFF\xD8" . $app0 . substr($binary, 2);
    }
}
