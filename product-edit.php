<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_admin();

$categories = ['panel', 'inverter', 'battery', 'combiner_ac', 'combiner_dc', 'accessory'];
$id = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));
$products = read_json('products', []);
$current = null;
$index = -1;
if ($id !== '') {
    foreach ($products as $i => $product) {
        if (($product['id'] ?? '') === $id) {
            $current = $product;
            $index = $i;
            break;
        }
    }
    if (!$current && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        flash('المنتج غير موجود', 'bad');
        redirect('products.php');
    }
}

$errors = [];
$form = $current ?: [
    'name' => '',
    'category' => 'panel',
    'brand' => '',
    'price' => '',
    'show_price' => false,
    'watts' => '',
    'kwh' => '',
    'amp_min' => '',
    'amp_max' => '',
    'specs' => '',
    'image' => '',
    'active' => true,
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf();
    $name = clean_text($_POST['name'] ?? '', 120);
    $category = (string) ($_POST['category'] ?? '');
    $brand = clean_text($_POST['brand'] ?? '', 80);
    $price = (int) round(num($_POST['price'] ?? 0));
    $showPrice = isset($_POST['show_price']);
    $specs = clean_text($_POST['specs'] ?? '', 2000);
    $active = isset($_POST['active']);
    $watts = 0;
    $kwh = 0;
    $ampMin = 0;
    $ampMax = 0;
    $isCombiner = ($category === 'combiner_ac' || $category === 'combiner_dc');

    if (str_len($name) < 2) {
        $errors[] = 'اكتب اسم المنتج';
    }
    if (!in_array($category, $categories, true)) {
        $errors[] = 'اختر نوع المنتج';
    }
    if ($price < 0 || $price > 10000000000) {
        $errors[] = 'السعر غير صحيح';
    }
    if ($category === 'panel' || $category === 'inverter') {
        $watts = num($_POST['watts'] ?? 0);
        if ($watts <= 0 || $watts > 1000000) {
            $errors[] = 'اكتب القدرة بالواط';
        }
    }
    if ($category === 'battery') {
        $kwh = num($_POST['kwh'] ?? 0);
        if ($kwh <= 0 || $kwh > 10000) {
            $errors[] = 'اكتب سعة البطارية بالكيلو واط ساعة';
        }
    }
    if ($isCombiner) {
        $ampMin = num($_POST['amp_min'] ?? 0);
        $ampMax = num($_POST['amp_max'] ?? 0);
        if ($ampMin <= 0 || $ampMax <= $ampMin || $ampMax > 1000) {
            $errors[] = 'اكتب مدى الأمبير، مثل من 10 إلى 20';
        }
    }

    $image = (string) ($current['image'] ?? '');
    if (!$errors && isset($_POST['remove_image'])) {
        delete_upload($image);
        $image = '';
    }
    if (!$errors) {
        $upload = store_image('image');
        if (!$upload['ok']) {
            $errors[] = $upload['error'];
        } elseif (!empty($upload['path'])) {
            if ($image !== '') {
                delete_upload($image);
            }
            $image = $upload['path'];
        }
    }

    $form = [
        'name' => $name,
        'category' => $category ?: 'panel',
        'brand' => $brand,
        'price' => $price,
        'show_price' => $showPrice,
        'watts' => $watts,
        'kwh' => $kwh,
        'amp_min' => $ampMin,
        'amp_max' => $ampMax,
        'specs' => $specs,
        'image' => $image,
        'active' => $active,
    ];

    if (!$errors) {
        $row = [
            'id' => $current['id'] ?? uid('p'),
            'name' => $name,
            'category' => $category,
            'brand' => $brand,
            'watts' => ($category === 'panel' || $category === 'inverter') ? (float) $watts : 0,
            'kwh' => $category === 'battery' ? (float) $kwh : 0,
            'amp_min' => $isCombiner ? (float) $ampMin : 0,
            'amp_max' => $isCombiner ? (float) $ampMax : 0,
            'voltage' => 0,
            'price' => $price,
            'show_price' => $showPrice,
            'specs' => $specs,
            'image' => $image,
            'active' => $active,
            'created_at' => $current['created_at'] ?? now_iso(),
            'updated_at' => now_iso(),
        ];
        if ($index >= 0) {
            $products[$index] = $row;
        } else {
            $products[] = $row;
        }
        if (!write_json('products', array_values($products))) {
            $errors[] = 'تعذر الحفظ. تأكد من صلاحية مجلد data';
        } else {
            flash($index >= 0 ? 'تم تحديث المنتج' : 'تمت إضافة المنتج');
            redirect('products.php');
        }
    }
}

