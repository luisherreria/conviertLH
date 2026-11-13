<?php

require_once __DIR__ . '/Database.php';

function clh_user()
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    try {
        $st = clh_db()->prepare(
            'SELECT u.id, u.name, u.email, u.level_id, u.credits, u.theme, u.status, u.created_at,
                    l.slug AS level_slug, l.name AS level_name, l.monthly_credits
             FROM users u
             INNER JOIN levels l ON l.id = u.level_id
             WHERE u.id = ?
             LIMIT 1'
        );
        $st->execute([(int) $_SESSION['user_id']]);
        $row = $st->fetch();
    } catch (Exception $e) {
        $user = null;
        return null;
    }

    if (!$row || $row['status'] !== 'active') {
        unset($_SESSION['user_id']);
        $user = null;
        return null;
    }

    $user = $row;
    return $user;
}

function clh_login_id($id)
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $id;
}

function clh_login($email, $password)
{
    $email = strtolower(trim((string) $email));
    $st = clh_db()->prepare('SELECT id, password_hash, status, theme FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $row = $st->fetch();
    if (!$row || !is_string($row['password_hash']) || !password_verify((string) $password, $row['password_hash'])) {
        throw new UserException('Email o contraseña incorrectos.');
    }
    if ($row['status'] !== 'active') {
        throw new UserException('Esta cuenta está dada de baja.');
    }
    clh_login_id((int) $row['id']);
    if (in_array($row['theme'], clh_theme_ids(), true)) {
        clh_remember_theme($row['theme']);
    }
}

function clh_register($name, $email, $password, $theme)
{
    $name = trim((string) $name);
    $email = strtolower(trim((string) $email));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        throw new UserException('Escribí tu nombre.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        throw new UserException('El email no es válido.');
    }
    if (strlen((string) $password) < 8 || strlen((string) $password) > 200) {
        throw new UserException('La contraseña tiene que tener al menos 8 caracteres.');
    }
    if (!in_array($theme, clh_theme_ids(), true)) {
        $theme = 'naranja';
    }

    $pdo = clh_db();
    $level = $pdo->query("SELECT id FROM levels WHERE slug = 'mesa' LIMIT 1")->fetch();
    if (!$level) {
        throw new UserException('La base no tiene el plan Mesa.');
    }

    $now = date('Y-m-d H:i:s');
    try {
        $st = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, level_id, credits, theme, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?)'
        );
        $st->execute([
            $name,
            $email,
            password_hash((string) $password, PASSWORD_DEFAULT),
            (int) $level['id'],
            $theme,
            'active',
            $now,
            $now,
        ]);
    } catch (PDOException $e) {
        $code = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
        if ($code === 1062) {
            throw new UserException('Ya hay una cuenta con ese email.');
        }
        throw new UserException('No se pudo crear la cuenta.');
    }

    clh_login_id((int) $pdo->lastInsertId());
}

function clh_logout()
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => isset($params['path']) ? $params['path'] : '/',
            'domain' => isset($params['domain']) ? $params['domain'] : '',
            'secure' => !empty($params['secure']),
            'httponly' => !empty($params['httponly']),
            'samesite' => isset($params['samesite']) ? $params['samesite'] : 'Lax',
        ]);
        session_destroy();
    }
}

function clh_csrf_token()
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function clh_csrf_check()
{
    $sent = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    $known = isset($_SESSION['csrf']) ? (string) $_SESSION['csrf'] : '';
    if ($sent === '' || $known === '' || !hash_equals($known, $sent)) {
        throw new UserException('La sesión del formulario venció. Volvé a intentar.');
    }
}
