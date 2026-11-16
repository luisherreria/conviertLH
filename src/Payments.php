<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/MercadoPago.php';

function clh_flash($message)
{
    $_SESSION['flash'] = (string) $message;
}

function clh_flash_take()
{
    if (empty($_SESSION['flash']) || !is_string($_SESSION['flash'])) {
        return '';
    }
    $message = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $message;
}

function clh_public_base()
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost';
    if (!preg_match('/^[A-Za-z0-9.-]+(?::\d+)?$/', $host)) {
        $host = 'localhost';
    }
    return ($https ? 'https' : 'http') . '://' . $host . clh_cookie_path();
}

function clh_checkout_start(array $user, $slug)
{
    $slug = (string) $slug;
    if (!preg_match('/^[a-z0-9_-]{1,40}$/', $slug)) {
        throw new UserException('Ese plan no existe.');
    }
    $db = clh_db();
    $st = $db->prepare('SELECT id, name, price_ars, monthly_credits FROM levels WHERE slug = ? LIMIT 1');
    $st->execute([$slug]);
    $level = $st->fetch();
    if (!$level || (int) $level['price_ars'] <= 0) {
        throw new UserException('Ese plan no se cobra.');
    }

    $reference = 'clh' . (int) $user['id'] . bin2hex(random_bytes(8));
    $now = date('Y-m-d H:i:s');
    $insert = $db->prepare(
        'INSERT INTO payments (user_id, level_id, external_reference, mp_order_id, amount_ars, credits, status, granted, created_at, updated_at)
         VALUES (?, ?, ?, NULL, ?, ?, ?, 0, ?, ?)'
    );
    $insert->execute([
        (int) $user['id'],
        (int) $level['id'],
        $reference,
        (int) $level['price_ars'],
        (int) $level['monthly_credits'],
        'pending',
        $now,
        $now,
    ]);
    $paymentId = (int) $db->lastInsertId();

    $back = clh_public_base() . 'pago-vuelta.php?ref=' . rawurlencode($reference);
    $mp = MercadoPago::fromConfig();
    $payerEmail = (string) $user['email'];
    if ($mp->isTest() && stripos($payerEmail, '@testuser.com') === false) {
        $payerEmail = '';
    }
    try {
        $order = $mp->createCheckout(
            [
                'title' => 'SubliEdit ' . $level['name'],
                'amount' => (int) $level['price_ars'],
            ],
            $reference,
            $payerEmail,
            [
                'success_url' => $back,
                'failure_url' => $back,
                'pending_url' => $back,
            ]
        );
    } catch (UserException $error) {
        $fail = $db->prepare('UPDATE payments SET status = ?, updated_at = ? WHERE id = ?');
        $fail->execute(['error', date('Y-m-d H:i:s'), $paymentId]);
        throw $error;
    }

    $save = $db->prepare('UPDATE payments SET mp_order_id = ?, updated_at = ? WHERE id = ?');
    $save->execute([$order['id'], date('Y-m-d H:i:s'), $paymentId]);
    clh_checkout_host($order['checkout_url']);
    return $order['checkout_url'];
}

function clh_payment_confirm(array $user, $reference)
{
    $reference = (string) $reference;
    if (!preg_match('/^clh[0-9]+[a-f0-9]{16}$/', $reference)) {
        throw new UserException('No encuentro ese pago.');
    }
    $db = clh_db();
    $st = $db->prepare('SELECT * FROM payments WHERE external_reference = ? AND user_id = ? LIMIT 1');
    $st->execute([$reference, (int) $user['id']]);
    $payment = $st->fetch();
    if (!$payment) {
        throw new UserException('Ese pago no es de esta cuenta.');
    }
    if ((int) $payment['granted'] === 1) {
        return 'already';
    }
    if ($payment['mp_order_id'] === null || $payment['mp_order_id'] === '') {
        throw new UserException('El checkout no llegó a crearse.');
    }

    $order = MercadoPago::fromConfig()->getOrder($payment['mp_order_id']);
    $remoteRef = isset($order['external_reference']) ? (string) $order['external_reference'] : '';
    $remoteAmount = isset($order['total_amount']) ? number_format((float) $order['total_amount'], 2, '.', '') : '';
    $expected = number_format((int) $payment['amount_ars'], 2, '.', '');
    if ($remoteRef !== $reference || $remoteAmount !== $expected) {
        throw new UserException('El pago que devolvió Mercado Pago no coincide con el pedido.');
    }

    $remoteStatus = isset($order['status']) ? substr((string) $order['status'], 0, 32) : 'unknown';
    if (!MercadoPago::isPaid($order)) {
        $update = $db->prepare('UPDATE payments SET status = ?, updated_at = ? WHERE id = ? AND granted = 0');
        $update->execute([$remoteStatus !== '' ? $remoteStatus : 'pending', date('Y-m-d H:i:s'), (int) $payment['id']]);
        return 'pending';
    }

    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT granted, level_id, credits FROM payments WHERE id = ? FOR UPDATE');
        $lock->execute([(int) $payment['id']]);
        $locked = $lock->fetch();
        if (!$locked || (int) $locked['granted'] === 1) {
            $db->commit();
            return 'already';
        }
        $userRow = $db->prepare('SELECT credits FROM users WHERE id = ? FOR UPDATE');
        $userRow->execute([(int) $user['id']]);
        $current = $userRow->fetch();
        if (!$current) {
            throw new UserException('No encuentro la cuenta.');
        }
        $level = $db->prepare('SELECT name, monthly_credits FROM levels WHERE id = ?');
        $level->execute([(int) $locked['level_id']]);
        $plan = $level->fetch();
        if (!$plan) {
            throw new UserException('El plan de ese pago ya no existe.');
        }
        $newCredits = (int) $plan['monthly_credits'];
        $delta = $newCredits - (int) $current['credits'];
        $now = date('Y-m-d H:i:s');
        $saveUser = $db->prepare('UPDATE users SET level_id = ?, credits = ?, updated_at = ? WHERE id = ?');
        $saveUser->execute([(int) $locked['level_id'], $newCredits, $now, (int) $user['id']]);
        $move = $db->prepare('INSERT INTO credit_moves (user_id, delta, reason, created_at) VALUES (?, ?, ?, ?)');
        $move->execute([(int) $user['id'], $delta, 'Plan ' . $plan['name'] . ' acreditado', $now]);
        $done = $db->prepare('UPDATE payments SET status = ?, granted = 1, updated_at = ? WHERE id = ?');
        $done->execute(['approved', $now, (int) $payment['id']]);
        $db->commit();
    } catch (Exception $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
    return 'approved';
}

function clh_payment_open(array $user)
{
    $st = clh_db()->prepare(
        'SELECT p.external_reference, p.amount_ars, p.status, l.name
         FROM payments p
         JOIN levels l ON l.id = p.level_id
         WHERE p.user_id = ? AND p.granted = 0 AND p.mp_order_id IS NOT NULL
         ORDER BY p.id DESC
         LIMIT 1'
    );
    $st->execute([(int) $user['id']]);
    $row = $st->fetch();
    return $row ? $row : null;
}

function clh_checkout_host($url)
{
    $host = parse_url((string) $url, PHP_URL_HOST);
    if (!is_string($host) || !preg_match('/(^|\.)mercadopago\.com(\.[a-z]{2})?$/', $host)) {
        throw new UserException('Mercado Pago devolvió una dirección de pago que no voy a abrir.');
    }
}
