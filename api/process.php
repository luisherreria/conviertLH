<?php

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/Formats.php';
require_once dirname(__DIR__) . '/src/Converter.php';
require_once dirname(__DIR__) . '/src/LocalRunner.php';
require_once dirname(__DIR__) . '/src/ReplicateClient.php';
require_once dirname(__DIR__) . '/src/Processor.php';

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

ini_set('display_errors', '0');
ini_set('memory_limit', '512M');
set_time_limit(0);
ignore_user_abort(true);

ob_start();

function clh_respond($code, array $payload)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    clh_respond(405, ['ok' => false, 'error' => 'Método no permitido.']);
}

$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
    clh_respond(400, ['ok' => false, 'error' => 'El envío supera el tamaño permitido por el servidor.']);
}

try {
    $processor = new Processor(clh_config());
    clh_respond(200, $processor->handle($_POST, $_FILES));
} catch (UserException $e) {
    clh_respond(400, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    $message = str_replace(CLH_ROOT, '', $e->getMessage());
    if (mb_strlen($message) > 240) {
        $message = mb_substr($message, 0, 240);
    }
    clh_respond(500, ['ok' => false, 'error' => 'No se pudo completar el proceso. ' . $message]);
}
