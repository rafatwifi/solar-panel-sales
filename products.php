<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf();
    if (($_POST['do'] ?? '') === 'show_price') {
        $id = (string) ($_POST['id'] ?? '');
        $products = read_json('products', []);
        foreach ($products as $i => $product) {
            if (($product['id'] ?? '') === $id) {
                $products[$i]['show_price'] = isset($_POST['show_price']);
                $products[$i]['updated_at'] = now_iso();
                break;
            }
        }
        if (!write_json('products', $products)) {
            flash('تعذر حفظ إظهار السعر', 'bad');
        } else {
            flash(isset($_POST['show_price']) ? 'صار سعر المنتج ظاهراً للموظف' : 'تم إخفاء سعر المنتج عن الموظف');
        }
    } elseif (($_POST['do'] ?? '') === 'delete') {
        $id = (string) ($_POST['id'] ?? '');
        $products = read_json('products', []);
        $next = [];
        foreach ($products as $product) {
            if (($product['id'] ?? '') === $id) {
                delete_upload($product['image'] ?? '');
                continue;
            }
            $next[] = $product;
        }
        if (!write_json('products', $next)) {
            flash('تعذر حذف المنتج', 'bad');
        } else {
            flash('تم حذف المنتج');
        }
    }
    redirect('products.php');
}

$cat = (string) ($_GET['cat'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$allowed = ['panel', 'inverter', 'battery', 'combiner_ac', 'combiner_dc', 'accessory'];
if (!in_array($cat, $allowed, true)) {
    $cat = '';
}
$products = read_json('products', []);
$visible = [];
foreach ($products as $product) {
    if ($cat !== '' && ($product['category'] ?? '') !== $cat) {
        continue;
    }
    if ($q !== '') {
        $hay = ($product['name'] ?? '') . ' ' . ($product['brand'] ?? '') . ' ' . ($product['specs'] ?? '');
        if (function_exists('mb_stripos')) {
            if (mb_stripos($hay, $q, 0, 'UTF-8') === false) {
                continue;
            }
        } elseif (stripos($hay, $q) === false) {
            continue;
        }
    }
    $visible[] = $product;
}

render_header('المنتجات', 'products.php');
?>
<div class="row-actions" style="margin-bottom:12px">
  <a class="btn btn-gold" href="product-edit.php">إضافة منتج</a>
</div>
<p class="hint">الأسعار التجريبية للتوضيح فقط. عدّلها قبل اعتماد العروض مع الزبائن. لكل منتج صورة ومواصفات وسعر.</p>
<form class="filters" method="get">
  <input class="input" type="search" name="q" value="<?php echo h($q); ?>" placeholder="بحث بالاسم أو المواصفات">
  <button class="btn" type="submit">بحث</button>
</form>
<div class="pills">
  <a class="<?php echo $cat === '' ? 'on' : ''; ?>" href="products.php">الكل</a>
  <?php foreach ($allowed as $key): ?>
    <a class="<?php echo $cat === $key ? 'on' : ''; ?>" href="products.php?cat=<?php echo h($key); ?>"><?php echo h(category_label($key)); ?></a>
  <?php endforeach; ?>
</div>
<?php if (!$visible): ?>
  <div class="card"><p>لا توجد منتجات مطابقة.</p></div>
<?php else: ?>
  <div class="catalog">
    <?php foreach ($visible as $product): ?>
      <?php $img = upload_url($product['image'] ?? ''); ?>
      <article class="product-card">
        <?php if ($img): ?>
          <img src="<?php echo h($img); ?>" alt="">
        <?php else: ?>
          <span class="ph ph-<?php echo h($product['category'] ?? 'panel'); ?>"></span>
        <?php endif; ?>
        <div class="body">
          <span class="tag"><?php echo h(category_label($product['category'] ?? '')); ?><?php echo empty($product['active']) ? ' · متوقف' : ''; ?></span>
          <strong><?php echo h($product['name'] ?? ''); ?></strong>
          <?php if (!empty($product['brand'])): ?><span class="muted"><?php echo h($product['brand']); ?></span><?php endif; ?>
          <?php $size = product_size_text($product); ?>
          <?php if ($size !== ''): ?><span><?php echo h($size); ?></span><?php endif; ?>
          <span class="price"><?php echo h(money($product['price'] ?? 0)); ?></span>
          <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="do" value="show_price">
            <input type="hidden" name="id" value="<?php echo h($product['id']); ?>">
            <label class="switch">
              <input type="checkbox" name="show_price" value="1"<?php echo shows_price($product) ? ' checked' : ''; ?> onchange="this.form.submit()">
              <i></i>
              <span><?php echo shows_price($product) ? 'السعر ظاهر للموظف' : 'السعر مخفي عن الموظف'; ?></span>
            </label>
          </form>
          <p class="muted" style="margin:0"><?php echo h(excerpt($product['specs'] ?? '')); ?></p>
          <div class="row-actions" style="margin-top:auto">
            <a class="btn btn-sm" href="product-edit.php?id=<?php echo h($product['id']); ?>">تعديل</a>
            <form method="post">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="do" value="delete">
              <input type="hidden" name="id" value="<?php echo h($product['id']); ?>">
              <button class="btn btn-danger btn-sm" type="submit" data-confirm="حذف هذا المنتج؟">حذف</button>
            </form>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
render_footer();
