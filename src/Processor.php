<?php

class Processor
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function handle(array $post, array $files)
    {
        $this->sweep();
        $job = $this->makeJobDir();
        try {
            return $this->run($post, $files, $job);
        } finally {
            $this->deleteTree($job);
        }
    }

    private function run(array $post, array $files, $job)
    {
        $tool = isset($post['tool']) ? (string) $post['tool'] : '';
        if (!in_array($tool, ['remove_bg', 'upscale', 'convert', 'crop', 'flip'], true)) {
            throw new UserException('Elegí una herramienta: quitar fondo, escalar, convertir, recortar o espejar.');
        }

        $scale = isset($post['scale']) ? (int) $post['scale'] : 2;
        if ($scale !== 2 && $scale !== 4) {
            throw new UserException('La escala tiene que ser 2× o 4×.');
        }

        $format = isset($post['format']) ? strtolower((string) $post['format']) : 'png';
        $catalog = Formats::all();
        if ($tool === 'convert') {
            if (!isset($catalog[$format])) {
                throw new UserException('Formato desconocido.');
            }
            if (!Formats::canWrite($format)) {
                throw new UserException(Formats::reason($format));
            }
        }

        $quality = isset($post['quality']) ? (int) $post['quality'] : 90;
        if ($quality < 1 || $quality > 100) {
            throw new UserException('La calidad tiene que estar entre 1 y 100.');
        }

        $background = isset($post['background']) ? (string) $post['background'] : 'transparent';
        if ($background !== 'transparent' && $background !== 'color') {
            throw new UserException('Elegí fondo transparente o un color.');
        }

        $color = Converter::normalizeHex(isset($post['background_color']) ? $post['background_color'] : '#ffffff');
        $crop = isset($post['crop']) && (string) $post['crop'] === '1';
        $dpi = isset($post['dpi']) ? (int) $post['dpi'] : 0;
        if (!in_array($dpi, [0, 72, 96, 150, 300, 600], true)) {
            throw new UserException('El DPI tiene que ser 72, 96, 150, 300, 600, o ninguno.');
        }

        $keep = isset($post['keep']) ? (int) $post['keep'] : 80;
        if ($keep < 0 || $keep > 100) {
            throw new UserException('El nivel de conservación tiene que estar entre 0 y 100.');
        }

        $flip = isset($post['flip']) ? (string) $post['flip'] : 'horizontal';
        if (!in_array($flip, ['horizontal', 'vertical', 'both'], true)) {
            throw new UserException('Elegí espejo horizontal, vertical o ambos.');
        }

        $uploads = $this->normalizeFiles($files);
        $maxFiles = max(1, (int) $this->config['max_files']);
        if (count($uploads) > $maxFiles) {
            throw new UserException('Podés subir hasta ' . $maxFiles . ' imágenes por vez.');
        }

        if (!in_array($tool, ['convert', 'crop', 'flip'], true) && !$this->usesReplicate() && !LocalRunner::isReady()) {
            throw new UserException('Falta el token de Replicate. Convertir formatos sigue disponible.');
        }

        $notices = [];
        if ($tool === 'convert' && $format === 'gif') {
            $notices[] = 'El GIF no conserva animación: se usa un solo cuadro.';
        }
        if ($dpi > 0 && $tool === 'convert' && $format !== 'png' && $format !== 'jpg') {
            $notices[] = 'El DPI se guarda solo en PNG y JPG. ' . strtoupper($format) . ' se entrega sin esa marca.';
        }

        $results = [];
        $errors = [];
        foreach ($uploads as $index => $upload) {
            try {
                $saved = $this->storeUpload($upload, $job);
                $results[] = $this->processOne(
                    $tool,
                    $saved['path'],
                    $saved['mime'],
                    $upload['name'],
                    $index,
                    $scale,
                    $format,
                    $quality,
                    $background,
                    $color,
                    $crop,
                    $dpi,
                    $keep,
                    $flip,
                    $notices
                );
            } catch (UserException $e) {
                $errors[] = $this->labelName($upload['name']) . ': ' . $e->getMessage();
            }
        }

        if (!$results) {
            $message = $errors ? implode(' ', $errors) : 'No se pudo procesar ninguna imagen.';
            throw new UserException($message);
        }
        if ($errors) {
            $notices[] = 'Algunas imágenes no se procesaron: ' . implode(' ', $errors);
        }

        $used = [];
        $public = [];
        foreach ($results as $result) {
            $name = $this->uniqueName($result['name'], $used);
            $result['name'] = $name;
            $public[] = $result;
        }

        $zip = null;
        if (count($public) > 1) {
            $zip = $this->buildZip($public, $job);
        }

        $filesOut = [];
        foreach ($public as $result) {
            $filesOut[] = [
                'name' => $result['name'],
                'mime' => $result['mime'],
                'base64' => base64_encode($result['binary']),
                'width' => $result['width'],
                'height' => $result['height'],
                'bytes' => strlen($result['binary']),
                'source_index' => $result['source_index'],
            ];
        }

        return [
            'ok' => true,
            'files' => $filesOut,
            'zip' => $zip,
            'notices' => array_values(array_unique($notices)),
        ];
    }

    private function formatFromMime($mime)
    {
        if ($mime === 'image/jpeg') {
            return 'jpg';
        }
        if ($mime === 'image/webp') {
            return 'webp';
        }
        if ($mime === 'image/gif') {
            return 'gif';
        }
        if ($mime === 'image/bmp' || $mime === 'image/x-ms-bmp') {
            return 'bmp';
        }
        return 'png';
    }

    private function usesReplicate()
    {
        return trim((string) ($this->config['replicate_token'] ?? '')) !== '';
    }

    private function processOne($tool, $path, $mime, $originalName, $index, $scale, $format, $quality, $background, $color, $crop, $dpi, $keep, $flip, array &$notices)
    {
        if ($tool === 'convert' || $tool === 'crop' || $tool === 'flip') {
            $outputFormat = $format;
            $doCrop = $crop;
            $qualityOut = $quality;
            if ($tool === 'crop' || $tool === 'flip') {
                $outputFormat = $this->formatFromMime($mime);
                if (!Formats::canWrite($outputFormat)) {
                    $notices[] = 'Este formato se entrega en PNG.';
                    $outputFormat = 'png';
                }
                $qualityOut = 92;
                $doCrop = $tool === 'crop';
            }
            $image = Converter::load($path);
            if ($tool === 'flip') {
                $image = Converter::flip($image, $flip);
            }
            return $this->finishLocal(
                $image,
                $outputFormat,
                $qualityOut,
                false,
                $color,
                $doCrop,
                $dpi,
                $originalName,
                $index,
                $notices
            );
        }

        if ($this->usesReplicate()) {
            $binary = file_get_contents($path);
            if ($binary === false || $binary === '') {
                throw new UserException('No se pudo leer la imagen.');
            }
            $client = new ReplicateClient($this->config);
            if ($tool === 'remove_bg') {
                $output = $client->removeBackground($binary, $mime, $keep);
                $outputFormat = 'png';
                $flatten = $background === 'color';
            } else {
                $info = @getimagesize($path);
                if ($info && (($info[0] * $scale) > 8000 || ($info[1] * $scale) > 8000)) {
                    throw new UserException('Quedaría por encima de 8000 px de lado al escalar ' . $scale . '×. Usá 2× o una imagen más chica.');
                }
                $output = $client->upscale($binary, $mime, $scale);
                $outputFormat = Converter::sniff($output);
                $flatten = false;
                if ($dpi > 0 && $outputFormat !== 'png' && $outputFormat !== 'jpg') {
                    $notices[] = 'El DPI se guarda solo en PNG y JPG. El resultado de la IA se entrega sin esa marca.';
                }
            }
        } else {
            $runner = new LocalRunner();
            if ($tool === 'remove_bg') {
                $output = $runner->removeBackground($path, $keep);
                if ($runner->notice !== '') {
                    $notices[] = $runner->notice;
                }
                $outputFormat = 'png';
                $flatten = $background === 'color';
            } else {
                $info = @getimagesize($path);
                if ($info && (($info[0] * $scale) > 8000 || ($info[1] * $scale) > 8000)) {
                    throw new UserException('Quedaría por encima de 8000 px de lado al escalar ' . $scale . '×. Usá 2× o una imagen más chica.');
                }
                $output = $runner->upscale($path, $scale);
                $outputFormat = Converter::sniff($output);
                $flatten = false;
                if ($dpi > 0 && $outputFormat !== 'png' && $outputFormat !== 'jpg') {
                    $notices[] = 'El DPI se guarda solo en PNG y JPG. El resultado de la IA se entrega sin esa marca.';
                }
            }
        }

        if (!$crop && !$flatten) {
            $binaryOut = $output;
            $formatOut = $outputFormat;
            if ($dpi > 0 && ($formatOut === 'png' || $formatOut === 'jpg')) {
                $binaryOut = Converter::applyDpi($binaryOut, $formatOut, $dpi);
            }
            $size = Converter::dimensions($binaryOut);
            $mimeOut = Formats::all()[$formatOut]['mime'];
            return [
                'name' => $this->outputName($originalName, $formatOut),
                'mime' => $mimeOut,
                'binary' => $binaryOut,
                'width' => $size[0],
                'height' => $size[1],
                'source_index' => $index,
            ];
        }

        $size = Converter::dimensions($output);
        if ($size[0] > 0 && $size[1] > 0 && ($size[0] * $size[1]) > 36000000) {
            throw new UserException('El resultado es demasiado grande para recortar en este servidor.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'clhai');
        if ($tmp === false || file_put_contents($tmp, $output) === false) {
            throw new UserException('No se pudo leer el resultado de la IA.');
        }
        try {
            $image = Converter::load($tmp);
        } finally {
            @unlink($tmp);
        }

        return $this->finishLocal(
            $image,
            'png',
            95,
            $flatten,
            $color,
            $crop,
            $dpi,
            $originalName,
            $index,
            $notices
        );
    }

    private function finishLocal($image, $format, $quality, $flatten, $color, $crop, $dpi, $originalName, $index, array &$notices)
    {
        try {
            if ($crop) {
                $cropped = Converter::cropToContent($image);
                if ($cropped['notice'] !== '') {
                    $notices[] = $cropped['notice'];
                }
                if ($cropped['image'] !== $image) {
                    imagedestroy($image);
                    $image = $cropped['image'];
                }
            }

            if ($flatten) {
                $flat = Converter::flatten($image, $color);
                imagedestroy($image);
                $image = $flat;
            }

            $catalog = Formats::all();
            $binary = Converter::encode($image, $format, $quality, $color);
            if ($dpi > 0 && ($format === 'png' || $format === 'jpg')) {
                $binary = Converter::applyDpi($binary, $format, $dpi);
            }
            $size = Converter::dimensions($binary);
            return [
                'name' => $this->outputName($originalName, $format),
                'mime' => $catalog[$format]['mime'],
                'binary' => $binary,
                'width' => $size[0],
                'height' => $size[1],
                'source_index' => $index,
            ];
        } finally {
            if (is_resource($image)) {
                imagedestroy($image);
            }
        }
    }

    private function normalizeFiles(array $files)
    {
        if (!isset($files['files']) || !is_array($files['files'])) {
            throw new UserException('Subí al menos una imagen.');
        }

        $bucket = $files['files'];
        if (!isset($bucket['name'])) {
            throw new UserException('Subí al menos una imagen.');
        }
        if (!is_array($bucket['name'])) {
            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
                $bucket[$key] = [$bucket[$key]];
            }
        }

        $out = [];
        $count = count($bucket['name']);
        for ($i = 0; $i < $count; $i++) {
            $error = (int) $bucket['error'][$i];
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => (string) $bucket['name'][$i],
                'type' => (string) $bucket['type'][$i],
                'tmp_name' => (string) $bucket['tmp_name'][$i],
                'error' => $error,
                'size' => (int) $bucket['size'][$i],
            ];
        }

        if (!$out) {
            throw new UserException('Subí al menos una imagen.');
        }
        return $out;
    }

    private function storeUpload(array $upload, $job)
    {
        $label = $this->labelName($upload['name']);
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            throw new UserException('"' . $label . '" ' . $this->uploadError($upload['error']) . '.');
        }

        $maxBytes = max(1024, (int) $this->config['max_upload_bytes']);
        if ($upload['size'] <= 0) {
            throw new UserException('"' . $label . '" está vacío.');
        }
        if ($upload['size'] > $maxBytes) {
            throw new UserException('"' . $label . '" pesa más de ' . $this->mb($maxBytes) . ' MB.');
        }
        if (!is_uploaded_file($upload['tmp_name'])) {
            throw new UserException('"' . $label . '" no llegó como una subida válida.');
        }

        $info = @getimagesize($upload['tmp_name']);
        $mimes = Formats::inputMimes();
        if (!$info || empty($info['mime']) || !isset($mimes[$info['mime']])) {
            throw new UserException('"' . $label . '" no es una imagen PNG, JPG, WEBP, GIF o BMP.');
        }

        $pixels = (int) $info[0] * (int) $info[1];
        $maxPixels = max(1, (int) $this->config['max_pixels']);
        if ($info[0] <= 0 || $info[1] <= 0 || $pixels > $maxPixels) {
            throw new UserException('"' . $label . '" tiene demasiados píxeles para este servidor.');
        }

        $dest = $job . '/' . bin2hex(random_bytes(8));
        if (!move_uploaded_file($upload['tmp_name'], $dest)) {
            throw new UserException('No se pudo guardar "' . $label . '" para procesarla.');
        }

        return [
            'path' => $dest,
            'mime' => $info['mime'],
        ];
    }

    private function buildZip(array $results, $job)
    {
        if (!class_exists('ZipArchive')) {
            throw new UserException('Este PHP no puede armar ZIP.');
        }

        $path = $job . '/resultado.zip';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE) !== true) {
            throw new UserException('No se pudo armar el ZIP.');
        }
        foreach ($results as $result) {
            $zip->addFromString($result['name'], $result['binary']);
        }
        $zip->close();

        $binary = file_get_contents($path);
        @unlink($path);
        if ($binary === false || $binary === '') {
            throw new UserException('El ZIP quedó vacío.');
        }

        return [
            'name' => 'convertlh-resultado.zip',
            'mime' => 'application/zip',
            'base64' => base64_encode($binary),
        ];
    }

    private function outputName($original, $format)
    {
        $stem = pathinfo((string) $original, PATHINFO_FILENAME);
        $stem = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $stem);
        $stem = trim((string) $stem, '-');
        if ($stem === '') {
            $stem = 'imagen';
        }
        if (strlen($stem) > 80) {
            $stem = substr($stem, 0, 80);
        }
        return $stem . '.' . $format;
    }

    private function uniqueName($name, array &$used)
    {
        $candidate = $name;
        $stem = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $n = 2;
        while (isset($used[$candidate])) {
            $candidate = $stem . '-' . $n . ($ext !== '' ? '.' . $ext : '');
            $n++;
        }
        $used[$candidate] = true;
        return $candidate;
    }

    private function labelName($name)
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', (string) $name);
        $name = trim($name);
        if ($name === '') {
            $name = 'imagen';
        }
        if (mb_strlen($name) > 80) {
            $name = mb_substr($name, 0, 80);
        }
        return $name;
    }

    private function uploadError($code)
    {
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return 'supera el tamaño permitido por el servidor';
        }
        if ($code === UPLOAD_ERR_PARTIAL) {
            return 'llegó incompleto';
        }
        if ($code === UPLOAD_ERR_NO_TMP_DIR || $code === UPLOAD_ERR_CANT_WRITE) {
            return 'no se pudo guardar en el servidor';
        }
        return 'no se pudo subir';
    }

    private function mb($bytes)
    {
        $mb = $bytes / (1024 * 1024);
        if (abs($mb - round($mb)) < 0.05) {
            return (string) (int) round($mb);
        }
        return number_format($mb, 1, ',', '');
    }

    private function makeJobDir()
    {
        $dir = clh_tmp_root() . '/job-' . bin2hex(random_bytes(8));
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new UserException('No se pudo crear la carpeta temporal.');
        }
        return $dir;
    }

    private function sweep()
    {
        $root = clh_tmp_root();
        $jobs = glob($root . '/job-*');
        if (!$jobs) {
            return;
        }
        foreach ($jobs as $dir) {
            if (is_dir($dir) && filemtime($dir) !== false && filemtime($dir) < time() - 3600) {
                $this->deleteTree($dir);
            }
        }
    }

    private function deleteTree($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $root = realpath(clh_tmp_root());
        $target = realpath($dir);
        if ($root === false || $target === false) {
            return;
        }
        $rootNorm = rtrim(strtolower($root), '\\/');
        $targetNorm = strtolower($target);
        if ($targetNorm === $rootNorm) {
            return;
        }
        $prefix = $rootNorm . DIRECTORY_SEPARATOR;
        $prefixAlt = $rootNorm . '/';
        if (strpos($targetNorm, $prefix) !== 0 && strpos($targetNorm, $prefixAlt) !== 0) {
            return;
        }

        $items = scandir($target);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $target . DIRECTORY_SEPARATOR . $item;
            if (is_link($path)) {
                @unlink($path);
                continue;
            }
            if (is_dir($path)) {
                $this->deleteTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($target);
    }
}
