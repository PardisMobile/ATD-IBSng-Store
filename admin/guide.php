<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_admin();

$config = app_config();
$pdo = Database::connection();
$settingKey = 'general_account_guide';
$systems = [
    'android' => ['fa' => 'Android', 'en' => 'Android'],
    'ios' => ['fa' => 'iPhone / iOS', 'en' => 'iPhone / iOS'],
    'windows' => ['fa' => 'Windows', 'en' => 'Windows'],
    'macos' => ['fa' => 'macOS', 'en' => 'macOS'],
    'linux' => ['fa' => 'Linux', 'en' => 'Linux'],
];
$defaultGuide = [
    'intro' => 'روش اتصال و سیستم‌عامل موردنظر خود را انتخاب کنید.',
    'methods' => [[
        'name' => 'OpenVPN',
        'active' => 0,
        'systems' => [],
    ]],
];
$readGuide = static function () use ($pdo, $settingKey, $defaultGuide): array {
    $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1');
    $stmt->execute([$settingKey]);
    $raw = $stmt->fetchColumn();
    if (!is_string($raw) || $raw === '') return $defaultGuide;
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return $defaultGuide;
    $decoded['intro'] = is_string($decoded['intro'] ?? null) ? $decoded['intro'] : $defaultGuide['intro'];
    $decoded['methods'] = is_array($decoded['methods'] ?? null) ? array_values($decoded['methods']) : [];
    return $decoded;
};
$guide = $readGuide();
$error = '';
$message = isset($_GET['saved']) ? 'راهنمای عمومی ذخیره شد.' : '';

