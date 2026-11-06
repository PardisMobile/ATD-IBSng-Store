<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/AdminAuth.php';
require_once dirname(__DIR__) . '/app/Pricing.php';
require_admin();

$config = app_config();
$pdo = Database::connection();
$renewalColumnCheck = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
);
$renewalColumnCheck->execute(['ibsng_groups', 'validity_days']);
$validityColumnReady = (int)$renewalColumnCheck->fetchColumn() === 1;
$renewalColumnCheck->execute(['products', 'duration_seconds']);
$legacyDurationColumnPresent = (int)$renewalColumnCheck->fetchColumn() === 1;
$renewalColumnCheck->execute(['products', 'ibsng_validity_days']);
$legacyProductValidityPresent = (int)$renewalColumnCheck->fetchColumn() === 1;
$renewalColumnCheck->execute(['orders', 'duration_seconds_snapshot']);
$legacyOrderSnapshotPresent = (int)$renewalColumnCheck->fetchColumn() === 1;
if (!$validityColumnReady || $legacyProductValidityPresent || $legacyDurationColumnPresent || $legacyOrderSnapshotPresent) {
    header('Location: ' . app_url('admin/ibsng-renewal-migrate.php'));
    exit;
}
$message = '';
$error = '';
$editId = (int)($_GET['edit'] ?? 0);

