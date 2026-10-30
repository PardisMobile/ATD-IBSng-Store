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
<nav><a class="button" href="<?= e(app_url('admin/ibsng-servers.php')) ?>">سرورهای IBSng</a><button class="button lang-toggle" id="langToggle" type="button" aria-label="Switch language">English</button></nav>
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
<script>
(function(){
const pairs={"ATD IBSng Store · مدیریت":"ATD IBSng Store · Admin","داشبورد":"Dashboard","سرورهای IBSng":"IBSng Servers","نگاشت پلن/گروه":"Plan / Group Mapping","بازگشت به فروشگاه":"Back to Store","افزودن سرور IBSng":"Add IBSng Server","مدیریت سرورهای IBSng":"IBSng Server Management","نام سرور":"Server Name","Host":"Host","پورت":"Port","ISP name":"ISP Name","نام کاربری":"Username","رمز عبور":"Password","تست اتصال و ذخیره تغییرات":"Test Connection & Save Changes","Test Connection":"Test Connection","انتخاب به‌عنوان پیش‌فرض":"Set as Default","غیرفعال‌سازی":"Disable","فعال‌سازی":"Enable","حذف":"Delete","حذف سرور":"Delete Server","ویرایش تنظیمات":"Edit Settings","نام کاربری جدید (خالی = حفظ قبلی)":"New Username (blank = keep current)","رمز جدید (خالی = حفظ قبلی)":"New Password (blank = keep current)","درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.":"Invalid request. Please refresh the page.","نام، Host، نام کاربری و رمز عبور IBSng الزامی است.":"Server name, host, username and IBSng password are required.","پورت باید 80 یا 443 باشد.":"Port must be 80 or 443.","اتصال IBSng با موفقیت تأیید و سرور ذخیره شد.":"IBSng connection verified and server saved.","سرور موردنظر پیدا نشد.":"The selected server was not found.","تنظیمات سرور به‌روزرسانی شد و تست اتصال موفق بود.":"Server settings updated; connection test succeeded.","اتصال موفق است؛ ورود مدیر IBSng تأیید شد.":"Connection succeeded; IBSng admin authentication verified.","برای انتخاب سرور پیش‌فرض، ابتدا آن را فعال کنید.":"Activate the server before setting it as default.","نگاشت پلن و گروه IBSng":"IBSng Plan / Group Mapping","نگاشت پلن فروشگاه به گروه IBSng":"Store Plan to IBSng Group Mapping","نام گروه باید دقیقاً مطابق گروه موجود در IBSng وارد شود. در این بخش نگاشت به‌صورت دستی تعریف می‌شود؛ دریافت خودکار فهرست گروه‌ها هنوز اضافه نشده است.":"Enter the exact existing IBSng group name. Mapping is manual; automatic group-list fetching is not implemented.","نام پلن":"Plan Name","نام دقیق گروه IBSng":"Exact IBSng Group Name","نام دقیق گروه":"Exact Group Name","مثلاً: یک ماهه":"For example: One month","نام گروه در IBSng":"IBSng group name","ثبت نگاشت":"Save Mapping","نگاشت‌های ثبت‌شده":"Saved Mappings","ویرایش نگاشت":"Edit Mapping","ذخیره تغییرات":"Save Changes","این نگاشت حذف شود؟":"Delete this mapping?","نگاشت پلن فروشگاه به گروه IBSng اضافه شد.":"Store plan to IBSng group mapping added.","نگاشت پلن و گروه IBSng ذخیره شد.":"Plan and IBSng group mapping saved.","وضعیت نگاشت تغییر کرد.":"Mapping status changed.","نگاشت حذف شد.":"Mapping deleted.","برای این سرور هنوز نگاشتی ثبت نشده است.":"No mappings have been added for this server yet.","ابتدا از بخش سرورهای IBSng یک سرور اضافه کنید.":"Add an IBSng server first.","سرور، نام پلن و نام دقیق گروه IBSng الزامی هستند.":"Server, plan name and exact IBSng group name are required.","نام پلن و نام دقیق گروه IBSng الزامی هستند.":"Plan name and exact IBSng group name are required.","این نام پلن یا گروه برای همین سرور قبلاً ثبت شده یا ذخیره‌سازی با خطا مواجه شد.":"This plan or group name already exists for this server, or saving failed.","این گروه به محصول/پلن فروشگاه متصل است؛ ابتدا نگاشت محصول را تغییر دهید.":"This group is assigned to a Store product/plan. Change the product mapping first.","نگاشت موردنظر پیدا نشد.":"The selected mapping was not found.","عملیات نامعتبر است.":"Invalid operation.","عملیات انجام نشد.":"The operation could not be completed.","سرور IBSng":"IBSng Server","Plan Name (نام پلن فروشگاه)":"Plan Name","IBSng Group Name (نام دقیق گروه)":"IBSng Group Name"};
const reverse={"ATD IBSng Store · Admin":"ATD IBSng Store · مدیریت","Dashboard":"داشبورد","IBSng Servers":"سرورهای IBSng","Plan / Group Mapping":"نگاشت پلن/گروه","Back to Store":"بازگشت به فروشگاه","Add IBSng Server":"افزودن سرور IBSng","IBSng Server Management":"مدیریت سرورهای IBSng","Server Name":"نام سرور","Host":"Host","Port":"پورت","ISP Name":"ISP name","Username":"نام کاربری","Password":"رمز عبور","Test Connection & Save Changes":"تست اتصال و ذخیره تغییرات","Test Connection":"Test Connection","Set as Default":"انتخاب به‌عنوان پیش‌فرض","Disable":"غیرفعال‌سازی","Enable":"فعال‌سازی","Delete":"حذف","Delete Server":"حذف سرور","Edit Settings":"ویرایش تنظیمات","New Username (blank = keep current)":"نام کاربری جدید (خالی = حفظ قبلی)","New Password (blank = keep current)":"رمز جدید (خالی = حفظ قبلی)","Invalid request. Please refresh the page.":"درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.","Server name, host, username and IBSng password are required.":"نام، Host، نام کاربری و رمز عبور IBSng الزامی است.","Port must be 80 or 443.":"پورت باید 80 یا 443 باشد.","IBSng connection verified and server saved.":"اتصال IBSng با موفقیت تأیید و سرور ذخیره شد.","The selected server was not found.":"سرور موردنظر پیدا نشد.","Server settings updated; connection test succeeded.":"تنظیمات سرور به‌روزرسانی شد و تست اتصال موفق بود.","Connection succeeded; IBSng admin authentication verified.":"اتصال موفق است؛ ورود مدیر IBSng تأیید شد.","Activate the server before setting it as default.":"برای انتخاب سرور پیش‌فرض، ابتدا آن را فعال کنید.","IBSng Plan / Group Mapping":"نگاشت پلن و گروه IBSng","Store Plan to IBSng Group Mapping":"نگاشت پلن فروشگاه به گروه IBSng","Enter the exact existing IBSng group name. Mapping is manual; automatic group-list fetching is not implemented.":"نام گروه باید دقیقاً مطابق گروه موجود در IBSng وارد شود. در این بخش نگاشت به‌صورت دستی تعریف می‌شود؛ دریافت خودکار فهرست گروه‌ها هنوز اضافه نشده است.","Plan Name":"Plan Name (نام پلن فروشگاه)","Exact IBSng Group Name":"نام دقیق گروه IBSng","Exact Group Name":"نام دقیق گروه","For example: One month":"مثلاً: یک ماهه","IBSng group name":"نام گروه در IBSng","Save Mapping":"ثبت نگاشت","Saved Mappings":"نگاشت‌های ثبت‌شده","Edit Mapping":"ویرایش نگاشت","Save Changes":"ذخیره تغییرات","Delete this mapping?":"این نگاشت حذف شود؟","Store plan to IBSng group mapping added.":"نگاشت پلن فروشگاه به گروه IBSng اضافه شد.","Plan and IBSng group mapping saved.":"نگاشت پلن و گروه IBSng ذخیره شد.","Mapping status changed.":"وضعیت نگاشت تغییر کرد.","Mapping deleted.":"نگاشت حذف شد.","No mappings have been added for this server yet.":"برای این سرور هنوز نگاشتی ثبت نشده است.","Add an IBSng server first.":"ابتدا از بخش سرورهای IBSng یک سرور اضافه کنید.","Server, plan name and exact IBSng group name are required.":"سرور، نام پلن و نام دقیق گروه IBSng الزامی هستند.","Plan name and exact IBSng group name are required.":"نام پلن و نام دقیق گروه IBSng الزامی هستند.","This plan or group name already exists for this server, or saving failed.":"این نام پلن یا گروه برای همین سرور قبلاً ثبت شده یا ذخیره‌سازی با خطا مواجه شد.","This group is assigned to a Store product/plan. Change the product mapping first.":"این گروه به محصول/پلن فروشگاه متصل است؛ ابتدا نگاشت محصول را تغییر دهید.","The selected mapping was not found.":"نگاشت موردنظر پیدا نشد.","Invalid operation.":"عملیات نامعتبر است.","The operation could not be completed.":"عملیات انجام نشد.","IBSng Server":"سرور IBSng","IBSng Group Name":"IBSng Group Name (نام دقیق گروه)"};
const root=document.documentElement;
const button=document.getElementById('langToggle');
function walk(node,map){
 if(node.nodeType===Node.TEXT_NODE){const raw=node.nodeValue||'',t=raw.trim();if(map[t])node.nodeValue=raw.replace(t,map[t]);return;}
 if(node.nodeType!==Node.ELEMENT_NODE)return;
 ['placeholder','title','aria-label'].forEach(a=>{const v=node.getAttribute(a);if(v&&map[v])node.setAttribute(a,map[v]);});
 if((node.matches('input[type=submit],input[type=button]'))&&node.value&&map[node.value])node.value=map[node.value];
 node.childNodes.forEach(n=>walk(n,map));
}
function setLang(lang){walk(document.body,lang==='en'?pairs:reverse);root.lang=lang;root.dir=lang==='fa'?'rtl':'ltr';
 document.body.style.fontFamily=lang==='fa'?'Vazirmatn,Tahoma,sans-serif':'"Segoe UI",Arial,sans-serif';
 if(button)button.textContent=lang==='en'?'فارسی':'English';
 try{localStorage.setItem('atd-ibsng-language',lang)}catch(e){}
}
let lang='en';try{lang=localStorage.getItem('atd-ibsng-language')||'en'}catch(e){}
if(button)button.addEventListener('click',()=>setLang(root.lang==='fa'?'en':'fa'));
setLang(lang);
})();
</script>
</body>
</html>