$cleanUrl = static function (string $value): string {
    $value = trim($value);
    if ($value === '') return '';
    if (strlen($value) > 2048 || !filter_var($value, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('یکی از نشانی‌ها معتبر نیست.');
    }
    $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('نشانی‌ها فقط می‌توانند با HTTP یا HTTPS باشند.');
    }
    return $value;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        try {
            $intro = trim((string)($_POST['intro'] ?? ''));
            if (strlen($intro) > 3000) throw new InvalidArgumentException('توضیح راهنما بیش از حد طولانی است.');
            $rawMethods = $_POST['methods'] ?? [];
            if (!is_array($rawMethods) || count($rawMethods) > 30) throw new InvalidArgumentException('فهرست روش‌های اتصال معتبر نیست.');
            $methods = [];
            foreach ($rawMethods as $rawMethod) {
                if (!is_array($rawMethod)) continue;
                $name = trim((string)($rawMethod['name'] ?? ''));
                if ($name === '') continue;
                if (strlen($name) > 100) throw new InvalidArgumentException('نام روش اتصال نباید بیشتر از ۱۰۰ کاراکتر باشد.');
                $rawSystems = is_array($rawMethod['systems'] ?? null) ? $rawMethod['systems'] : [];
                $methodSystems = [];
                foreach ($systems as $systemKey => $systemLabel) {
                    $raw = is_array($rawSystems[$systemKey] ?? null) ? $rawSystems[$systemKey] : [];
                    $body = trim((string)($raw['content'] ?? ''));
                    if (strlen($body) > 20000) throw new InvalidArgumentException('متن یکی از راهنماها بیش از حد طولانی است.');
                    $image = $cleanUrl((string)($raw['image_url'] ?? ''));
                    $software = $cleanUrl((string)($raw['software_url'] ?? ''));
                    $download = $cleanUrl((string)($raw['download_url'] ?? ''));
                    $downloadLabel = trim((string)($raw['download_label'] ?? ''));
                    if (strlen($downloadLabel) > 160) throw new InvalidArgumentException('عنوان فایل دانلودی بیش از حد طولانی است.');
                    if ($body !== '' || $image !== '' || $software !== '' || $download !== '') {
                        $methodSystems[$systemKey] = [
                            'content' => $body,
                            'image_url' => $image,
                            'software_url' => $software,
                            'download_url' => $download,
                            'download_label' => $downloadLabel,
                        ];
                    }
                }
                $methods[] = [
                    'name' => $name,
                    'active' => isset($rawMethod['active']) ? 1 : 0,
                    'systems' => $methodSystems,
                ];
            }
            $payload = json_encode(['intro' => $intro, 'methods' => $methods], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (strlen($payload) > 60000) throw new InvalidArgumentException('حجم محتوای راهنما بیش از ظرفیت ذخیره تنظیمات است؛ متن یا تعداد روش‌ها را کاهش دهید.');
            $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
            $stmt->execute([$settingKey, $payload]);
            header('Location: ' . app_url('admin/guide.php?saved=1'));
            exit;
        } catch (Throwable $e) {
            $error = $e instanceof InvalidArgumentException ? $e->getMessage() : 'ذخیره راهنما انجام نشد.';
            $guide = ['intro' => (string)($_POST['intro'] ?? ''), 'methods' => is_array($_POST['methods'] ?? null) ? array_values($_POST['methods']) : []];
        }
    }
}
$guideMethods = is_array($guide['methods'] ?? null) ? array_values($guide['methods']) : [];
if (!$guideMethods) $guideMethods = $defaultGuide['methods'];
$esc = static fn($value): string => e((string)$value);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title data-ui-title-key="title_admin_guide">مدیریت راهنمای استفاده | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.guide-admin-method{padding:18px;margin:16px 0;border:1px solid var(--border);border-radius:var(--ui-radius-lg,18px);background:var(--surface-2)}
.guide-admin-method-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.guide-admin-method .system-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.guide-admin-method textarea{min-height:120px;resize:vertical}
.guide-admin-method .field-full{grid-column:1/-1}
.guide-admin-method .check-toggle{display:flex;align-items:center;gap:8px}
.guide-admin-note{color:var(--muted);line-height:1.9}
@media(max-width:650px){.guide-admin-method .system-fields{grid-template-columns:1fr}}
</style>
</head>
<body>
<main class="admin-shell">
<?php require __DIR__ . '/_navigation.php'; ?>
<section class="panel panel-card">
<div class="eyebrow" data-fa="محتوای عمومی مشتریان" data-en="Customer-facing general content">محتوای عمومی مشتریان</div>
<h1 data-fa="راهنمای استفاده از اکانت" data-en="Account usage guide">راهنمای استفاده از اکانت</h1>
<p class="guide-admin-note" data-fa="یک راهنمای واحد برای همه اکانت‌هاست. روش‌های اتصال را تعریف کنید و برای هرکدام، آموزش سیستم‌عامل‌های موردنظر را وارد کنید. هیچ راهنمایی به محصول یا نوع سرویس فروشگاه متصل نمی‌شود." data-en="One shared guide for all accounts. Define connection methods and add instructions for the operating systems you need. Guides are not linked to products or store service types.">یک راهنمای واحد برای همه اکانت‌هاست. روش‌های اتصال را تعریف کنید و برای هرکدام، آموزش سیستم‌عامل‌های موردنظر را وارد کنید. هیچ راهنمایی به محصول یا نوع سرویس فروشگاه متصل نمی‌شود.</p>
<?php if ($message !== ''): ?><div class="notice" role="status"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
<div class="field field-full">
<label for="guide-intro" data-fa="توضیح ابتدای راهنما" data-en="Guide introduction">توضیح ابتدای راهنما</label>
<textarea id="guide-intro" name="intro" rows="3"><?= $esc($guide['intro'] ?? '') ?></textarea>
</div>
<div class="guide-admin-method-head"><h2 data-fa="روش‌های اتصال" data-en="Connection methods">روش‌های اتصال</h2><button class="button" type="button" id="add-guide-method" data-fa="افزودن روش اتصال" data-en="Add connection method">افزودن روش اتصال</button></div>
<div id="guide-method-list">
<?php foreach ($guideMethods as $index => $method): $method = is_array($method) ? $method : []; ?>
<article class="guide-admin-method" data-guide-method>
<div class="guide-admin-method-head">
<div class="field" style="flex:1;min-width:220px"><label data-fa="نام روش اتصال" data-en="Connection method name">نام روش اتصال</label><input name="methods[<?= (int)$index ?>][name]" maxlength="100" value="<?= $esc($method['name'] ?? '') ?>" placeholder="OpenVPN / L2TP / Cisco"></div>
<label class="check-toggle"><input type="checkbox" name="methods[<?= (int)$index ?>][active]" value="1"<?= !empty($method['active']) ? ' checked' : '' ?>> <span data-fa="فعال و قابل نمایش برای مشتری" data-en="Enabled and visible to customers">فعال و قابل نمایش برای مشتری</span></label>
<button class="button" type="button" data-remove-guide-method data-fa="حذف روش" data-en="Remove method">حذف روش</button>
</div>
<?php foreach ($systems as $systemKey => $labels): $system = is_array($method['systems'][$systemKey] ?? null) ? $method['systems'][$systemKey] : []; ?>
<fieldset class="form-section">
<legend><?= e($labels['fa']) ?></legend>
<div class="system-fields">
<div class="field field-full"><label data-fa="مراحل و توضیحات" data-en="Instructions">مراحل و توضیحات</label><textarea name="methods[<?= (int)$index ?>][systems][<?= e($systemKey) ?>][content]" rows="5"><?= $esc($system['content'] ?? '') ?></textarea></div>
<div class="field"><label data-fa="نشانی تصویر آموزشی (اختیاری)" data-en="Instruction image URL (optional)">نشانی تصویر آموزشی (اختیاری)</label><input type="url" name="methods[<?= (int)$index ?>][systems][<?= e($systemKey) ?>][image_url]" value="<?= $esc($system['image_url'] ?? '') ?>" placeholder="https://..."></div>
<div class="field"><label data-fa="لینک دریافت نرم‌افزار (اختیاری)" data-en="Software link (optional)">لینک دریافت نرم‌افزار (اختیاری)</label><input type="url" name="methods[<?= (int)$index ?>][systems][<?= e($systemKey) ?>][software_url]" value="<?= $esc($system['software_url'] ?? '') ?>" placeholder="https://..."></div>
<div class="field"><label data-fa="لینک فایل راهنما (اختیاری)" data-en="Guide file URL (optional)">لینک فایل راهنما (اختیاری)</label><input type="url" name="methods[<?= (int)$index ?>][systems][<?= e($systemKey) ?>][download_url]" value="<?= $esc($system['download_url'] ?? '') ?>" placeholder="https://..."></div>
<div class="field"><label data-fa="عنوان لینک فایل" data-en="Download link label">عنوان لینک فایل</label><input name="methods[<?= (int)$index ?>][systems][<?= e($systemKey) ?>][download_label]" maxlength="160" value="<?= $esc($system['download_label'] ?? '') ?>"></div>
</div>
</fieldset>
<?php endforeach; ?>
</article>
<?php endforeach; ?>
</div>
<div class="actions"><button class="button primary" type="submit" data-fa="ذخیره راهنمای عمومی" data-en="Save general guide">ذخیره راهنمای عمومی</button><a class="button" href="<?= e(app_url('guide.php')) ?>" target="_blank" rel="noopener" data-fa="مشاهده صفحه راهنما" data-en="Open customer guide">مشاهده صفحه راهنما</a></div>
</form>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
</main>
<script>
(function(){
 const list=document.getElementById('guide-method-list');
 const add=document.getElementById('add-guide-method');
 if(!list||!add)return;
 let nextIndex=<?= count($guideMethods) ?>;
 const systems=[
  ['android','Android'],['ios','iPhone / iOS'],['windows','Windows'],['macos','macOS'],['linux','Linux']
 ];
 function field(label,name,type){
  const wrap=document.createElement('div');wrap.className='field';
  const caption=document.createElement('label');caption.textContent=label;
  const input=document.createElement(type==='textarea'?'textarea':'input');
  if(type!=='textarea')input.type=type||'text';
  input.name=name;
  wrap.append(caption,input);return wrap;
 }
 add.addEventListener('click',()=>{
  const i=nextIndex++;
  const article=document.createElement('article');article.className='guide-admin-method';article.dataset.guideMethod='';
  const head=document.createElement('div');head.className='guide-admin-method-head';
  const name=field('نام روش اتصال','methods['+i+'][name]','text');name.style.flex='1';name.style.minWidth='220px';
  const label=document.createElement('label');label.className='check-toggle';
  const check=document.createElement('input');check.type='checkbox';check.name='methods['+i+'][active]';check.value='1';
  const labelText=document.createElement('span');labelText.textContent='فعال و قابل نمایش برای مشتری';label.append(check,labelText);
  const remove=document.createElement('button');remove.type='button';remove.className='button';remove.textContent='حذف روش';remove.dataset.removeGuideMethod='';
  head.append(name,label,remove);article.append(head);
  systems.forEach(([key,title])=>{
   const fs=document.createElement('fieldset');fs.className='form-section';
   const legend=document.createElement('legend');legend.textContent=title;fs.append(legend);
   const grid=document.createElement('div');grid.className='system-fields';
   const body=field('مراحل و توضیحات','methods['+i+'][systems]['+key+'][content]','textarea');body.classList.add('field-full');body.querySelector('textarea').rows=5;
   grid.append(body,
    field('نشانی تصویر آموزشی (اختیاری)','methods['+i+'][systems]['+key+'][image_url]','url'),
    field('لینک دریافت نرم‌افزار (اختیاری)','methods['+i+'][systems]['+key+'][software_url]','url'),
    field('لینک فایل راهنما (اختیاری)','methods['+i+'][systems]['+key+'][download_url]','url'),
    field('عنوان لینک فایل','methods['+i+'][systems]['+key+'][download_label]','text'));
   fs.append(grid);article.append(fs);
  });
  list.append(article);
 });
 list.addEventListener('click',event=>{
  const button=event.target.closest('[data-remove-guide-method]');
  if(button)button.closest('[data-guide-method]')?.remove();
 });
})();
</script>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</body></html>
