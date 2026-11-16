<?php

require_once __DIR__ . '/Database.php';

function clh_install_signals()
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo = clh_db();
    $cols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM users') as $col) {
        $cols[$col['Field']] = true;
    }
    if (!isset($cols['signup_ip'])) {
        $pdo->exec("ALTER TABLE users ADD signup_ip VARCHAR(45) NOT NULL DEFAULT ''");
    }
    if (!isset($cols['device_id'])) {
        $pdo->exec("ALTER TABLE users ADD device_id VARCHAR(64) NOT NULL DEFAULT ''");
    }
    if (!isset($cols['is_admin'])) {
        $pdo->exec('ALTER TABLE users ADD is_admin TINYINT(1) NOT NULL DEFAULT 0');
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS notices (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            kind VARCHAR(20) NOT NULL,
            body VARCHAR(255) NOT NULL,
            ref_user_id INT UNSIGNED NULL,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_notices_user (user_id, id),
            CONSTRAINT fk_notices_user FOREIGN KEY (user_id) REFERENCES users (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $admin = $pdo->query('SELECT id FROM users WHERE is_admin = 1 LIMIT 1')->fetch();
    if (!$admin) {
        $pdo->exec('UPDATE users SET is_admin = 1 ORDER BY id ASC LIMIT 1');
    }
}

function clh_client_ip()
{
    $candidates = [];
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidates[] = (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $candidates[] = (string) $_SERVER['REMOTE_ADDR'];
    }
    foreach ($candidates as $ip) {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return substr($ip, 0, 45);
        }
    }
    return '';
}

function clh_notice_add($userId, $kind, $body, $refUserId = null)
{
    $st = clh_db()->prepare(
        'INSERT INTO notices (user_id, kind, body, ref_user_id, created_at) VALUES (?, ?, ?, ?, ?)'
    );
    $st->execute([
        (int) $userId,
        substr((string) $kind, 0, 20),
        substr((string) $body, 0, 255),
        $refUserId ? (int) $refUserId : null,
        date('Y-m-d H:i:s'),
    ]);
}

function clh_admin_ids()
{
    $ids = [];
    foreach (clh_db()->query('SELECT id FROM users WHERE is_admin = 1') as $row) {
        $ids[] = (int) $row['id'];
    }
    return $ids;
}

function clh_notice_unread($userId)
{
    try {
        clh_install_signals();
        $st = clh_db()->prepare('SELECT COUNT(*) FROM notices WHERE user_id = ? AND read_at IS NULL');
        $st->execute([(int) $userId]);
        return (int) $st->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function clh_signup_hold($deviceId, $ip)
{
    $st = clh_db()->prepare(
        'SELECT id, name, email, signup_ip, device_id
         FROM users
         WHERE (device_id = ? AND device_id <> \'\')
            OR (signup_ip = ? AND signup_ip <> \'\')
         ORDER BY id ASC'
    );
    $st->execute([$deviceId, $ip]);
    $rows = $st->fetchAll();
    if (!$rows) {
        return null;
    }
    $sameDevice = false;
    $sameIp = false;
    $names = [];
    foreach ($rows as $row) {
        if ($deviceId !== '' && $row['device_id'] === $deviceId) {
            $sameDevice = true;
        }
        if ($ip !== '' && $row['signup_ip'] === $ip) {
            $sameIp = true;
        }
        if (count($names) < 3) {
            $names[] = $row['name'] . ' (' . $row['email'] . ')';
        }
    }
    if ($sameDevice && $sameIp) {
        $why = 'la misma IP y el mismo navegador';
    } elseif ($sameDevice) {
        $why = 'el mismo navegador';
    } else {
        $why = 'la misma IP';
    }
    return [
        'why' => $why,
        'who' => implode(', ', $names),
    ];
}

function clh_log_activity($userId, $tool, $mockup, $count, $scale)
{
    clh_install_signals();
    $userId = (int) $userId;
    $count = max(1, (int) $count);
    $detail = 'Procesó una imagen';
    if ($tool === 'remove_bg') {
        $detail = 'Quitó el fondo';
    } elseif ($tool === 'upscale') {
        $detail = 'Escaló una imagen';
    } elseif ($tool === 'convert') {
        $detail = 'Convirtió una imagen';
    } elseif ($tool === 'crop') {
        $detail = 'Recortó una imagen';
    } elseif ($tool === 'flip') {
        $detail = 'Espejó una imagen';
    } elseif ($tool === 'halftone') {
        $detail = 'Semitono para DTF';
    } elseif ($tool === 'mockup') {
        $label = 'plantilla';
        if (class_exists('Mockup')) {
            $options = Mockup::options();
            if (isset($options[$mockup]['label'])) {
                $label = $options[$mockup]['label'];
            }
        }
        $detail = 'Mockup de ' . $label;
    }
    if ($count > 1) {
        $detail .= ' · ' . $count . ' imágenes';
    }
    $cost = 0;
    if ($tool === 'remove_bg') {
        $cost = 2 * $count;
    } elseif ($tool === 'upscale') {
        $cost = ((int) $scale >= 4 ? 4 : 2) * $count;
    } elseif ($tool === 'mockup') {
        $cost = $count;
    }
    if ($cost > 0) {
        $detail .= ' · ' . $cost . ($cost === 1 ? ' crédito' : ' créditos');
    }

    $st = clh_db()->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $name = $st->fetchColumn();
    $name = is_string($name) && $name !== '' ? $name : 'Usuario';
    $when = date('d/m/Y H:i');
    clh_notice_add($userId, 'activity', $when . ' · ' . $detail, null);
    $shared = $when . ' · ' . $name . ' · ' . $detail;
    foreach (clh_admin_ids() as $adminId) {
        if ($adminId !== $userId) {
            clh_notice_add($adminId, 'activity', $shared, null);
        }
    }
}

function clh_approve_user($targetId)
{
    clh_install_signals();
    $targetId = (int) $targetId;
    $now = date('Y-m-d H:i:s');
    $st = clh_db()->prepare('UPDATE users SET status = ?, updated_at = ? WHERE id = ? AND status = ?');
    $st->execute(['active', $now, $targetId, 'pending']);
    if ($st->rowCount() < 1) {
        return false;
    }
    clh_notice_add($targetId, 'info', 'Tu cuenta ya está activa. Ya podés entrar.', null);
    $done = clh_db()->prepare('UPDATE notices SET read_at = ? WHERE kind = ? AND ref_user_id = ? AND read_at IS NULL');
    $done->execute([$now, 'review', $targetId]);
    return true;
}
