<?php

require_once __DIR__ . '/Converter.php';

class Mockup
{
    public static function options()
    {
        return [
            'remera-frente' => [
                'label' => 'Remera frente',
                'file' => 'remera-frente.jpg',
                'areas' => [[912, 440, 555, 556]],
            ],
            'remera-espalda' => [
                'label' => 'Remera espalda',
                'file' => 'remera-espalda.jpg',
                'areas' => [[820, 200, 820, 980]],
            ],
            'remera-v' => [
                'label' => 'Remera cuello V',
                'file' => 'remera-v.jpg',
                'areas' => [[1580, 680, 1100, 1250]],
            ],
            'taza-par' => [
                'label' => 'Dos tazas',
                'file' => 'taza-par.jpg',
                'areas' => [[780, 1280, 1180, 1450], [2580, 1680, 1100, 1400]],
            ],
            'taza-sola' => [
                'label' => 'Taza sola',
                'file' => 'taza-sola.jpg',
                'areas' => [[1180, 1200, 1000, 1100]],
            ],
            'taza-corazon' => [
                'label' => 'Taza asa corazón',
                'file' => 'taza-corazon.jpg',
                'areas' => [[820, 1280, 1150, 1450], [2580, 1680, 1080, 1400]],
            ],
            'gorra-trucker' => [
                'label' => 'Gorra trucker',
                'file' => 'gorra-trucker.jpg',
                'areas' => [[228, 158, 258, 148]],
            ],
            'medalla' => [
                'label' => 'Medalla',
                'file' => 'medalla-azul.jpg',
                'areas' => [[156, 282, 380, 380]],
                'round' => true,
                'ribbon' => true,
            ],
            'set-matero' => [
                'label' => 'Set matero',
                'file' => 'set-matero.jpg',
                'areas' => [[75, 369, 298, 309], [459, 480, 368, 209], [895, 372, 310, 319]],
            ],
            'jarro-termico' => [
                'label' => 'Jarro térmico',
                'file' => 'jarro-termico.jpg',
                'areas' => [[128, 263, 190, 218], [439, 257, 192, 231]],
            ],
            'cartuchera' => [
                'label' => 'Cartuchera',
                'file' => 'cartuchera.jpg',
                'areas' => [[101, 160, 1688, 772]],
            ],
        ];
    }

    public static function ribbons()
    {
        return [
            'amarillo' => ['label' => 'Amarillo', 'css' => '#f2c200'],
            'celeste' => ['label' => 'Celeste', 'css' => '#6eb5e0'],
            'azul' => ['label' => 'Azul', 'css' => '#1e4b8f'],
            'rojo' => ['label' => 'Rojo', 'css' => '#d0122d'],
            'rosa' => ['label' => 'Rosa', 'css' => '#e56b93'],
            'naranja' => ['label' => 'Naranja', 'css' => '#f26b1f'],
            'blanco' => ['label' => 'Blanco', 'css' => '#f4f4f4'],
            'verde' => ['label' => 'Verde', 'css' => '#1f8a3b'],
            'argentina' => ['label' => 'Argentina', 'css' => 'linear-gradient(90deg,#74acdf 0 33%,#ffffff 33% 66%,#74acdf 66% 100%)'],
            'paraguay' => ['label' => 'Paraguay', 'css' => 'linear-gradient(90deg,#d52b1e 0 33%,#ffffff 33% 66%,#0038a8 66% 100%)'],
            'chile' => ['label' => 'Chile', 'css' => 'linear-gradient(90deg,#0039a6 0 33%,#ffffff 33% 66%,#d52b1e 66% 100%)'],
            'bolivia' => ['label' => 'Bolivia', 'css' => 'linear-gradient(90deg,#d52b1e 0 33%,#f9e300 33% 66%,#007934 66% 100%)'],
            'ecuador' => ['label' => 'Ecuador', 'css' => 'linear-gradient(90deg,#ffd100 0 50%,#034ea2 50% 75%,#ed1c24 75% 100%)'],
            'venezuela' => ['label' => 'Venezuela', 'css' => 'linear-gradient(90deg,#fce300 0 33%,#0033a0 33% 66%,#cf142b 66% 100%)'],
            'colombia' => ['label' => 'Colombia', 'css' => 'linear-gradient(90deg,#fcd116 0 50%,#003893 50% 75%,#ce1126 75% 100%)'],
            'peru' => ['label' => 'Perú', 'css' => 'linear-gradient(90deg,#d91023 0 33%,#ffffff 33% 66%,#d91023 66% 100%)'],
            'italia' => ['label' => 'Italia', 'css' => 'linear-gradient(90deg,#009246 0 33%,#ffffff 33% 66%,#ce2b37 66% 100%)'],
            'francia' => ['label' => 'Francia', 'css' => 'linear-gradient(90deg,#0055a4 0 33%,#ffffff 33% 66%,#ef4135 66% 100%)'],
            'espana' => ['label' => 'España', 'css' => 'linear-gradient(90deg,#aa151b 0 25%,#f1bf00 25% 75%,#aa151b 75% 100%)'],
        ];
    }

