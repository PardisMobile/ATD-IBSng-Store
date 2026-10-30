<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_admin();

$config = app_config();
$pdo = Database::connection();
$message = '';
$error = '';
$servers = $pdo->query('SELECT id,name,host,is_active FROM ibsng_servers ORDER BY is_default DESC,id')->fetchAll();
$selectedServerId = (int)($_GET['server_id'] ?? $_POST['server_id'] ?? ($servers[0]['id'] ?? 0));
$serverIds = array_map(static fn(array $server): int => (int)$server['id'], $servers);
if ($selectedServerId > 0 && !in_array($selectedServerId, $serverIds, true)) {
    $selectedServerId = (int)($servers[0]['id'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $groupId = (int)($_POST['id'] ?? 0);
        try {
            if ($action === 'add') {
                $planName = trim((string)($_POST['plan_name'] ?? ''));
                $groupName = trim((string)($_POST['group_name'] ?? ''));
                if ($selectedServerId < 1 || $planName === '' || $groupName === '') {
                    throw new InvalidArgumentException('سرور، نام پلن و نام دقیق گروه IBSng الزامی هستند.');
                }
                $stmt = $pdo->prepare('INSERT INTO ibsng_groups (server_id, plan_name, group_name) VALUES (?, ?, ?)');
                $stmt->execute([$selectedServerId, $planName, $groupName]);
                $message = 'نگاشت پلن فروشگاه به گروه IBSng اضافه شد.';
            } elseif ($action === 'update') {
                $planName = trim((string)($_POST['plan_name'] ?? ''));
                $groupName = trim((string)($_POST['group_name'] ?? ''));
                if ($groupId < 1 || $planName === '' || $groupName === '') {
                    throw new InvalidArgumentException('نام پلن و نام دقیق گروه IBSng الزامی هستند.');
                }
                $stmt = $pdo->prepare('UPDATE ibsng_groups SET plan_name=?, group_name=? WHERE id=? AND server_id=?');
                $stmt->execute([$planName, $groupName, $groupId, $selectedServerId]);
                if ($stmt->rowCount() === 0) {
                    $check = $pdo->prepare('SELECT id FROM ibsng_groups WHERE id=? AND server_id=?');
                    $check->execute([$groupId, $selectedServerId]);
                    if (!$check->fetchColumn()) {
                        throw new RuntimeException('نگاشت موردنظر پیدا نشد.');
                    }
                }
                $message = 'نگاشت پلن و گروه IBSng ذخیره شد.';
            } elseif ($action === 'toggle') {
                $stmt = $pdo->prepare('UPDATE ibsng_groups SET is_active=IF(is_active=1,0,1) WHERE id=? AND server_id=?');
                $stmt->execute([$groupId, $selectedServerId]);
                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('نگاشت موردنظر پیدا نشد.');
                }
                $message = 'وضعیت نگاشت تغییر کرد.';
            } elseif ($action === 'delete') {
                $check = $pdo->prepare('SELECT COUNT(*) FROM product_server_groups WHERE ibsng_group_id=?');
                $check->execute([$groupId]);
                if ((int)$check->fetchColumn() > 0) {
                    throw new RuntimeException('این گروه به محصول/پلن فروشگاه متصل است؛ ابتدا نگاشت محصول را تغییر دهید.');
                }
                $stmt = $pdo->prepare('DELETE FROM ibsng_groups WHERE id=? AND server_id=?');
                $stmt->execute([$groupId, $selectedServerId]);
                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('نگاشت موردنظر پیدا نشد.');
                }
                $message = 'نگاشت حذف شد.';
            } else {
                throw new RuntimeException('عملیات نامعتبر است.');
            }
        } catch (PDOException $e) {
            $error = 'این نام پلن یا گروه برای همین سرور قبلاً ثبت شده یا ذخیره‌سازی با خطا مواجه شد.';
        } catch (Throwable $e) {
            $error = $e instanceof InvalidArgumentException || $e instanceof RuntimeException
                ? $e->getMessage()
                : 'عملیات انجام نشد.';
        }
    }
}

