<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
    http_response_code(405);
    exit('درخواست معتبر نیست.');
}

admin_logout();
header('Location: ' . app_url('admin/login.php'));
exit;