function product_post_int(string $key, int $default = 0): int
{
    $value = filter_var($_POST[$key] ?? $default, FILTER_VALIDATE_INT);
    return $value === false ? -1 : (int)$value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_valid((string)($_POST['csrf'] ?? ''))) {
        $error = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id = product_post_int('id');
        try {
            if ($action === 'save') {
                $name = trim((string)($_POST['name'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                $price = product_post_int('price_minor');
                $priceUsdtRaw = trim((string)($_POST['price_usdt'] ?? ''));
                $priceUsdt = null;
                if ($priceUsdtRaw !== '') {
                    if (!preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\\.[0-9]{1,8})?$/D', $priceUsdtRaw) || (float)$priceUsdtRaw <= 0) {
                        throw new InvalidArgumentException('قیمت USDT باید عدد مثبت با حداکثر ۸ رقم اعشار باشد.');
                    }
                    $priceUsdt = $priceUsdtRaw;
                }
                $quota = trim((string)($_POST['quota_amount'] ?? ''));
                $quotaUnit = (string)($_POST['quota_unit'] ?? 'GB');
                $currency = strtoupper(trim((string)($_POST['currency'] ?? 'IRR')));
                $sortOrder = product_post_int('sort_order');
                $active = isset($_POST['is_active']) ? 1 : 0;
                $rawMappings = $_POST['mappings'] ?? [];
                if (!is_array($rawMappings)) throw new InvalidArgumentException('نگاشت‌های IBSng نامعتبر هستند.');
                $mappingPairs = [];
                foreach ($rawMappings as $rawMapping) {
                    if (!is_array($rawMapping)) continue;
                    $serverId = filter_var($rawMapping['server_id'] ?? null, FILTER_VALIDATE_INT);
                    $groupId = filter_var($rawMapping['ibsng_group_id'] ?? null, FILTER_VALIDATE_INT);
                    if (!$serverId || !$groupId || $serverId < 1 || $groupId < 1) continue;
                    if (isset($mappingPairs[$serverId])) throw new InvalidArgumentException('برای هر سرور فقط یک گروه IBSng در یک محصول می‌توان ثبت کرد.');
                    $mappingPairs[$serverId] = $groupId;
                }
                if (!$mappingPairs) throw new InvalidArgumentException('حداقل یک سرور و گروه IBSng برای محصول انتخاب کنید.');

                if ($name === '' || (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name)) > 180) {
                    throw new InvalidArgumentException('نام محصول الزامی است و نباید بیشتر از ۱۸۰ کاراکتر باشد.');
                }
                if ($price < 0 || $sortOrder < 0) throw new InvalidArgumentException('قیمت یا ترتیب نمایش معتبر نیست.');
                if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                    throw new InvalidArgumentException('واحد پول باید کد سه‌حرفی مانند IRR باشد.');
                }
                $quotaBytes = null;
                if (!in_array($quotaUnit, ['MB', 'GB', 'TB'], true)) {
                    throw new InvalidArgumentException('واحد حجم انتخاب‌شده معتبر نیست.');
                }
                if ($quota !== '') {
                    if (!preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\\.[0-9]{1,9})?$/D', $quota)) {
                        throw new InvalidArgumentException('حجم باید یک عدد مثبت یا صفر باشد.');
                    }
                    $amount = (float)$quota;
                    $multiplier = $quotaUnit === 'TB' ? 1099511627776 : ($quotaUnit === 'GB' ? 1073741824 : 1048576);
                    $bytes = $amount * $multiplier;
                    if (!is_finite($bytes) || $bytes > PHP_INT_MAX) {
                        throw new InvalidArgumentException('حجم واردشده بیش از حد مجاز است.');
                    }
                    $quotaBytes = (int)round($bytes);
                }

                $pdo->beginTransaction();
                $validateMapping = $pdo->prepare('SELECT g.id FROM ibsng_groups g JOIN ibsng_servers s ON s.id=g.server_id WHERE g.id=? AND g.server_id=? AND g.is_active=1 AND s.is_active=1');
                foreach ($mappingPairs as $serverId => $groupId) {
                    $validateMapping->execute([$groupId, $serverId]);
                    if (!$validateMapping->fetchColumn()) throw new InvalidArgumentException('یکی از نگاشت‌های گروه IBSng فعال نیست یا به سرور انتخاب‌شده تعلق ندارد.');
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE products SET name=?,description=?,price_minor=?,price_usdt=?,currency=?,quota_bytes=?,is_active=?,sort_order=? WHERE id=?');
                    $stmt->execute([$name, $description !== '' ? $description : null, $price, $priceUsdt, $currency, $quotaBytes, $active, $sortOrder, $id]);
                    $check = $pdo->prepare('SELECT id FROM products WHERE id=?');
                    $check->execute([$id]);
                    if (!$check->fetchColumn()) {
                        throw new RuntimeException('محصول موردنظر پیدا نشد.');
                    }
                    $productId = $id;
                } else {
                    $stmt = $pdo->prepare('INSERT INTO products (name,description,price_minor,price_usdt,currency,quota_bytes,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?)');
                    $stmt->execute([$name, $description !== '' ? $description : null, $price, $priceUsdt, $currency, $quotaBytes, $active, $sortOrder]);
                    $productId = (int)$pdo->lastInsertId();
                }

                $pdo->prepare('DELETE FROM product_server_groups WHERE product_id=?')->execute([$productId]);
                $insertMapping = $pdo->prepare('INSERT INTO product_server_groups (product_id,server_id,ibsng_group_id) VALUES (?,?,?)');
                foreach ($mappingPairs as $serverId => $groupId) $insertMapping->execute([$productId, $serverId, $groupId]);
                $pdo->commit();
                header('Location: ' . app_url('admin/products.php?saved=1'));
                exit;
            }

            if ($action === 'toggle') {
                $stmt = $pdo->prepare('UPDATE products SET is_active=IF(is_active=1,0,1) WHERE id=?');
                $stmt->execute([$id]);
                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('محصول موردنظر پیدا نشد یا تغییری اعمال نشد.');
                }
                $message = 'وضعیت نمایش محصول تغییر کرد.';
            } elseif ($action === 'delete') {
                $check = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE product_id=?');
                $check->execute([$id]);
                if ((int)$check->fetchColumn() > 0) {
                    throw new RuntimeException('این محصول در سفارش‌های قبلی استفاده شده است؛ به‌جای حذف، آن را غیرفعال کنید.');
                }
                $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
                $message = 'محصول حذف شد.';
            } elseif ($action !== 'save') {
                throw new RuntimeException('عملیات نامعتبر است.');
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'ذخیره‌سازی انجام نشد؛ نام محصول یا نگاشت انتخابی ممکن است تکراری یا نامعتبر باشد.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e instanceof InvalidArgumentException || $e instanceof RuntimeException
                ? $e->getMessage()
                : 'عملیات انجام نشد.';
        }
    }
}

