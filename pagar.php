<?php

require_once __DIR__ . '/src/Site.php';
require_once __DIR__ . '/src/Payments.php';

clh_site_boot();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: precios.php');
    exit;
}

$plan = isset($_POST['plan']) ? (string) $_POST['plan'] : '';
$user = clh_user();
if (!$user) {
    header('Location: login.php?alta=1&plan=' . rawurlencode($plan));
    exit;
}

try {
    clh_csrf_check();
    $url = clh_checkout_start($user, $plan);
    header('Location: ' . $url);
    exit;
} catch (UserException $error) {
    clh_flash($error->getMessage());
    header('Location: precios.php');
    exit;
}
