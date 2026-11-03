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
$renewalColumnCheck->execute(['products', 'ibsng_validity_days']);
if ((int)$renewalColumnCheck->fetchColumn() !== 1) {
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
                $duration = product_post_int('duration_seconds');
                $ibsngValidity = product_post_int('ibsng_validity_days');
                $price = product_post_int('price_minor');
                $priceUsdtRaw = trim((string)($_POST['price_usdt'] ?? ''));
                $priceUsdt = null;
                if ($priceUsdtRaw !== '') {
                    if (!preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\\.[0-9]{1,8})?$/D', $priceUsdtRaw) || (float)$priceUsdtRaw <= 0) {
                        throw new InvalidArgumentException('قیمت USDT باید عدد مثبت با حداکثر ۸ رقم اعشار باشد.');
                    }
                    $priceUsdt = $priceUsdtRaw;
                }
                $quota = trim((string)($_POST['quota_bytes'] ?? ''));
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
                if ($duration < 1 || $ibsngValidity < 1 || $ibsngValidity > 36500 || $price < 0 || $sortOrder < 0) {
                    throw new InvalidArgumentException('مدت عمومی، مدت اعتبار مخصوص IBSng (۱ تا ۳۶۵۰۰ روز)، قیمت یا ترتیب نمایش معتبر نیست.');
                }
                if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                    throw new InvalidArgumentException('واحد پول باید کد سه‌حرفی مانند IRR باشد.');
                }
                $quotaBytes = null;
                if ($quota !== '') {
                    if (!ctype_digit($quota) || (float)$quota > PHP_INT_MAX) {
                        throw new InvalidArgumentException('حجم باید عدد صحیح بر حسب بایت باشد.');
                    }
                    $quotaBytes = (int)$quota;
                }

                $pdo->beginTransaction();
                $validateMapping = $pdo->prepare('SELECT g.id FROM ibsng_groups g JOIN ibsng_servers s ON s.id=g.server_id WHERE g.id=? AND g.server_id=? AND g.is_active=1 AND s.is_active=1');
                foreach ($mappingPairs as $serverId => $groupId) {
                    $validateMapping->execute([$groupId, $serverId]);
                    if (!$validateMapping->fetchColumn()) throw new InvalidArgumentException('یکی از نگاشت‌های گروه IBSng فعال نیست یا به سرور انتخاب‌شده تعلق ندارد.');
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE products SET name=?,description=?,duration_seconds=?,ibsng_validity_days=?,price_minor=?,price_usdt=?,currency=?,quota_bytes=?,is_active=?,sort_order=? WHERE id=?');
                    $stmt->execute([$name, $description !== '' ? $description : null, $duration, $ibsngValidity, $price, $priceUsdt, $currency, $quotaBytes, $active, $sortOrder, $id]);
                    $check = $pdo->prepare('SELECT id FROM products WHERE id=?');
                    $check->execute([$id]);
                    if (!$check->fetchColumn()) {
                        throw new RuntimeException('محصول موردنظر پیدا نشد.');
                    }
                    $productId = $id;
                } else {
                    $stmt = $pdo->prepare('INSERT INTO products (name,description,duration_seconds,ibsng_validity_days,price_minor,price_usdt,currency,quota_bytes,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([$name, $description !== '' ? $description : null, $duration, $ibsngValidity, $price, $priceUsdt, $currency, $quotaBytes, $active, $sortOrder]);
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
<title>مدیریت محصولات | <?= e((string)($config['site_title'] ?? 'ATD IBSng Store')) ?></title>
<link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
<style>
.admin-shell{width:min(1120px,calc(100% - 32px));margin:0 auto}.admin-top{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:24px 0}.panel-card{padding:24px;margin:20px 0}.field{display:grid;gap:8px;margin:14px 0}.field label{color:var(--muted);font-size:.9rem}.field input,.field select,.field textarea{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--control-bg);color:var(--text);font:inherit}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}.notice{padding:13px 15px;border-radius:12px;margin:14px 0;line-height:1.8}.ok{background:#44d6a018;border:1px solid #44d6a044}.err{background:#ff718018;border:1px solid #ff718044}.product-row{padding:18px;margin:14px 0}.row-head{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.actions button{font:inherit;cursor:pointer}.muted{overflow-wrap:anywhere}.empty{padding:24px;text-align:center;color:var(--muted)}@media(max-width:600px){.grid{grid-template-columns:1fr}.admin-top{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<main class="admin-shell">
<header class="admin-top"><a class="brand" href="<?= e(app_url('admin/')) ?>">ATD IBSng Store · مدیریت</a><nav><a class="button" href="<?= e(app_url('admin/ibsng-groups.php')) ?>">نگاشت پلن/گروه</a><a class="button" href="<?= e(app_url('admin/ibsng-servers.php')) ?>">سرورهای IBSng</a></nav></header>
<section class="panel panel-card">
<div class="eyebrow">Store · Catalog</div><h1>مدیریت محصولات و پلن‌ها</h1>
<p class="muted">هر محصول می‌تواند به یک یا چند سرور و گروه IBSng متناظر با آن‌ها متصل شود. مدت بر حسب ثانیه، قیمت بر حسب کوچک‌ترین واحد پول و حجم بر حسب بایت ثبت می‌شود.</p>
<?php if ($message !== ''): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
<?php if (!$servers || !$groups): ?><div class="notice err">برای تعریف محصول ابتدا یک سرور فعال و سپس یک نگاشت گروه فعال در بخش IBSng ایجاد کنید.</div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($product['id'] ?? 0) ?>">
<div class="grid">
<div class="field"><label for="name">نام محصول</label><input id="name" name="name" maxlength="180" required value="<?= e((string)($product['name'] ?? '')) ?>"></div>
<div class="field"><label for="duration_seconds">مدت اعتبار (ثانیه)</label><input id="duration_seconds" name="duration_seconds" type="number" min="1" required value="<?= e((string)($product['duration_seconds'] ?? 2592000)) ?>"></div>
<div class="field"><label for="ibsng_validity_days">مدت تمدید IBSng (روز)</label><input id="ibsng_validity_days" name="ibsng_validity_days" type="number" min="1" max="36500" required value="<?= e((string)($product['ibsng_validity_days'] ?? '')) ?>"><div class="hint">این مقدار فقط برای تمدید IBSng است و از مدت عمومی محصول حدس زده نمی‌شود.</div></div>
<div class="field"><label for="price_minor">قیمت پایه (کوچک‌ترین واحد پولِ ارز انتخابی)</label><input id="price_minor" name="price_minor" type="number" min="0" required value="<?= e((string)($product['price_minor'] ?? 0)) ?>"></div>
<div class="field"><label for="price_usdt">قیمت مستقل USDT (اختیاری)</label><input id="price_usdt" name="price_usdt" type="number" min="0.00000001" step="0.00000001" value="<?= e((string)($product['price_usdt'] ?? '')) ?>"><div class="hint">این قیمت جداگانه ثبت می‌شود و از نرخ تبدیل ارز محاسبه نمی‌شود. تا فعال‌شدن درگاه کریپتو در فروشگاه نمایش داده نخواهد شد.</div></div>
<div class="field"><label for="currency">کد پولی ISO (مثلاً IRR)</label><input id="currency" name="currency" maxlength="3" required value="<?= e((string)($product['currency'] ?? 'IRR')) ?>"></div>
<div class="field"><label for="quota_bytes">سقف حجم (بایت؛ خالی یعنی نامشخص/نامحدود)</label><input id="quota_bytes" name="quota_bytes" inputmode="numeric" value="<?= e((string)($product['quota_bytes'] ?? '')) ?>"></div>
<div class="field"><label for="sort_order">ترتیب نمایش</label><input id="sort_order" name="sort_order" type="number" min="0" value="<?= e((string)($product['sort_order'] ?? 0)) ?>"></div>
<div class="field" style="grid-column:1/-1"><label>سرورها و گروه‌های IBSng این محصول</label>
<div id="mapping-list"><?php foreach ($productMappings as $mappingIndex => $productMapping): ?><div class="mapping-row grid" style="align-items:end" data-mapping-row>
<div class="field"><label>سرور IBSng</label><select name="mappings[<?= (int)$mappingIndex ?>][server_id]" data-server-select required><option value="">انتخاب سرور</option><?php foreach ($servers as $server): ?><option value="<?= (int)$server['id'] ?>"<?= (int)($productMapping['server_id'] ?? 0)===(int)$server['id']?' selected':'' ?>><?= e((string)$server['name']) ?> · <?= e((string)$server['host']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label>گروه / پلن فعال</label><div style="display:flex;gap:8px"><select name="mappings[<?= (int)$mappingIndex ?>][ibsng_group_id]" data-group-select required><option value="">انتخاب نگاشت</option><?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>" data-server="<?= (int)$group['server_id'] ?>"<?= (int)($productMapping['ibsng_group_id'] ?? 0)===(int)$group['id']?' selected':'' ?>><?= e((string)$group['server_name']) ?> · <?= e((string)$group['plan_name']) ?> (<?= e((string)$group['group_name']) ?>)</option><?php endforeach; ?></select><button class="button" type="button" data-remove-mapping>حذف</button></div></div>
</div><?php endforeach; ?></div>
<button class="button" type="button" id="add-mapping">افزودن سرور / گروه</button><div class="hint">هر سرور فقط یک‌بار در این محصول انتخاب شود. گروه باید از نگاشت‌های دستی همان سرور باشد.</div></div>
</div>
<div class="field"><label for="description">توضیحات</label><textarea id="description" name="description" rows="3"><?= e((string)($product['description'] ?? '')) ?></textarea></div>
<label><input type="checkbox" name="is_active" value="1"<?= (int)($product['is_active'] ?? 1)===1?' checked':'' ?>> نمایش در فروشگاه</label>
<div class="actions"><button class="button primary" type="submit">ذخیره محصول</button><?php if ($product): ?><a class="button" href="<?= e(app_url('admin/products.php')) ?>">لغو ویرایش</a><?php endif; ?></div>
</form>
</section>
<section class="panel panel-card"><h2>محصولات ثبت‌شده</h2>
<?php if (!$products): ?><div class="empty">هنوز محصولی ثبت نشده است.</div><?php endif; ?>
<?php foreach ($products as $item): ?><article class="product-row panel"><div class="row-head"><strong><?= e((string)$item['name']) ?></strong><span class="status-chip"><?= (int)$item['is_active']===1?'فعال':'غیرفعال' ?></span></div>
<p class="muted"><?= e((string)($item['description'] ?? '')) ?></p><div>مدت: <?= number_format((int)$item['duration_seconds']) ?> ثانیه · تمدید IBSng: <?= $item['ibsng_validity_days'] !== null ? number_format((int)$item['ibsng_validity_days']) . ' روز' : 'تنظیم نشده' ?> · قیمت: <?php $displayPrice = store_format_price((int)$item['price_minor'], (string)$item['currency'], $priceDisplayUnit); ?><?= e($displayPrice['amount']) ?> <?= e($displayPrice['label']) ?></div><div class="muted">سرورها و گروه‌ها: <?= e((string)($item['mapping_labels'] ?? '—')) ?></div>
<div class="actions"><a class="button" href="<?= e(app_url('admin/products.php?edit='.(int)$item['id'])) ?>">ویرایش</a><form method="post"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="button" type="submit"><?= (int)$item['is_active']===1?'غیرفعال‌سازی':'فعال‌سازی' ?></button></form><form method="post" onsubmit="return confirm('محصول حذف شود؟');"><input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="button" type="submit">حذف</button></form></div></article><?php endforeach; ?>
</section><footer><a href="<?= e(app_url()) ?>">بازگشت به فروشگاه</a></footer>
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
 document.querySelector('form').addEventListener('submit',event=>{
  const rows=Array.from(list.querySelectorAll('[data-mapping-row]')),seen=new Set();
  for(const row of rows){
   const server=row.querySelector('[data-server-select]').value;
   if(server&&seen.has(server)){event.preventDefault();alert('هر سرور فقط یک‌بار برای محصول انتخاب شود.');return;}
   if(server)seen.add(server);
  }
 });
})();
</script>
<script src="<?= e(app_url('assets/js/ui-preferences.js')) ?>" defer></script>
</body></html>