if (isset($_GET['saved'])) {
    $message = 'محصول و نگاشت‌های IBSng ذخیره شدند.';
}
$servers = $pdo->query('SELECT id,name,host FROM ibsng_servers WHERE is_active=1 ORDER BY is_default DESC,id')->fetchAll();
$groups = $pdo->query('SELECT g.id,g.server_id,g.plan_name,g.group_name,s.name AS server_name FROM ibsng_groups g JOIN ibsng_servers s ON s.id=g.server_id WHERE g.is_active=1 AND s.is_active=1 ORDER BY s.is_default DESC,s.id,g.plan_name')->fetchAll();
$product = null;
$productMappings = [];
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id=? LIMIT 1');
    $stmt->execute([$editId]);
    $product = $stmt->fetch() ?: null;
    if (!$product) {
        $error = 'محصول موردنظر پیدا نشد.';
    } else {
        $mappingStmt = $pdo->prepare('SELECT server_id,ibsng_group_id FROM product_server_groups WHERE product_id=? ORDER BY server_id');
        $mappingStmt->execute([$editId]);
        $productMappings = $mappingStmt->fetchAll();
    }
}
if (!$productMappings) $productMappings = [['server_id' => '', 'ibsng_group_id' => '']];
$priceDisplayUnit = store_price_display_unit($pdo);
$products = $pdo->query("SELECT p.*,GROUP_CONCAT(CONCAT(s.name, ' / ', g.plan_name, ' (', g.group_name, ')') ORDER BY s.is_default DESC,s.id SEPARATOR '، ') AS mapping_labels FROM products p LEFT JOIN product_server_groups m ON m.product_id=p.id LEFT JOIN ibsng_groups g ON g.id=m.ibsng_group_id LEFT JOIN ibsng_servers s ON s.id=m.server_id GROUP BY p.id ORDER BY p.sort_order,p.id DESC")->fetchAll();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title data-ui-title-key="title_admin_products">مدیریت محصولات | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_asset_url('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(app_asset_url('assets/css/unified.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.admin-top{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:24px 0;flex-wrap:wrap}.panel-card{padding:clamp(18px,3vw,26px);margin:20px 0;min-width:0}
.field{display:grid;gap:8px;margin:14px 0;min-width:0}.field label{color:var(--muted);font-size:.9rem}
.field input,.field select,.field textarea{width:100%;min-width:0;min-height:var(--ui-control-height,46px);padding:12px 14px;border:1px solid var(--border);border-radius:var(--ui-radius-md,12px);background:var(--control-bg,var(--surface-2));color:var(--text);font:inherit}
.field input:focus-visible,.field select:focus-visible,.field textarea:focus-visible{outline:2px solid var(--accent);outline-offset:2px;box-shadow:0 0 0 3px var(--ui-focus-ring)}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}
.notice{padding:13px 15px;border-radius:var(--ui-radius-md,12px);margin:14px 0;line-height:1.8}.ok{background:color-mix(in srgb,var(--success,var(--green)) 10%,transparent);border:1px solid color-mix(in srgb,var(--success,var(--green)) 30%,transparent)}.err{background:color-mix(in srgb,var(--danger,#ff7180) 10%,transparent);border:1px solid color-mix(in srgb,var(--danger,#ff7180) 30%,transparent)}
.product-row{padding:clamp(16px,3vw,20px);margin:14px 0;min-width:0}.row-head{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.actions button{font:inherit;cursor:pointer}.muted{overflow-wrap:anywhere}.empty{padding:24px;text-align:center;color:var(--muted)}
/* Product editor and saved-plan cards: clearer grouping without changing form behavior. */
.product-row{border:1px solid var(--border);border-radius:var(--ui-radius-lg,18px);background:var(--surface-2);transition:border-color .18s ease,transform .18s ease;}
.product-row:hover{border-color:color-mix(in srgb,var(--accent) 42%,var(--border));transform:translateY(-1px)}
.product-row .row-head{align-items:center;margin-bottom:8px}
.product-row .row-head strong{font-size:1.05rem;overflow-wrap:anywhere}
.product-row>div:not(.row-head){line-height:1.85;overflow-wrap:anywhere}
.product-row .actions form{display:flex;min-width:0}
.product-row .actions .button{min-height:44px;white-space:normal}
.mapping-row{padding:14px;margin:12px 0;border:1px solid var(--border);border-radius:var(--ui-radius-md,14px);background:var(--surface-2);align-items:end!important}
.mapping-row .field{margin:6px 0;min-width:0}
.mapping-row .field>div{min-width:0;flex-wrap:wrap}
.mapping-row select{min-width:0;flex:1 1 180px}
#add-mapping{margin-top:8px;min-height:44px}
@media(max-width:680px){.grid{grid-template-columns:minmax(0,1fr)}.mapping-row{grid-template-columns:minmax(0,1fr)!important}.mapping-row .field>div{align-items:stretch}.mapping-row .field>div .button{flex:0 0 auto}.product-row .actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr))}.product-row .actions form,.product-row .actions form .button{width:100%}}
@media(prefers-reduced-motion:reduce){.product-row{transition:none}.product-row:hover{transform:none}}
@media(max-width:600px){.grid{grid-template-columns:1fr}.admin-shell{width:calc(100% - 24px)}.admin-top{align-items:flex-start;flex-direction:column}.actions>*{flex:1 1 auto}}
</style>
</head>
<body>
<main class="admin-shell">
<?php require __DIR__ . '/_navigation.php'; ?>
<section class="panel panel-card">
<div class="eyebrow">Store · Catalog</div><h1 data-ui-text="products_title">مدیریت محصولات و پلن‌ها</h1>
<p class="muted" data-ui-text="products_intro">هر محصول می‌تواند به یک یا چند سرور و گروه IBSng متناظر با آن‌ها متصل شود. مدت اعتبار محصول و تمدید IBSng بر حسب روز ثبت می‌شود؛ قیمت بر حسب کوچک‌ترین واحد پول و حجم را می‌توانید بر حسب MB، GB یا TB وارد کنید؛ مقدار نهایی در پایگاه داده بر حسب بایت ذخیره می‌شود.</p>
<?php if ($message !== ''): ?><div class="notice ok" role="status" aria-live="polite"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err" role="alert" aria-live="assertive"><?= e($error) ?></div><?php endif; ?>
<?php if (!$servers || !$groups): ?><div class="notice err" data-ui-text="products_prerequisite">برای تعریف محصول ابتدا یک سرور فعال و سپس یک نگاشت گروه فعال در بخش IBSng ایجاد کنید.</div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($product['id'] ?? 0) ?>">
<fieldset class="form-section"><legend data-ui-text="products_section_basic">اطلاعات پایه محصول</legend>
<div class="grid">
<div class="field"><label for="name" data-ui-text="products_name">نام محصول</label><input id="name" name="name" maxlength="180" required value="<?= e((string)($product['name'] ?? '')) ?>"></div>
<div class="field"><label for="description" data-ui-text="products_description">توضیحات</label><textarea id="description" name="description" rows="3"><?= e((string)($product['description'] ?? '')) ?></textarea></div>
</div>
</fieldset>
<fieldset class="form-section"><legend data-ui-text="products_section_pricing">قیمت و ارز</legend>
<div class="grid">
<div class="field"><label for="price_minor" data-ui-text="products_base_price">قیمت پایه (کوچک‌ترین واحد پولِ ارز انتخابی)</label><input id="price_minor" name="price_minor" type="text" inputmode="numeric" autocomplete="off" data-numeric="integer" required value="<?= e((string)($product['price_minor'] ?? 0)) ?>"></div>
<div class="field"><label for="price_usdt" data-ui-text="products_usdt_price">قیمت مستقل USDT (اختیاری)</label><input id="price_usdt" name="price_usdt" type="text" inputmode="decimal" autocomplete="off" data-numeric="decimal" data-max-decimals="8" value="<?= e((string)($product['price_usdt'] ?? '')) ?>"><div class="hint" data-ui-text="products_usdt_hint">این قیمت جداگانه ثبت می‌شود و از نرخ تبدیل ارز محاسبه نمی‌شود. تا فعال‌شدن درگاه کریپتو در فروشگاه نمایش داده نخواهد شد.</div></div>
<div class="field"><label for="currency" data-ui-text="products_currency">کد پولی ISO (مثلاً IRR)</label><input id="currency" name="currency" maxlength="3" required value="<?= e((string)($product['currency'] ?? 'IRR')) ?>"></div>
</div>
</fieldset>
<fieldset class="form-section"><legend data-ui-text="products_section_capacity">حجم و ترتیب نمایش</legend>
<div class="grid">
<div class="field"><label for="quota_amount" data-fa="سقف حجم (خالی یا صفر = نامحدود)" data-en="Quota limit (blank or zero = unlimited)">سقف حجم (خالی یا صفر = نامحدود)</label><div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(110px,.55fr);gap:8px"><input id="quota_amount" name="quota_amount" type="text" inputmode="decimal" autocomplete="off" data-numeric="decimal" data-max-decimals="9" placeholder="مثلاً 100 یا 5" value="<?php
$quotaBytesForEdit = isset($product['quota_bytes']) && $product['quota_bytes'] !== null ? (int)$product['quota_bytes'] : null;
$quotaUnitForEdit = 'GB';
$quotaAmountForEdit = '';
if ($quotaBytesForEdit !== null && $quotaBytesForEdit > 0) {
    if ($quotaBytesForEdit % 1099511627776 === 0) {
        $quotaUnitForEdit = 'TB';
        $quotaAmountForEdit = (string)($quotaBytesForEdit / 1099511627776);
    } elseif ($quotaBytesForEdit % 1073741824 === 0) {
        $quotaUnitForEdit = 'GB';
        $quotaAmountForEdit = (string)($quotaBytesForEdit / 1073741824);
    } elseif ($quotaBytesForEdit % 1048576 === 0) {
        $quotaUnitForEdit = 'MB';
        $quotaAmountForEdit = (string)($quotaBytesForEdit / 1048576);
    } else {
        $quotaUnitForEdit = 'MB';
        $quotaAmountForEdit = rtrim(rtrim(number_format($quotaBytesForEdit / 1048576, 9, '.', ''), '0'), '.');
    }
}
echo e($quotaAmountForEdit);
?>"><select id="quota_unit" name="quota_unit" aria-label="واحد حجم"><option value="MB"<?= $quotaUnitForEdit === 'MB' ? ' selected' : '' ?>>MB</option><option value="GB"<?= $quotaUnitForEdit === 'GB' ? ' selected' : '' ?>>GB</option><option value="TB"<?= $quotaUnitForEdit === 'TB' ? ' selected' : '' ?>>TB</option></select></div><div class="hint" data-fa="واحدهای MB، GB و TB با مبنای ۱۰۲۴ محاسبه می‌شوند." data-en="MB, GB and TB use a 1024-based conversion.">واحد MB و GB با مبنای ۱۰۲۴ محاسبه می‌شود. در ویرایش، مقدارهای قبلی بدون تغییر در حجم ذخیره‌شده حفظ می‌شوند.</div></div>
<div class="field"><label for="sort_order" data-ui-text="products_sort">ترتیب نمایش</label><input id="sort_order" name="sort_order" type="text" inputmode="numeric" autocomplete="off" data-numeric="integer" value="<?= e((string)($product['sort_order'] ?? 0)) ?>"></div>
</div>
</fieldset>
<fieldset class="form-section"><legend data-ui-text="products_section_mappings">اتصال سرور و گروه IBSng</legend>
<div class="grid">
<div class="field field-full"><label data-ui-text="products_mappings">سرورها و گروه‌های IBSng این محصول</label>
<div id="mapping-list"><?php foreach ($productMappings as $mappingIndex => $productMapping): ?><div class="mapping-row grid" data-mapping-row>
<div class="field"><label data-ui-text="products_server">سرور IBSng</label><select name="mappings[<?= (int)$mappingIndex ?>][server_id]" data-server-select required><option value="" data-ui-text="products_select_server">انتخاب سرور</option><?php foreach ($servers as $server): ?><option value="<?= (int)$server['id'] ?>"<?= (int)($productMapping['server_id'] ?? 0)===(int)$server['id']?' selected':'' ?>><?= e((string)$server['name']) ?> · <?= e((string)$server['host']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label data-ui-text="products_group_plan">گروه / پلن فعال</label><div class="mapping-group-control"><select name="mappings[<?= (int)$mappingIndex ?>][ibsng_group_id]" data-group-select required><option value="" data-ui-text="products_select_mapping">انتخاب نگاشت</option><?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>" data-server="<?= (int)$group['server_id'] ?>"<?= (int)($productMapping['ibsng_group_id'] ?? 0)===(int)$group['id']?' selected':'' ?>><?= e((string)$group['server_name']) ?> · <?= e((string)$group['plan_name']) ?> (<?= e((string)$group['group_name']) ?>)</option><?php endforeach; ?></select><button class="button danger" type="button" data-remove-mapping data-ui-text="products_remove_mapping">حذف</button></div></div>
</div><?php endforeach; ?></div>
<button class="button" type="button" id="add-mapping" data-ui-text="products_add_mapping">افزودن سرور / گروه</button><div class="hint" data-ui-text="products_mapping_hint">هر سرور فقط یک‌بار در این محصول انتخاب شود. گروه باید از نگاشت‌های دستی همان سرور باشد.</div></div>
</div>
</fieldset>
<fieldset class="form-section"><legend data-ui-text="products_section_publication">انتشار در فروشگاه</legend>
<label class="check-toggle"><input type="checkbox" name="is_active" value="1"<?= (int)($product['is_active'] ?? 1)===1?' checked':'' ?>> <span data-ui-text="products_show_store">نمایش در فروشگاه</span></label>
</fieldset>
<div class="actions"><button class="button primary" type="submit" data-ui-text="products_save">ذخیره محصول</button><?php if ($product): ?><a class="button" href="<?= e(app_url('admin/products.php')) ?>" data-ui-text="products_cancel_edit">لغو ویرایش</a><?php endif; ?></div>
</form>
</section>
<section class="panel panel-card"><h2 data-ui-text="products_registered">محصولات ثبت‌شده</h2>
<?php if (!$products): ?><div class="empty" data-ui-text="products_empty">هنوز محصولی ثبت نشده است.</div><?php endif; ?>
<?php foreach ($products as $item): ?><article class="product-row panel"><div class="row-head"><strong><?= e((string)$item['name']) ?></strong><span class="status-chip" data-ui-status="<?= (int)$item['is_active']===1?'active':'inactive' ?>"><?= (int)$item['is_active']===1?'فعال':'غیرفعال' ?></span></div>
<p class="muted"><?= e((string)($item['description'] ?? '')) ?></p><div><span data-ui-text="products_price_label">قیمت:</span> <?php $displayPrice = store_format_price((int)$item['price_minor'], (string)$item['currency'], $priceDisplayUnit); ?><?= e($displayPrice['amount']) ?> <?= e($displayPrice['label']) ?></div><div class="muted"><span data-ui-text="products_mappings_label">سرورها و گروه‌ها:</span> <?= e((string)($item['mapping_labels'] ?? '—')) ?></div>
<div class="actions"><a class="button" href="<?= e(app_url('admin/products.php?edit='.(int)$item['id'])) ?>" data-ui-text="products_edit">ویرایش</a><form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="button" type="submit" data-ui-text="<?= (int)$item['is_active']===1?'products_inactive':'products_active' ?>"><?= (int)$item['is_active']===1?'غیرفعال‌سازی':'فعال‌سازی' ?></button></form><form method="post" data-confirm-fa="محصول حذف شود؟" data-confirm-en="Delete this product?"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="button danger" type="submit" data-ui-text="products_delete">حذف</button></form></div></article><?php endforeach; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
</main>
<script>
(function(){
 const list=document.getElementById('mapping-list'),add=document.getElementById('add-mapping');
 if(!list||!add)return;
 let nextIndex=list.querySelectorAll('[data-mapping-row]').length;
 const template=list.querySelector('[data-mapping-row]');
 function filterRow(row){
  const server=row.querySelector('[data-server-select]'),group=row.querySelector('[data-group-select]');
  Array.from(group.options).forEach(option=>{if(!option.value)return;option.hidden=option.dataset.server!==server.value;if(option.hidden&&option.selected)group.value='';});
 }
 function bind(row){
  row.querySelector('[data-server-select]').addEventListener('change',()=>filterRow(row));
  row.querySelector('[data-remove-mapping]').addEventListener('click',()=>{
   if(list.querySelectorAll('[data-mapping-row]').length>1)row.remove();
   else {row.querySelector('[data-server-select]').value='';row.querySelector('[data-group-select]').value='';}
  });
  filterRow(row);
 }
 list.querySelectorAll('[data-mapping-row]').forEach(bind);
 add.addEventListener('click',()=>{
  const row=template.cloneNode(true);
  row.querySelectorAll('select').forEach(select=>{
   select.name=select.name.replace(/mappings\[\d+\]/,'mappings['+nextIndex+']');
   select.value='';
  });
  nextIndex++;
  list.appendChild(row);
  bind(row);
 });
 // Numeric fields: prevent non-numeric keyboard input and sanitize pasted/input text.
 document.querySelectorAll('[data-numeric]').forEach(input=>{
  const decimal=input.dataset.numeric==='decimal';
  const maxDecimals=Number(input.dataset.maxDecimals||0);
  const clean=value=>{
   let result=String(value).replace(decimal?/[^0-9.]/g:/[^0-9]/g);
   if(decimal){
    const dot=result.indexOf('.');
    if(dot!==-1) result=result.slice(0,dot+1)+result.slice(dot+1).replace(/\\./g,'').slice(0,maxDecimals);
   }
   return result;
  };
  input.addEventListener('beforeinput',event=>{
   if(event.inputType==='insertText' && event.data && !(decimal?/^[0-9.]$/.test(event.data):/^[0-9]$/.test(event.data))){
    event.preventDefault();
   }
  });
  input.addEventListener('input',()=>{const value=clean(input.value);if(value!==input.value)input.value=value;});
  input.addEventListener('paste',event=>{
   const pasted=(event.clipboardData||window.clipboardData)?.getData('text');
   if(pasted!==undefined){
    event.preventDefault();
    const start=input.selectionStart??input.value.length,end=input.selectionEnd??start;
    const inserted=clean(pasted);
    input.setRangeText(inserted,start,end,'end');
    input.dispatchEvent(new Event('input',{bubbles:true}));
   }
  });
 });
 document.querySelector('form').addEventListener('submit',event=>{
  const rows=Array.from(list.querySelectorAll('[data-mapping-row]')),seen=new Set();
  for(const row of rows){
   const server=row.querySelector('[data-server-select]').value;
   if(server&&seen.has(server)){event.preventDefault();window.ATDUI?.toast(document.documentElement.lang==='en'?'Select each server only once in a product.':'هر سرور فقط یک‌بار برای محصول انتخاب شود.','error');return;}
   if(server)seen.add(server);
  }
 });
})();
</script>
<script src="<?= e(app_asset_url('assets/js/ui-preferences.js')) ?>" defer></script><script src="<?= e(app_asset_url('assets/js/ui-components.js')) ?>" defer></script>
</body></html>