render_header($current ? 'تعديل منتج' : 'منتج جديد', 'products.php');
?>
<div class="card" style="max-width:760px">
  <?php foreach ($errors as $error): ?>
    <div class="notice notice-low"><?php echo h($error); ?></div>
  <?php endforeach; ?>
  <form method="post" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo h($current['id'] ?? ''); ?>">
    <div class="field">
      <label for="name">اسم المنتج</label>
      <input class="input" id="name" name="name" value="<?php echo h($form['name']); ?>" required>
    </div>
    <div class="grid-2">
      <div class="field">
        <label for="category">النوع</label>
        <select class="select" id="category" name="category">
          <?php foreach ($categories as $key): ?>
            <option value="<?php echo h($key); ?>"<?php echo ($form['category'] ?? '') === $key ? ' selected' : ''; ?>><?php echo h(category_label($key)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="brand">الشركة أو السلسلة</label>
        <input class="input" id="brand" name="brand" value="<?php echo h($form['brand'] ?? ''); ?>">
      </div>
    </div>
    <div class="field">
      <label for="price">السعر</label>
      <input class="input num" id="price" name="price" inputmode="decimal" value="<?php echo h($form['price']); ?>">
      <label class="switch">
        <input type="checkbox" name="show_price" value="1"<?php echo shows_price($form) ? ' checked' : ''; ?>>
        <i></i>
        <span>إظهار السعر للموظف</span>
      </label>
      <p class="hint">إذا كان الزر مطفأ، الموظف يشوف المنتج بدون سعر. أنت تشوف السعر دائماً.</p>
    </div>
    <div class="field" data-show-for="panel,inverter">
      <label for="watts">القدرة (واط)</label>
      <input class="input num" id="watts" name="watts" inputmode="decimal" value="<?php echo h($form['watts']); ?>">
      <p class="hint">للوح اكتب قدرة اللوح مثل 550. للإنفرتر اكتب قدرته مثل 5000.</p>
    </div>
    <div class="field" data-show-for="battery">
      <label for="kwh">السعة (كيلو واط ساعة)</label>
      <input class="input num" id="kwh" name="kwh" inputmode="decimal" value="<?php echo h($form['kwh']); ?>">
    </div>
    <div data-show-for="combiner_ac,combiner_dc">
      <div class="grid-2">
        <div class="field">
          <label for="amp_min">من أمبير</label>
          <input class="input num" id="amp_min" name="amp_min" inputmode="decimal" value="<?php echo h($form['amp_min'] ?? ''); ?>">
        </div>
        <div class="field">
          <label for="amp_max">إلى أمبير</label>
          <input class="input num" id="amp_max" name="amp_max" inputmode="decimal" value="<?php echo h($form['amp_max'] ?? ''); ?>">
        </div>
      </div>
      <p class="hint">مثال: من 10 إلى 20. ما يحتاج قدرة بالواط ولا سعة بالكيلو واط ساعة، بس السعر وهذا المدى.</p>
    </div>
    <div class="field">
      <label for="specs">المواصفات</label>
      <textarea class="textarea" id="specs" name="specs"><?php echo h($form['specs'] ?? ''); ?></textarea>
    </div>
    <div class="field">
      <label for="image">صورة أو شعار المنتج</label>
      <input class="input" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
      <p class="hint">JPG أو PNG، بحد أقصى 2 ميغا.</p>
      <?php $img = upload_url($form['image'] ?? ''); ?>
      <?php if ($img): ?>
        <img id="imagePreview" class="preview-img" src="<?php echo h($img); ?>" alt="">
        <label class="check"><input type="checkbox" name="remove_image" value="1"> حذف الصورة الحالية</label>
      <?php else: ?>
        <img id="imagePreview" class="preview-img" alt="" hidden>
      <?php endif; ?>
    </div>
    <label class="check"><input type="checkbox" name="active" value="1"<?php echo !empty($form['active']) ? ' checked' : ''; ?>> ظاهر للموظف</label>
    <div class="row-actions" style="margin-top:16px">
      <button class="btn btn-gold" type="submit">حفظ</button>
      <a class="btn" href="products.php">رجوع</a>
    </div>
  </form>
</div>
<?php
render_footer();
