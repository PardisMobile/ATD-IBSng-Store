<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/StorefrontView.php';

$config = app_config();
$siteTitle = (string)($config['site_title'] ?? 'ATD IBSng Store');
$systems = [
    'android' => ['fa' => 'Android', 'en' => 'Android'],
    'ios' => ['fa' => 'iPhone / iOS', 'en' => 'iPhone / iOS'],
    'windows' => ['fa' => 'Windows', 'en' => 'Windows'],
    'macos' => ['fa' => 'macOS', 'en' => 'macOS'],
    'linux' => ['fa' => 'Linux', 'en' => 'Linux'],
];
$guide = ['intro' => 'روش اتصال و سیستم‌عامل موردنظر خود را انتخاب کنید.', 'methods' => []];
try {
    $stmt = Database::connection()->prepare("SELECT setting_value FROM app_settings WHERE setting_key='general_account_guide' LIMIT 1");
    $stmt->execute();
    $decoded = json_decode((string)$stmt->fetchColumn(), true);
    if (is_array($decoded)) $guide = array_merge($guide, $decoded);
} catch (Throwable $e) {
    error_log('General account guide could not be loaded: ' . $e->getMessage());
}
$methods = array_values(array_filter(is_array($guide['methods'] ?? null) ? $guide['methods'] : [], static fn($method): bool => is_array($method) && !empty($method['active']) && trim((string)($method['name'] ?? '')) !== ''));
$visibleSystems = [];
foreach ($methods as $method) {
    foreach ($systems as $key => $label) {
        $content = $method['systems'][$key] ?? null;
        if (is_array($content) && (trim((string)($content['content'] ?? '')) !== '' || trim((string)($content['image_url'] ?? '')) !== '' || trim((string)($content['software_url'] ?? '')) !== '' || trim((string)($content['download_url'] ?? '')) !== '')) {
            $visibleSystems[$key] = $label;
        }
    }
}
$validUrl = static function ($value): string {
    $value = trim((string)$value);
    if ($value === '') return '';
    $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
    return filter_var($value, FILTER_VALIDATE_URL) && in_array($scheme, ['http','https'], true) ? $value : '';
};
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title data-ui-title-key="title_guide">راهنمای استفاده | <?= e($siteTitle) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.guide{width:min(850px,calc(100% - 28px));margin:0 auto;padding:clamp(20px,4.5vw,38px);min-width:0;background:var(--surface);border:1px solid var(--border);border-radius:var(--ui-radius-lg,20px)}
.guide h1{margin:8px 0 14px;font-size:clamp(1.65rem,4vw,2.35rem);line-height:1.45;overflow-wrap:anywhere}
.guide>p{line-height:1.95}
.guide .muted{color:var(--muted)}
.guide section{position:relative;padding:clamp(16px,3vw,22px);margin:14px 0;border:1px solid var(--border);border-radius:var(--ui-radius-lg,18px);background:var(--surface-2);line-height:1.95;min-width:0;transition:border-color .18s ease,background-color .18s ease}
.guide section h2{font-size:clamp(1.05rem,2.5vw,1.25rem);line-height:1.6;margin:0 0 10px;overflow-wrap:anywhere}
.guide a{color:var(--accent,var(--blue));text-decoration:underline;text-underline-offset:3px}
.guide .guide-selectors{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:22px 0}
.guide .field{display:grid;gap:8px}.guide .field label{color:var(--muted)}
.guide select{width:100%;min-height:46px;padding:10px 12px;border:1px solid var(--border);border-radius:13px;background:var(--control-bg,var(--surface-2));color:var(--text);font:inherit}
.guide-content{white-space:pre-wrap;overflow-wrap:anywhere;line-height:2}
.guide-image{display:block;max-width:100%;height:auto;border-radius:14px;margin:14px 0;border:1px solid var(--border)}
.guide-links{display:flex;flex-wrap:wrap;gap:16px;margin-top:14px}
.guide-empty{padding:18px;border:1px dashed var(--border);border-radius:14px;color:var(--muted)}
@media(max-width:560px){.guide{width:calc(100% - 20px);border-radius:18px}.guide section{padding:15px}.guide .guide-selectors{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){.guide section{transition:none}}
</style>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</head>
<body>
<?php storefront_header($siteTitle, 'guide'); ?>
<main class="guide">
<h1 data-ui-text="guide_title">راهنمای استفاده از اکانت</h1>
<p class="muted"><?= e((string)($guide['intro'] ?? '')) ?></p>
<?php if (!$methods): ?>
<div class="guide-empty" data-fa="راهنمای اتصال هنوز توسط مدیریت منتشر نشده است." data-en="Connection instructions have not been published yet.">راهنمای اتصال هنوز توسط مدیریت منتشر نشده است.</div>
<?php else: ?>
<div class="guide-selectors">
<div class="field"><label for="guide-method" data-fa="روش اتصال" data-en="Connection method">روش اتصال</label><select id="guide-method"><option value="" data-fa="انتخاب روش اتصال" data-en="Select a connection method">انتخاب روش اتصال</option><?php foreach ($methods as $index => $method): ?><option value="<?= (int)$index ?>"><?= e((string)$method['name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="guide-system" data-fa="سیستم‌عامل یا دستگاه" data-en="Operating system or device">سیستم‌عامل یا دستگاه</label><select id="guide-system" disabled><option value="" data-fa="ابتدا روش اتصال را انتخاب کنید" data-en="Select a method first">ابتدا روش اتصال را انتخاب کنید</option></select></div>
</div>
<div id="guide-instructions" aria-live="polite"><div class="guide-empty" data-fa="ابتدا روش اتصال و سپس سیستم‌عامل یا دستگاه را انتخاب کنید." data-en="Choose a connection method, then an operating system or device.">ابتدا روش اتصال و سپس سیستم‌عامل یا دستگاه را انتخاب کنید.</div></div>
<script type="application/json" id="guide-data"><?= json_encode(['methods' => $methods, 'systems' => $systems], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
(function(){
 const data=JSON.parse(document.getElementById('guide-data').textContent);
 const method=document.getElementById('guide-method'),system=document.getElementById('guide-system'),output=document.getElementById('guide-instructions');
 const lang=()=>document.documentElement.lang==='en'?'en':'fa';
 const labels={android:{fa:'Android',en:'Android'},ios:{fa:'iPhone / iOS',en:'iPhone / iOS'},windows:{fa:'Windows',en:'Windows'},macos:{fa:'macOS',en:'macOS'},linux:{fa:'Linux',en:'Linux'}};
 const safeUrl=value=>{try{const u=new URL(value,window.location.href);return ['http:','https:'].includes(u.protocol)?u.href:''}catch(_){return ''}};
 const el=(tag,cls,text)=>{const node=document.createElement(tag);if(cls)node.className=cls;if(text)node.textContent=text;return node};
 function refreshSystems(){
  system.replaceChildren();system.disabled=true;
  const selected=data.methods[Number(method.value)];
  const placeholder=el('option','',lang()==='en'?'Select an operating system or device':'انتخاب سیستم‌عامل یا دستگاه');placeholder.value='';system.append(placeholder);
  if(!selected)return;
  Object.keys(labels).forEach(key=>{
   const item=selected.systems?.[key];if(!item||!(item.content||item.image_url||item.software_url||item.download_url))return;
   const option=el('option','',labels[key][lang()]);option.value=key;system.append(option);
  });
  system.disabled=system.options.length<2;
  if(system.disabled){render()}else{render()}
 }
 function render(){
  output.replaceChildren();
  const selected=data.methods[Number(method.value)],key=system.value,item=selected?.systems?.[key];
  if(!selected||!item){output.append(el('div','guide-empty',lang()==='en'?'Select a method and an available operating system.':'روش اتصال و یک سیستم‌عامل دارای آموزش را انتخاب کنید.'));return}
  const section=el('section','');
  section.append(el('h2','',selected.name+' · '+labels[key][lang()]));
  if(item.content)section.append(el('div','guide-content',item.content));
  const image=safeUrl(item.image_url||'');
  if(image){const img=document.createElement('img');img.className='guide-image';img.src=image;img.alt=lang()==='en'?'Connection instruction':'تصویر آموزشی اتصال';img.loading='lazy';section.append(img)}
  const links=el('div','guide-links','');
  const software=safeUrl(item.software_url||''),download=safeUrl(item.download_url||'');
  if(software){const a=el('a','',lang()==='en'?'Download required software':'دریافت نرم‌افزار موردنیاز');a.href=software;a.target='_blank';a.rel='noopener noreferrer';links.append(a)}
  if(download){const a=el('a','',item.download_label||(lang()==='en'?'Download guide file':'دریافت فایل راهنما'));a.href=download;a.target='_blank';a.rel='noopener noreferrer';links.append(a)}
  if(links.childNodes.length)section.append(links);
  output.append(section);
 }
 method.addEventListener('change',refreshSystems);system.addEventListener('change',render);
 document.addEventListener('atd:language-change',()=>{const selected=system.value;refreshSystems();system.value=selected;render()});
})();
</script>
<?php endif; ?>
<?php storefront_footer($siteTitle); ?>
</main>
</body>
</html>
