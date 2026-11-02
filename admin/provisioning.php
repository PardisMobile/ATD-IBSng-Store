<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_once dirname(__DIR__) . '/app/IBSngProvisioningDispatcher.php';
require_admin();

$pdo = Database::connection();
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        try {
            $attemptId = filter_var($_POST['attempt_id'] ?? null, FILTER_VALIDATE_INT);
            if ($attemptId === false || $attemptId === null || $attemptId < 1) {
                throw new InvalidArgumentException('شناسه عملیات معتبر نیست.');
            }
            $dispatcher = new IBSngProvisioningDispatcher($pdo);
            if ((string)($_POST['action'] ?? 'run') === 'resolve') {
                $outcome = (string)($_POST['outcome'] ?? '');
                $remoteUserId = trim((string)($_POST['remote_user_id'] ?? ''));
                $note = trim((string)($_POST['resolution_note'] ?? ''));
                $dispatcher->resolveManually((int)$attemptId, $outcome, $remoteUserId !== '' ? $remoteUserId : null, $note);
                $message = 'تعیین تکلیف دستی ثبت شد؛ هیچ درخواست جدیدی به IBSng ارسال نشد.';
            } else {
                $result = $dispatcher->runQueued((int)$attemptId);
                $message = $result === 'succeeded'
                    ? 'ساخت حساب در IBSng تأیید شد.'
                    : 'نتیجهٔ IBSng قطعی نیست یا تعارض وجود دارد؛ عملیات در وضعیت «نیازمند بررسی» قرار گرفت و retry خودکار انجام نشد.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
$config = app_config();
$sql = "SELECT a.id, a.order_id, a.server_id, a.operation, a.status, a.attempt_number,
               a.remote_user_id, a.error_code, a.error_message, a.created_at,
               o.username, o.product_name_snapshot, s.name AS server_name
        FROM provisioning_attempts a
        JOIN orders o ON o.id=a.order_id
        JOIN ibsng_servers s ON s.id=a.server_id
        ORDER BY a.created_at DESC, a.id DESC LIMIT 200";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>اجرای Provisioning | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.shell{width:min(1200px,calc(100% - 32px));margin:0 auto;padding-bottom:30px}.top{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:24px 0}.panel{padding:22px;margin:16px 0}.hint{color:var(--muted);line-height:1.8}.notice{padding:12px 14px;border-radius:12px;margin:14px 0}.ok{border:1px solid #44d6a044;background:#44d6a018}.err{border:1px solid #ff718044;background:#ff718018}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:right;padding:12px;border-bottom:1px solid var(--border);white-space:nowrap}.run{border:0;cursor:pointer;font:inherit}
</style>
</head>
<body><main class="shell">
<header class="top"><a class="brand" href="<?= e(app_url('admin/')) ?>">مدیریت فروشگاه</a><a class="button" href="<?= e(app_url('admin/')) ?>">داشبورد</a></header>
<section class="panel"><div class="eyebrow">عملیات IBSng</div><h1>صف Provisioning</h1>
<p class="hint">بعد از تأیید پرداخت، اجرای اولیهٔ صف به‌صورت best-effort انجام می‌شود. این صفحه برای اجرای مجدد موردهای queued باقی‌مانده و تعیین تکلیف موارد مبهم است. خطا یا پاسخ مبهم در «نیازمند بررسی» می‌ماند؛ running مانده هم retry خودکار نمی‌شود. برای needs_review یا running، مدیر پس از بررسی مستقیم IBSng می‌تواند فقط نتیجه را دستی ثبت کند؛ این کار هیچ درخواست remote جدیدی ارسال نمی‌کند.</p>
<?php if ($message !== ''): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>شناسه</th><th>سفارش</th><th>نام کاربری</th><th>سرویس</th><th>سرور</th><th>وضعیت</th><th>نتیجه/خطا</th><th>عملیات</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8">موردی در صف ثبت نشده است.</td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr><td><?= (int)$row['id'] ?></td><td>#<?= (int)$row['order_id'] ?></td><td><?= e((string)$row['username']) ?></td><td><?= e((string)$row['product_name_snapshot']) ?></td><td><?= e((string)$row['server_name']) ?></td><td><?= e((string)$row['status']) ?></td><td><?= e(trim((string)($row['error_message'] ?? '')) ?: (string)($row['remote_user_id'] ?? '—')) ?></td><td><?php if ($row['status'] === 'queued' && $row['operation'] === 'create'): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="run"><input type="hidden" name="attempt_id" value="<?= (int)$row['id'] ?>"><button class="button primary run" type="submit">اجرای یک‌باره</button></form><?php elseif (in_array($row['status'], ['needs_review','running'], true)): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="resolve"><input type="hidden" name="attempt_id" value="<?= (int)$row['id'] ?>"><div class="field"><label>نتیجهٔ بررسی IBSng</label><select name="outcome"><option value="succeeded">حساب تأیید شد</option><option value="failed">حساب ایجاد نشده / شکست قطعی</option></select></div><div class="field"><label>Remote user ID (اختیاری)</label><input name="remote_user_id" maxlength="150" value="<?= e((string)($row['remote_user_id'] ?? '')) ?>"></div><div class="field"><label>یادداشت بررسی الزامی</label><input name="resolution_note" maxlength="1500" required placeholder="آنچه در IBSng بررسی و تأیید شد"></div><button class="button run" type="submit">ثبت تعیین تکلیف</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></section></main></body></html>