    public static function place($designPath, $id, $ribbon = 'azul')
    {
        $options = self::options();
        if (!isset($options[$id])) {
            throw new UserException('Elegí una plantilla.');
        }
        $file = $options[$id]['file'];
        if (!empty($options[$id]['ribbon'])) {
            if (!isset(self::ribbons()[$ribbon])) {
                throw new UserException('Elegí un color de cinta.');
            }
            $file = 'medalla-' . $ribbon . '.jpg';
        }
        $root = dirname(clh_tmp_root()) . '/mockups/' . $file;
        if (!is_file($root)) {
            throw new UserException('Falta la plantilla.');
        }
        $base = @imagecreatefromjpeg($root);
        if (!$base) {
            throw new UserException('No pude abrir la plantilla.');
        }
        $design = Converter::load($designPath);
        imagealphablending($base, true);
        $round = !empty($options[$id]['round']);
        foreach ($options[$id]['areas'] as $area) {
            self::stamp($base, $design, $area[0], $area[1], $area[2], $area[3], $round);
        }
        imagedestroy($design);
        return $base;
    }

    private static function stamp($base, $design, $x, $y, $w, $h, $round = false)
    {
        $srcW = imagesx($design);
        $srcH = imagesy($design);
        if ($srcW < 1 || $srcH < 1 || $w < 1 || $h < 1) {
            return;
        }
        $scale = $round ? max($w / $srcW, $h / $srcH) : min($w / $srcW, $h / $srcH);
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));
        $dstX = (int) floor(($w - $dstW) / 2);
        $dstY = (int) floor(($h - $dstH) / 2);

        $plate = imagecreatetruecolor($w, $h);
        imagealphablending($plate, false);
        imagesavealpha($plate, true);
        $clear = imagecolorallocatealpha($plate, 0, 0, 0, 127);
        imagefilledrectangle($plate, 0, 0, $w, $h, $clear);
        imagealphablending($plate, true);
        imagecopyresampled($plate, $design, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);
        if ($round) {
            imagealphablending($plate, false);
            $cx = ($w - 1) / 2;
            $cy = ($h - 1) / 2;
            $rx = $w / 2;
            $ry = $h / 2;
            for ($yy = 0; $yy < $h; $yy++) {
                for ($xx = 0; $xx < $w; $xx++) {
                    $nx = ($xx - $cx) / $rx;
                    $ny = ($yy - $cy) / $ry;
                    if (($nx * $nx) + ($ny * $ny) > 1) {
                        imagesetpixel($plate, $xx, $yy, $clear);
                    }
                }
            }
            imagealphablending($plate, true);
        }
        imagecopy($base, $plate, $x, $y, 0, 0, $w, $h);
        imagedestroy($plate);
    }
}
