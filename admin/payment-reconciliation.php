<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_once dirname(__DIR__) . '/app/Payments/PaymentTransactionService.php';
require_admin();

$pdo = Database::connection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        try {
            $transactionId = filter_var($_POST['transaction_id'] ?? null, FILTER_VALIDATE_INT);
            $note = trim((string)($_POST['resolution_note'] ?? ''));
            if ($transactionId === false || $transactionId === null || $transactionId < 1) {
                throw new InvalidArgumentException('شناسه تراکنش معتبر نیست.');
            }
            if ($note === '') {
                throw new InvalidArgumentException('یادداشت بررسی درگاه الزامی است.');
            }
            $service = new PaymentTransactionService($pdo);
            if (!$service->resolveAmbiguousInitiationAsFailed((int)$transactionId, $note)) {
                throw new RuntimeException('تراکنش تغییر کرده یا دیگر در وضعیت مبهم قابل تعیین تکلیف نیست.');
            }
            $message = 'تراکنش پس از ثبت یادداشت بررسی، ناموفق تعیین شد. امکان تلاش مجدد برای سفارش آزاد شد.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$config = app_config();
$sql = "SELECT t.id, t.order_id, t.provider, t.amount_minor, t.currency, t.created_at,
               o.public_id, o.buyer_name, o.buyer_phone
        FROM payment_transactions t
        JOIN orders o ON o.id = t.order_id
        WHERE t.status = 'initiated' AND t.provider_reference IS NULL
        ORDER BY t.created_at ASC, t.id ASC";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>بررسی پرداخت‌های مبهم | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.shell{width:min(1180px,calc(100% - 32px));margin:0 auto;padding-bottom:30px}.top{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:24px 0}.panel{padding:22px;margin:16px 0}.muted{color:var(--muted);line-height:1.8}.notice{padding:12px 14px;border-radius:12px;margin:14px 0}.ok{border:1px solid #44d6a044;background:#44d6a018}.err{border:1px solid #ff718044;background:#ff718018}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:right;padding:13px;border-bottom:1px solid var(--border);vertical-align:top}input,textarea{width:100%;box-sizing:border-box;margin:6px 0;padding:10px;border:1px solid var(--border);border-radius:9px;background:var(--surface-2);color:var(--text);font:inherit}textarea{min-width:220px;min-height:72px}button{font:inherit;cursor:pointer}
</style>
</head>
<body><main class="shell">
<header class="top"><a class="brand" href="<?= e(app_url('admin/')) ?>">ATD IBSng Store · مدیریت</a><a class="button" href="<?= e(app_url('admin/')) ?>">داشبورد</a></header>
<section class="panel">
<div class="eyebrow">پرداخت‌ها</div><h1>بررسی تراکنش‌های مبهم</h1>
<p class="muted">این فهرست تراکنش‌هایی را نشان می‌دهد که شروع پرداخت در درگاه نتیجه قطعی نداشته و هنوز شناسهٔ درگاه ذخیره نشده است. پیش از تعیین تراکنش به‌عنوان ناموفق، حتماً در پنل درگاه بررسی کنید که فاکتور قابل پرداختی برای آن ایجاد نشده باشد. این عملیات فقط وضعیت داخلی را تغییر می‌دهد و درگاه را فراخوانی نمی‌کند.</p>
<?php if ($message !== ''): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>تراکنش</th><th>سفارش</th><th>درگاه</th><th>مبلغ</th><th>خریدار</th><th>ایجاد</th><th>تعیین تکلیف</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7">تراکنش مبهمی برای بررسی وجود ندارد.</td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr>
<td>#<?= (int)$row['id'] ?></td><td><?= e((string)$row['public_id']) ?></td><td><?= e((string)$row['provider']) ?></td>
<td><?= e(number_format((int)$row['amount_minor'])) ?> <?= e((string)$row['currency']) ?></td>
<td><?= e((string)$row['buyer_name']) ?><br><?= e((string)$row['buyer_phone']) ?></td>
<td><?= e((string)$row['created_at']) ?></td>
<td><form method="post" onsubmit="return confirm('آیا در پنل درگاه بررسی کردید که فاکتور قابل پرداختی وجود ندارد؟');">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="transaction_id" value="<?= (int)$row['id'] ?>">
<label for="note-<?= (int)$row['id'] ?>">یادداشت بررسی درگاه (الزامی)</label><textarea id="note-<?= (int)$row['id'] ?>" name="resolution_note" required maxlength="2000" placeholder="نتیجه بررسی و دلیل تعیین تراکنش به‌عنوان ناموفق"></textarea>
<button class="button" type="submit">تعیین به‌عنوان ناموفق و آزادسازی سفارش</button></form></td>
</tr><?php endforeach; ?>
</tbody></table></div></section><footer><a href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></footer></main></body></html>
