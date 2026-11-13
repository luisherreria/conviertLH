<?php

class ReplicateClient
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function removeBackground($binary, $mime, $keep = 80)
    {
        $keep = max(0, min(100, (int) $keep));
        if ($keep < 34) {
            $variant = 'general-hr';
        } elseif ($keep < 67) {
            $variant = 'matting';
        } else {
            $variant = 'matting-hr';
        }
        $offset = (int) round(($keep - 50) / 50 * 8);

        return $this->run('remove_bg', $binary, $mime, [
            'variant' => $variant,
            'mask_offset' => $offset,
        ]);
    }

    public function upscale($binary, $mime, $scale)
    {
        $model = $this->config['models']['upscale'] ?? [];
        $field = trim((string) ($model['scale_field'] ?? 'scale'));
        $extra = [];
        if ($field !== '') {
            $extra[$field] = (int) $scale;
        }
        return $this->run('upscale', $binary, $mime, $extra);
    }

    private function run($modelKey, $binary, $mime, array $extra)
    {
        if (!function_exists('curl_init')) {
            throw new UserException('Este PHP no tiene cURL, hace falta para llamar a Replicate.');
        }

        $models = $this->config['models'] ?? [];
        if (empty($models[$modelKey]) || !is_array($models[$modelKey])) {
            throw new UserException('Falta la configuración del modelo de IA.');
        }

        $model = $models[$modelKey];
        $owner = trim((string) ($model['owner'] ?? ''));
        $name = trim((string) ($model['name'] ?? ''));
        $imageField = trim((string) ($model['image_field'] ?? 'image'));
        if ($owner === '' || $name === '' || $imageField === '') {
            throw new UserException('El modelo de IA está incompleto en config.php.');
        }
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $owner) || !preg_match('/^[A-Za-z0-9_.-]+$/', $name)) {
            throw new UserException('El nombre del modelo en config.php no es válido.');
        }

        $input = [];
        if (!empty($model['extra']) && is_array($model['extra'])) {
            $input = $model['extra'];
        }
        $input[$imageField] = $this->upload($binary, $mime);
        foreach ($extra as $key => $value) {
            $input[$key] = $value;
        }

        $created = $this->createPrediction($owner, $name, $input);
        try {
            $done = $this->wait($created);
        } catch (UserException $error) {
            if (strpos($error->getMessage(), 'upload output') === false) {
                throw $error;
            }
            $created = $this->createPrediction($owner, $name, $input);
            $done = $this->wait($created);
        }
        $fileUrl = $this->outputUrl($done['output'] ?? null);
        return $this->download($fileUrl);
    }

    private function token()
    {
        $token = trim((string) ($this->config['replicate_token'] ?? ''));
        if ($token === '') {
            throw new UserException('Falta el token de Replicate. Copiá config.example.php como config.php y pegá el token. Convertir formatos no lo necesita.');
        }
        return $token;
    }

    private function upload($binary, $mime)
    {
        $extension = $this->extensionForMime($mime);
        $tmp = tempnam(sys_get_temp_dir(), 'clhup');
        if ($tmp === false) {
            throw new UserException('No se pudo preparar la imagen para la IA.');
        }

        try {
            if (file_put_contents($tmp, $binary) === false) {
                throw new UserException('No se pudo preparar la imagen para la IA.');
            }

            $file = new CURLFile($tmp, $mime, 'imagen.' . $extension);
            $ch = curl_init('https://api.replicate.com/v1/files');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->token(),
                ],
                CURLOPT_POSTFIELDS => ['content' => $file],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 20,
            ]);
            $this->lockHttps($ch);
            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno) {
                throw new UserException('No se pudo subir la imagen a Replicate. ' . $this->clip($error));
            }

            $data = json_decode((string) $raw, true);
            if ($code >= 400 || !is_array($data)) {
                throw new UserException($this->apiError($code, is_array($data) ? $data : [], 'No se pudo subir la imagen a Replicate.'));
            }

            $url = '';
            if (isset($data['urls']['get']) && is_string($data['urls']['get'])) {
                $url = $data['urls']['get'];
            } elseif (isset($data['url']) && is_string($data['url'])) {
                $url = $data['url'];
            }
            $this->assertReplicateUrl($url);
            return $url;
        } finally {
            @unlink($tmp);
        }
    }

    private function createPrediction($owner, $name, array $input)
    {
        $version = $this->latestVersion($owner, $name);
        $body = ['version' => $version, 'input' => $input];
        try {
            return $this->request('POST', 'https://api.replicate.com/v1/predictions', $body, 75);
        } catch (UserException $error) {
            if (stripos($error->getMessage(), 'throttled') === false && stripos($error->getMessage(), 'rate limit') === false) {
                throw $error;
            }
            sleep(12);
            return $this->request('POST', 'https://api.replicate.com/v1/predictions', $body, 75);
        }
    }

    private function latestVersion($owner, $name)
    {
        $data = $this->request(
            'GET',
            'https://api.replicate.com/v1/models/' . rawurlencode($owner) . '/' . rawurlencode($name),
            null,
            30
        );
        $id = '';
        if (isset($data['latest_version']['id']) && is_string($data['latest_version']['id'])) {
            $id = $data['latest_version']['id'];
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $id)) {
            throw new UserException('No se pudo leer la versión del modelo de Replicate.');
        }
        return $id;
    }

    private function wait(array $prediction)
    {
        $status = isset($prediction['status']) ? (string) $prediction['status'] : '';
        $id = isset($prediction['id']) ? (string) $prediction['id'] : '';
        $deadline = time() + max(15, (int) $this->config['poll_timeout_seconds']);

        while ($status !== 'succeeded') {
            if ($status === 'failed' || $status === 'canceled') {
                $detail = isset($prediction['error']) && is_string($prediction['error'])
                    ? $prediction['error']
                    : 'Sin detalle.';
                throw new UserException('La IA no pudo procesar la imagen. ' . $this->clip($detail));
            }
            if ($id === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $id) || time() >= $deadline) {
                throw new UserException('La IA tardó demasiado. Probá con una imagen más chica.');
            }
            sleep(2);
            $prediction = $this->request(
                'GET',
                'https://api.replicate.com/v1/predictions/' . rawurlencode($id),
                null,
                30
            );
            $status = isset($prediction['status']) ? (string) $prediction['status'] : '';
        }

        return $prediction;
    }

    private function request($method, $url, $body, $timeout)
    {
        $headers = [
            'Authorization: Bearer ' . $this->token(),
            'Accept: application/json',
        ];
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Prefer: wait=60';
        }

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $options);
        $this->lockHttps($ch);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new UserException('No se pudo contactar a Replicate. ' . $this->clip($error));
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            throw new UserException('Replicate respondió de una forma inesperada.');
        }
        if ($code >= 400) {
            throw new UserException($this->apiError($code, $data, 'Replicate rechazó la imagen.'));
        }
        return $data;
    }

    private function download($url)
    {
        $this->assertReplicateUrl($url);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token(),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 20,
        ]);
        $this->lockHttps($ch);
        $binary = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($errno) {
            throw new UserException('No se pudo descargar el resultado de la IA. ' . $this->clip($error));
        }
        $this->assertReplicateUrl($finalUrl);
        if (!is_string($binary) || !$this->looksLikeImage($binary)) {
            throw new UserException('La IA no devolvió una imagen.');
        }
        return $binary;
    }

    private function outputUrl($output)
    {
        if (is_string($output) && $output !== '') {
            return $output;
        }
        if (is_array($output)) {
            foreach ($output as $item) {
                if (is_string($item) && $item !== '') {
                    return $item;
                }
                if (is_array($item) && isset($item['url']) && is_string($item['url'])) {
                    return $item['url'];
                }
            }
        }
        throw new UserException('La IA no devolvió una imagen.');
    }

    private function assertReplicateUrl($url)
    {
        $parts = parse_url((string) $url);
        if (!$parts || empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https' || empty($parts['host'])) {
            throw new UserException('La respuesta de la IA no trajo un archivo válido.');
        }
        $host = strtolower($parts['host']);
        $allowed = $host === 'replicate.delivery'
            || $host === 'replicate.com'
            || $this->endsWith($host, '.replicate.delivery')
            || $this->endsWith($host, '.replicate.com');
        if (!$allowed) {
            throw new UserException('La IA respondió con un origen que esta app no descarga.');
        }
    }

    private function looksLikeImage($binary)
    {
        if (strlen($binary) < 12) {
            return false;
        }
        if (substr($binary, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return true;
        }
        if (substr($binary, 0, 2) === "\xFF\xD8") {
            return true;
        }
        if (substr($binary, 0, 4) === 'RIFF' && substr($binary, 8, 4) === 'WEBP') {
            return true;
        }
        if (substr($binary, 0, 6) === 'GIF87a' || substr($binary, 0, 6) === 'GIF89a') {
            return true;
        }
        return false;
    }

    private function apiError($code, array $data, $fallback)
    {
        if ($code === 401) {
            return 'El token de Replicate fue rechazado. Revisá config.php.';
        }
        if ($code === 402 || $code === 429) {
            $detail = $this->errorDetail($data);
            if ($detail === '') {
                return 'Replicate no aceptó el pedido (crédito o límite de uso).';
            }
            return 'Replicate: ' . $detail;
        }
        $detail = '';
        if (isset($data['detail'])) {
            $detail = is_string($data['detail']) ? $data['detail'] : json_encode($data['detail']);
        } elseif (isset($data['error']) && is_string($data['error'])) {
            $detail = $data['error'];
        } elseif (isset($data['title']) && is_string($data['title'])) {
            $detail = $data['title'];
        }
        $detail = $this->clip($detail);
        if ($detail === '') {
            return $fallback;
        }
        return 'Replicate: ' . $detail;
    }

    private function errorDetail(array $data)
    {
        if (isset($data['detail'])) {
            $detail = is_string($data['detail']) ? $data['detail'] : json_encode($data['detail']);
        } elseif (isset($data['error']) && is_string($data['error'])) {
            $detail = $data['error'];
        } elseif (isset($data['title']) && is_string($data['title'])) {
            $detail = $data['title'];
        } else {
            $detail = '';
        }
        return $this->clip($detail);
    }

    private function extensionForMime($mime)
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

    private function lockHttps($ch)
    {
        if (defined('CURLPROTO_HTTPS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            if (defined('CURLPROTO_HTTPS')) {
                curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
            }
        }
    }

    private function endsWith($value, $suffix)
    {
        $len = strlen($suffix);
        if ($len === 0 || strlen($value) < $len) {
            return false;
        }
        return substr($value, -$len) === $suffix;
    }

    private function clip($text)
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));
        if (mb_strlen($text) > 240) {
            $text = mb_substr($text, 0, 240) . '…';
        }
        return $text;
    }
}
