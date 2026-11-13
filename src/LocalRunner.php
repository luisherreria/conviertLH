<?php

class LocalRunner
{
    public $notice = '';

    public function removeBackground($path, $keep)
    {
        return $this->run('remove_bg', $path, 4, $keep);
    }

    public function upscale($path, $scale)
    {
        return $this->run('upscale', $path, (int) $scale);
    }

    public static function pythonPath()
    {
        return CLH_ROOT . '/tools/ai/venv/Scripts/python.exe';
    }

    public static function isReady()
    {
        return is_file(self::pythonPath()) && is_file(CLH_ROOT . '/tools/ai/run.py');
    }

    private function run($task, $input, $scale, $keep = 80)
    {
        $python = self::pythonPath();
        $script = CLH_ROOT . '/tools/ai/run.py';
        if (!is_file($python) || !is_file($script)) {
            throw new UserException('Falta el entorno de IA local. Convertir formatos sigue disponible.');
        }
        if (!is_file($input)) {
            throw new UserException('No se pudo leer la imagen.');
        }

        $output = $input . '.ia.png';
        $command = [
            $python,
            $script,
            '--task',
            $task,
            '--input',
            $input,
            '--output',
            $output,
            '--scale',
            (string) $scale,
            '--keep',
            (string) (int) $keep,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $environment = [
            'HF_HOME' => CLH_ROOT . '/tools/ai/cache',
            'HF_HUB_DISABLE_TELEMETRY' => '1',
            'HF_HUB_DISABLE_PROGRESS_BARS' => '1',
            'HF_HUB_DISABLE_SYMLINKS_WARNING' => '1',
            'TRANSFORMERS_VERBOSITY' => 'error',
            'PYTHONIOENCODING' => 'utf-8',
            'PYTHONUNBUFFERED' => '1',
            'SystemRoot' => (string) getenv('SystemRoot'),
            'PATH' => (string) getenv('PATH'),
            'TEMP' => (string) getenv('TEMP'),
            'TMP' => (string) getenv('TMP'),
            'USERPROFILE' => (string) getenv('USERPROFILE'),
            'APPDATA' => (string) getenv('APPDATA'),
            'LOCALAPPDATA' => (string) getenv('LOCALAPPDATA'),
        ];

        $process = proc_open($command, $descriptors, $pipes, CLH_ROOT, $environment);
        if (!is_resource($process)) {
            throw new UserException('No se pudo iniciar la IA local.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = time() + 600;
        while (true) {
            $status = proc_get_status($process);
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            if (!$status['running']) {
                break;
            }
            if (time() > $deadline) {
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                @unlink($output);
                throw new UserException('La IA tardó demasiado. Probá con una imagen más chica.');
            }
            usleep(200000);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $payload = $this->lastJson($stdout);
        $this->notice = '';
        if (!$payload || empty($payload['ok'])) {
            @unlink($output);
            $message = '';
            if (is_array($payload) && !empty($payload['error'])) {
                $message = (string) $payload['error'];
            } elseif (trim($stderr) !== '') {
                $message = trim($stderr);
            }
            $message = preg_replace('/\s+/', ' ', $message);
            if (function_exists('mb_substr') && mb_strlen($message) > 280) {
                $message = mb_substr($message, 0, 280);
            }
            if ($message === '') {
                $message = 'La IA local no pudo procesar la imagen.';
            }
            throw new UserException($message);
        }
        if (!empty($payload['notice'])) {
            $this->notice = (string) $payload['notice'];
        }

        $binary = file_get_contents($output);
        @unlink($output);
        if ($binary === false || $binary === '') {
            throw new UserException('La IA no devolvió una imagen.');
        }
        return $binary;
    }

    private function lastJson($stdout)
    {
        $lines = preg_split("/\r\n|\n|\r/", (string) $stdout);
        if (!$lines) {
            return null;
        }
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if ($line === '' || $line[0] !== '{') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }
}
