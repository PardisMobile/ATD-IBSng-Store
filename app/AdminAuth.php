<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Database.php';

function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['admin_csrf'];
}

function admin_csrf_valid(string $token): bool
{
    return isset($_SESSION['admin_csrf']) && hash_equals((string)$_SESSION['admin_csrf'], $token);
}

function admin_is_authenticated(): bool
{
    return isset($_SESSION['admin_user_id']) && (int)$_SESSION['admin_user_id'] > 0;
}

function require_admin(): void
{
    if (!admin_is_authenticated()) {
        header('Location: ' . app_url('admin/login.php'));
        exit;
    }
}

function admin_login(string $username, string $password): bool
{
    $stmt = Database::connection()->prepare(
        'SELECT id, username, password_hash, display_name, is_active FROM admin_users WHERE username = ? LIMIT 1'
    );
    $stmt->execute([trim($username)]);
    $admin = $stmt->fetch();

    if (!$admin || (int)$admin['is_active'] !== 1 || !password_verify($password, (string)$admin['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_user_id'] = (int)$admin['id'];
    $_SESSION['admin_username'] = (string)$admin['username'];
    $_SESSION['admin_display_name'] = (string)$admin['display_name'];
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));

    $update = Database::connection()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?');
    $update->execute([(int)$admin['id']]);
    return true;
}

function admin_logout(): void
{
    unset($_SESSION['admin_user_id'], $_SESSION['admin_username'], $_SESSION['admin_display_name'], $_SESSION['admin_csrf']);
    session_regenerate_id(true);
}