$groups = [];
if ($selectedServerId > 0) {
    $stmt = $pdo->prepare('SELECT id,plan_name,group_name,is_active,created_at FROM ibsng_groups WHERE server_id=? ORDER BY id DESC');
    $stmt->execute([$selectedServerId]);
    $groups = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>نگاشت پلن و گروه IBSng | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.admin-top{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:24px 0}.panel-card{padding:24px;margin-top:20px}.field{display:grid;gap:8px;margin:14px 0}.field label{color:var(--muted);font-size:.9rem}.field input,.field select{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:#0d1426;color:var(--text);font:inherit}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}.notice{padding:13px 15px;border-radius:12px;margin:14px 0;line-height:1.8}.ok{background:#44d6a018;border:1px solid #44d6a044}.err{background:#ff718018;border:1px solid #ff718044}.group-row{padding:18px;margin:14px 0}.group-head{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.muted{overflow-wrap:anywhere}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.actions button{font:inherit;cursor:pointer;border:1px solid var(--border);background:var(--surface-2);color:var(--text)}.empty{padding:25px;text-align:center;color:var(--muted)}@media(max-width:600px){.grid{grid-template-columns:1fr}.admin-top{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<main class="admin-shell">
<header class="admin-top">
<a class="brand" href="<?= e(app_url('admin/')) ?>">ATD IBSng Store · مدیریت</a>
<nav><a class="button" href="<?= e(app_url('admin/ibsng-servers.php')) ?>">سرورهای IBSng</a></nav>
</header>
<section class="panel panel-card">
<div class="eyebrow">IBSng · Group / Plan Mapping</div>
<h1 style="font-size:2rem">نگاشت پلن فروشگاه به گروه IBSng</h1>
<p class="muted">نام گروه باید دقیقاً مطابق گروه موجود در IBSng وارد شود. در این بخش نگاشت به‌صورت دستی تعریف می‌شود؛ دریافت خودکار فهرست گروه‌ها هنوز اضافه نشده است.</p>
<?php if ($message !== ''): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
<?php if (!$servers): ?><div class="empty">ابتدا از بخش سرورهای IBSng یک سرور اضافه کنید.</div><?php else: ?>
<form method="get">
<div class="field"><label for="server_id">سرور IBSng</label><select id="server_id" name="server_id" onchange="this.form.submit()">
<?php foreach ($servers as $server): ?><option value="<?= (int)$server['id'] ?>"<?= (int)$server['id'] === $selectedServerId ? ' selected' : '' ?>><?= e((string)$server['name']) ?> · <?= e((string)$server['host']) ?></option><?php endforeach; ?>
</select></div>
</form>
<?php endif; ?>
</section>
<?php if ($selectedServerId > 0): ?>
<section class="panel panel-card">
<h2>افزودن نگاشت</h2>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="add"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>">
<div class="grid">
<div class="field"><label for="plan_name">Plan Name (نام پلن فروشگاه)</label><input id="plan_name" name="plan_name" required maxlength="150" placeholder="مثلاً: یک ماهه"></div>
<div class="field"><label for="group_name">IBSng Group Name (نام دقیق گروه)</label><input id="group_name" name="group_name" required maxlength="150" placeholder="نام گروه در IBSng"></div>
</div>
<button class="button primary" type="submit">ثبت نگاشت</button>
</form>
</section>
<section class="panel panel-card">
<h2>نگاشت‌های ثبت‌شده</h2>
<?php if (!$groups): ?><div class="empty">برای این سرور هنوز نگاشتی ثبت نشده است.</div><?php endif; ?>
<?php foreach ($groups as $group): ?>
<article class="group-row panel">
<div class="group-head"><strong><?= e((string)$group['plan_name']) ?></strong><span class="status-chip"><?= (int)$group['is_active'] === 1 ? 'فعال' : 'غیرفعال' ?></span></div>
<div class="muted" dir="ltr"><?= e((string)$group['group_name']) ?></div>
<details style="margin-top:12px"><summary class="button">ویرایش نگاشت</summary>
<form method="post" style="margin-top:12px">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$group['id'] ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>">
<div class="grid">
<div class="field"><label>Plan Name</label><input name="plan_name" required value="<?= e((string)$group['plan_name']) ?>"></div>
<div class="field"><label>IBSng Group Name</label><input name="group_name" required value="<?= e((string)$group['group_name']) ?>"></div>
</div><button class="button primary" type="submit">ذخیره تغییرات</button>
</form></details>
<div class="actions">
<form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$group['id'] ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><button class="button" type="submit"><?= (int)$group['is_active'] === 1 ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?></button></form>
<form method="post" onsubmit="return confirm('این نگاشت حذف شود؟');"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$group['id'] ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><button class="button" type="submit">حذف</button></form>
</div>
</article>
<?php endforeach; ?>
</section>
<?php endif; ?>
<footer><a href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></footer>
</main>
</body>
</html>
