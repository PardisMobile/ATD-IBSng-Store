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
                $validityDays = filter_var($_POST['validity_days'] ?? null, FILTER_VALIDATE_INT);
                if ($selectedServerId < 1 || $planName === '' || $groupName === '' || $validityDays === false || $validityDays < 1 || $validityDays > 36500) throw new InvalidArgumentException('سرور، نام پلن، نام دقیق گروه IBSng و مدت اعتبار بین ۱ تا ۳۶۵۰۰ روز الزامی هستند.');
                $stmt = $pdo->prepare('INSERT INTO ibsng_groups (server_id, plan_name, group_name, validity_days) VALUES (?, ?, ?, ?)');
                $stmt->execute([$selectedServerId, $planName, $groupName, $validityDays]);
                $message = 'تعریف پلن فروشگاه و گروه IBSng اضافه شد.';
            } elseif ($action === 'update') {
                $planName = trim((string)($_POST['plan_name'] ?? ''));
                $groupName = trim((string)($_POST['group_name'] ?? ''));
                $validityDays = filter_var($_POST['validity_days'] ?? null, FILTER_VALIDATE_INT);
                if ($groupId < 1 || $planName === '' || $groupName === '' || $validityDays === false || $validityDays < 1 || $validityDays > 36500) throw new InvalidArgumentException('نام پلن، نام دقیق گروه IBSng و مدت اعتبار بین ۱ تا ۳۶۵۰۰ روز الزامی هستند.');
                $stmt = $pdo->prepare('UPDATE ibsng_groups SET plan_name=?, group_name=?, validity_days=? WHERE id=? AND server_id=?');
                $stmt->execute([$planName, $groupName, $validityDays, $groupId, $selectedServerId]);
                if ($stmt->rowCount() === 0) {
                    $check = $pdo->prepare('SELECT id FROM ibsng_groups WHERE id=? AND server_id=?');
                    $check->execute([$groupId, $selectedServerId]);
                    if (!$check->fetchColumn()) {
                        throw new RuntimeException('پلن موردنظر پیدا نشد.');
                    }
                }
                $message = 'تغییرات پلن ذخیره شد.';
            } elseif ($action === 'toggle') {
                $stmt = $pdo->prepare('UPDATE ibsng_groups SET is_active=IF(is_active=1,0,1) WHERE id=? AND server_id=?');
                $stmt->execute([$groupId, $selectedServerId]);
                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('پلن موردنظر پیدا نشد.');
                }
                $message = 'وضعیت گروه تغییر کرد.';
            } elseif ($action === 'delete') {
                $check = $pdo->prepare('SELECT COUNT(*) FROM product_server_groups WHERE ibsng_group_id=?');
                $check->execute([$groupId]);
                if ((int)$check->fetchColumn() > 0) {
                    throw new RuntimeException('این گروه به محصول فروشگاه متصل است؛ ابتدا اتصال محصول را تغییر دهید.');
                }
                $stmt = $pdo->prepare('DELETE FROM ibsng_groups WHERE id=? AND server_id=?');
                $stmt->execute([$groupId, $selectedServerId]);
                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('پلن موردنظر پیدا نشد.');
                }
                $message = 'پلن حذف شد.';
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
    $stmt = $pdo->prepare('SELECT id,plan_name,group_name,validity_days,is_active,created_at FROM ibsng_groups WHERE server_id=? ORDER BY id DESC');
    $stmt->execute([$selectedServerId]);
    $groups = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?= e(app_asset_url('assets/icons/atd-app-icon.svg')) ?>"><link rel="apple-touch-icon" sizes="180x180" href="<?= e(app_asset_url('assets/icons/apple-touch-icon.png')) ?>"><link rel="manifest" href="<?= e(app_asset_url('site.webmanifest')) ?>">
<title data-ui-title-key="title_admin_ibsng_groups">تعریف پلن و گروه IBSng | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.admin-top{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:24px 0}.panel-card{padding:24px;margin-top:20px}.field{display:grid;gap:8px;margin:14px 0}.field label{color:var(--muted);font-size:.9rem}.field input,.field select{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--control-bg,var(--surface-2));color:var(--text);font:inherit}.field input:focus-visible,.field select:focus-visible{outline:2px solid var(--accent);outline-offset:2px;box-shadow:0 0 0 4px var(--ui-focus-ring)}.field input[name="group_name"]{direction:ltr;text-align:left;unicode-bidi:plaintext}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0 16px}.notice{padding:13px 15px;border-radius:12px;margin:14px 0;line-height:1.8}.ok{background:#44d6a018;border:1px solid #44d6a044}.err{background:#ff718018;border:1px solid #ff718044}.group-row{padding:18px;margin:14px 0}.group-head{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.muted{overflow-wrap:anywhere}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.actions button{font:inherit;cursor:pointer}.empty{padding:25px;text-align:center;color:var(--muted)}
.group-row{border:1px solid var(--border);border-radius:16px;background:var(--surface);transition:border-color .18s ease,background-color .18s ease}
.group-row:hover{border-color:color-mix(in srgb,var(--accent) 42%,var(--border));background:color-mix(in srgb,var(--surface) 94%,var(--accent))}
.group-head{align-items:center}
.group-head strong{min-width:0;overflow-wrap:anywhere}
.group-row>.muted{display:block;margin-top:8px;padding:9px 12px;border:1px solid var(--border);border-radius:10px;background:var(--surface-2);font-variant-numeric:tabular-nums}
.group-row details{border-top:1px solid var(--border);padding-top:12px}
.group-row summary{display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;list-style:none;min-height:40px;box-sizing:border-box}
.group-row summary::-webkit-details-marker{display:none}
.group-row summary::after{content:'＋';font-size:1rem;line-height:1;opacity:.8}
.group-row details[open] summary::after{content:'−'}
.group-row summary:focus-visible{outline:2px solid var(--accent);outline-offset:2px;box-shadow:0 0 0 4px var(--ui-focus-ring)}
.group-row details form{padding:12px;border:1px solid var(--border);border-radius:12px;background:var(--surface-2)}
.actions{align-items:stretch}
.actions form{display:flex;flex:0 1 auto;min-width:0}
.actions button{white-space:normal}
@media(max-width:600px){.grid{grid-template-columns:1fr}.admin-top{align-items:flex-start;flex-direction:column}.panel-card{padding:16px}.group-row{padding:14px}.actions form,.actions button{flex:1 1 100%;width:100%;box-sizing:border-box}.group-row details form{padding:10px}}
</style>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</head>
<body>
<main class="admin-shell">
<?php require __DIR__ . '/_navigation.php'; ?>
<section class="panel panel-card">
<div class="eyebrow">IBSng · Plan & Group Setup</div>
<h1 data-fa="افزودن پلن فروشگاه به گروه IBSng" data-en="Add Store Plan to IBSng Group">افزودن پلن فروشگاه به گروه IBSng</h1>
<p class="muted" data-fa="گروه‌ها در این بخش به‌صورت دستی ثبت می‌شوند و نام هر گروه باید دقیقاً با نام گروه در IBSng مطابقت داشته باشد." data-en="Groups are added manually in this section. Each group name must exactly match its name in IBSng.">نام گروه باید دقیقاً مطابق گروه موجود در IBSng وارد شود. در این بخش گروه‌ها به‌صورت دستی تعریف می‌شوند؛ دریافت خودکار فهرست گروه‌ها هنوز اضافه نشده است.</p>
<?php if ($message !== ''): ?><div class="notice ok" role="status" aria-live="polite"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err" role="alert" aria-live="assertive"><?= e($error) ?></div><?php endif; ?>
<?php if (!$servers): ?><div class="empty" data-fa="ابتدا از بخش سرورهای IBSng یک سرور اضافه کنید." data-en="Add an IBSng server first.">ابتدا از بخش سرورهای IBSng یک سرور اضافه کنید.</div><?php else: ?>
<form method="get">
<div class="field"><label for="server_id" data-fa="سرور IBSng" data-en="IBSng server">سرور IBSng</label><select id="server_id" name="server_id" onchange="this.form.submit()">
<?php foreach ($servers as $server): ?><option value="<?= (int)$server['id'] ?>"<?= (int)$server['id'] === $selectedServerId ? ' selected' : '' ?>><?= e((string)$server['name']) ?> · <?= e((string)$server['host']) ?></option><?php endforeach; ?>
</select></div>
</form>
<?php endif; ?>
</section>
<?php if ($selectedServerId > 0): ?>
<section class="panel panel-card">
<h2 data-ui-text="groups_add_plan">افزودن پلن</h2>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="add"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>">
<div class="grid">
<div class="field"><label for="plan_name" data-fa="نام پلن فروشگاه" data-en="Store plan name">نام پلن فروشگاه</label><input id="plan_name" name="plan_name" required maxlength="150" placeholder="مثلاً: یک ماهه" data-ui-placeholder="groups_plan_placeholder"></div>
<div class="field"><label for="group_name" data-fa="نام دقیق گروه IBSng" data-en="Exact IBSng group name">نام دقیق گروه IBSng</label><input id="group_name" name="group_name" dir="ltr" required maxlength="150" placeholder="نام گروه در IBSng" data-ui-placeholder="groups_group_placeholder"></div>
<div class="field"><label for="validity_days" data-fa="مدت اعتبار (روز)" data-en="Duration (Days)">مدت اعتبار (روز)</label><input id="validity_days" name="validity_days" type="number" min="1" max="36500" required value="30"></div>
</div>
<button class="button primary" type="submit" data-fa="ذخیره پلن" data-en="Save plan">ذخیره پلن</button>
</form>
</section>
<section class="panel panel-card">
<h2 data-fa="پلن‌های ثبت‌شده" data-en="Saved plans">پلن‌های ثبت‌شده</h2>
<?php if (!$groups): ?><div class="empty" data-fa="برای این سرور هنوز پلنی ثبت نشده است." data-en="No plans have been added for this server yet.">برای این سرور هنوز پلنی ثبت نشده است.</div><?php endif; ?>
<?php foreach ($groups as $group): ?>
<article class="group-row panel">
<div class="group-head"><strong><?= e((string)$group['plan_name']) ?></strong><span class="status-chip" data-ui-status="<?= (int)$group['is_active'] === 1 ? 'active' : 'inactive' ?>"><?= (int)$group['is_active'] === 1 ? 'فعال' : 'غیرفعال' ?></span></div>
<div class="muted" dir="ltr"><?= e((string)$group['group_name']) ?></div><div class="muted"><span data-fa="مدت اعتبار:" data-en="Duration:">مدت اعتبار:</span> <?= (int)$group['validity_days'] ?> <span data-fa="روز" data-en="days">روز</span></div>
<details><summary class="button" data-fa="ویرایش پلن" data-en="Edit plan">ویرایش پلن</summary>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$group['id'] ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>">
<div class="grid">
<div class="field"><label data-fa="نام پلن فروشگاه" data-en="Store plan name">نام پلن فروشگاه</label><input name="plan_name" required value="<?= e((string)$group['plan_name']) ?>"></div>
<div class="field"><label data-fa="نام دقیق گروه IBSng" data-en="Exact IBSng group name">نام دقیق گروه IBSng</label><input name="group_name" dir="ltr" required value="<?= e((string)$group['group_name']) ?>"></div>
<div class="field"><label data-fa="مدت اعتبار (روز)" data-en="Duration (Days)">مدت اعتبار (روز)</label><input name="validity_days" type="number" min="1" max="36500" required value="<?= (int)$group['validity_days'] ?>"></div>
</div><button class="button primary" type="submit" data-fa="ذخیره تغییرات" data-en="Save changes">ذخیره تغییرات</button>
</form></details>
<div class="actions">
<form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$group['id'] ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><button class="button" type="submit" data-fa="<?= (int)$group['is_active'] === 1 ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?>" data-en="<?= (int)$group['is_active'] === 1 ? 'Disable' : 'Enable' ?>"><?= (int)$group['is_active'] === 1 ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?></button></form>
<form method="post" data-confirm-fa="این پلن حذف شود؟" data-confirm-en="Delete this plan?"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$group['id'] ?>"><input type="hidden" name="server_id" value="<?= $selectedServerId ?>"><button class="button danger" type="submit" data-fa="حذف" data-en="Delete">حذف</button></form>
</div>
</article>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
</main>
</body>
</html>
