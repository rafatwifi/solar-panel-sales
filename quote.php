<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/calc.php';
require __DIR__ . '/includes/layout.php';
require_login();

$settings = app_settings();
$products = read_json('products', []);
$user = current_user();
$errors = [];
$openStep = 0;
$old = default_quote_form($settings);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf();
    $built = build_quote($_POST, $user, $settings, $products);
    $errors = $built['errors'];
    $old = $built['old'];
    if (!$errors) {
        $orders = read_json('orders', []);
        $order = $built['order'];
        $action = (($_POST['action'] ?? '') === 'send') ? 'send' : 'draft';
        $order['status'] = $action === 'send' ? 'sent' : 'draft';
        $order['sent_at'] = $action === 'send' ? now_iso() : '';
        $index = -1;
        $oid = trim((string) ($old['order_id'] ?? ''));
        if ($oid !== '') {
            foreach ($orders as $i => $row) {
                if (($row['id'] ?? '') === $oid) {
                    $index = $i;
                    break;
                }
            }
            if ($index < 0) {
                $errors[] = 'المسودة غير موجودة';
            } else {
                $prev = $orders[$index];
                $can = is_admin() || (($prev['employee_id'] ?? '') === $user['id']);
                if (!$can || ($prev['status'] ?? '') !== 'draft') {
                    $errors[] = 'لا يمكن تعديل هذا الطلب';
                } else {
                    $order['id'] = $prev['id'];
                    $order['code'] = $prev['code'];
                    $order['created_at'] = $prev['created_at'];
                    $order['admin_note'] = $prev['admin_note'] ?? '';
                    $order['employee_id'] = $prev['employee_id'];
                    $order['employee_name'] = $prev['employee_name'];
                }
            }
        } else {
            $order['code'] = next_order_code($orders);
        }
        if (!$errors) {
            if ($index < 0) {
                $orders[] = $order;
            } else {
                $orders[$index] = $order;
            }
            if (!write_json('orders', $orders)) {
                $errors[] = 'تعذر حفظ الطلب. تأكد أن مجلد data قابل للكتابة.';
            } else {
                flash($action === 'send' ? 'تم إرسال الطلب إلى المبيعات' : 'تم حفظ المسودة');
                redirect('order.php?id=' . urlencode($order['id']));
            }
        }
    }
    if ($errors) {
        $openStep = 6;
    }
} elseif (!empty($_GET['id'])) {
    $existing = find_order(trim((string) $_GET['id']));
    if (!$existing) {
        flash('الطلب غير موجود', 'bad');
        redirect('orders.php');
    }
    $can = is_admin() || (($existing['employee_id'] ?? '') === $user['id']);
    if (!$can) {
        flash('لا يمكنك فتح هذا الطلب', 'bad');
        redirect('orders.php');
    }
    if (($existing['status'] ?? '') !== 'draft') {
        redirect('order.php?id=' . urlencode($existing['id']));
    }
    $old = order_form_values($existing);
}

$panels = products_by_cat($products, 'panel');
$inverters = products_by_cat($products, 'inverter');
$batteries = products_by_cat($products, 'battery');
$combinersAc = products_by_cat($products, 'combiner_ac');
$combinersDc = products_by_cat($products, 'combiner_dc');
$jsProducts = [];
foreach ($products as $product) {
    if (empty($product['active'])) {
        continue;
    }
    $jsProducts[] = [
        'id' => $product['id'],
        'name' => $product['name'],
        'category' => $product['category'],
        'brand' => $product['brand'] ?? '',
        'watts' => (float) ($product['watts'] ?? 0),
        'kwh' => (float) ($product['kwh'] ?? 0),
        'ampMin' => (float) ($product['amp_min'] ?? 0),
        'ampMax' => (float) ($product['amp_max'] ?? 0),
        'price' => (float) ($product['price'] ?? 0),
        'showPrice' => is_admin() || shows_price($product),
    ];
}
$jsSettings = [
    'day_panel_factor' => (float) $settings['day_panel_factor'],
    'ac_voltage' => (float) $settings['ac_voltage'],
    'peak_sun_hours' => (float) $settings['peak_sun_hours'],
    'pv_efficiency_percent' => (float) $settings['pv_efficiency_percent'],
    'inverter_margin_percent' => (float) $settings['inverter_margin_percent'],
    'battery_reserve_percent' => (float) $settings['battery_reserve_percent'],
    'battery_efficiency_percent' => (float) $settings['battery_efficiency_percent'],
    'currency' => $settings['currency'],
    'cost_panel_install' => (float) $settings['cost_panel_install'],
    'cost_other' => (float) $settings['cost_other'],
];

function pick_card($product, $inputName, $checked)
{
    $img = upload_url($product['image'] ?? '');
    $badge = product_size_text($product);
    ?>
    <label class="pick-card">
      <input type="radio" name="<?php echo h($inputName); ?>" value="<?php echo h($product['id']); ?>"<?php echo $checked ? ' checked' : ''; ?>>
      <span class="pick-body">
        <?php if ($img): ?>
          <img src="<?php echo h($img); ?>" alt="">
        <?php else: ?>
          <span class="ph ph-<?php echo h($product['category']); ?>"></span>
        <?php endif; ?>
        <span class="pick-text">
          <strong><?php echo h($product['name']); ?></strong>
          <?php if (!empty($product['brand'])): ?><small><?php echo h($product['brand']); ?></small><?php endif; ?>
          <?php if ($badge !== ''): ?><em><?php echo h($badge); ?></em><?php endif; ?>
          <?php if (is_admin() || shows_price($product)): ?><b><?php echo h(money($product['price'] ?? 0)); ?></b><?php endif; ?>
        </span>
      </span>
    </label>
    <?php
}

render_header('تصميم منظومة', 'quote.php');
?>
<?php foreach ($errors as $error): ?>
  <div class="notice notice-low"><?php echo h($error); ?></div>
<?php endforeach; ?>
<noscript><div class="notice notice-low">فعّل جافاسكربت في المتصفح حتى يشتغل مصمم المنظومة.</div></noscript>

<form id="quoteForm" class="card" method="post" novalidate>
  <?php echo csrf_field(); ?>
  <input type="hidden" name="order_id" value="<?php echo h($old['order_id'] ?? ''); ?>">
  <div class="wsteps">
    <?php foreach (['الزبون', 'الأحمال', 'الألواح', 'الإنفرتر', 'البطارية', 'البوردات', 'الملخص'] as $i => $label): ?>
      <button type="button" class="wstep" data-go="<?php echo $i; ?>">
        <span class="n"><?php echo $i + 1; ?></span>
        <span class="step-label"><?php echo h($label); ?></span>
      </button>
    <?php endforeach; ?>
  </div>
  <p id="stepCount" class="hint">الخطوة 1 من 7</p>
  <div class="design-strip" id="designStrip">
    <div class="chip"><span>الألواح المطلوبة</span><strong>—</strong></div>
    <div class="chip"><span>الإنفرتر المطلوب</span><strong>—</strong></div>
    <div class="chip"><span>البطارية المطلوبة</span><strong>—</strong></div>
    <div class="chip"><span>البورد المطلوب</span><strong>—</strong></div>
  </div>
  <p id="dailyLine" class="hint"></p>
  <p class="hint">عدد الألواح = أمبير النهار × <?php echo h($settings['day_panel_factor']); ?>. هذا المعامل يتغير من إعدادات الإدارة.</p>

  <section class="step active">
    <h2>بيانات الزبون</h2>
    <p class="hint">تُحفظ مع الطلب وتظهر عند المبيعات.</p>
    <div class="field">
      <label for="customer_name">اسم الزبون</label>
      <input class="input" id="customer_name" name="customer_name" value="<?php echo h($old['customer_name']); ?>" autocomplete="name">
    </div>
    <div class="field">
      <label for="customer_phone">رقم الهاتف</label>
      <input class="input ltr" id="customer_phone" name="customer_phone" value="<?php echo h($old['customer_phone']); ?>" inputmode="tel" autocomplete="tel" placeholder="07xxxxxxxxx">
    </div>
  </section>

  <section class="step">
    <h2>الأحمال</h2>
    <p class="hint">الأمبير هو سحب الأجهزة على كهرباء <?php echo h($settings['ac_voltage']); ?> فولت. النهار والليل ينحسبان منفصلين.</p>
    <div class="amp-grid">
      <div class="field">
        <label for="day_amps">التجهيز النهاري (أمبير)</label>
        <input class="input num amp-input" id="day_amps" name="day_amps" inputmode="decimal" value="<?php echo h($old['day_amps']); ?>" placeholder="15">
      </div>
      <div class="field">
        <label for="night_amps">كم أمبير ليلي؟</label>
        <input class="input num amp-input" id="night_amps" name="night_amps" inputmode="decimal" value="<?php echo h($old['night_amps']); ?>" placeholder="8">
      </div>
      <div class="field">
        <label for="day_hours">ساعات تشغيل النهار</label>
        <input class="input num" id="day_hours" name="day_hours" inputmode="decimal" value="<?php echo h($old['day_hours']); ?>">
      </div>
      <div class="field">
        <label for="night_hours">ساعات تشغيل الليل</label>
        <input class="input num" id="night_hours" name="night_hours" inputmode="decimal" value="<?php echo h($old['night_hours']); ?>">
      </div>
    </div>
  </section>

  <section class="step">
    <h2>الألواح</h2>
    <p class="hint">العدد يطلع من أمبير النهار × المعامل. اختر نوع اللوح حتى ينثبت بالمنظومة.</p>
    <?php if (!$panels): ?>
      <div class="notice notice-low">لا توجد ألواح. <?php if (is_admin()): ?>أضفها من صفحة المنتجات.<?php else: ?>اطلب من الإدارة إضافة الألواح.<?php endif; ?></div>
    <?php else: ?>
      <input class="input" data-filter="panelGrid" placeholder="بحث عن لوح" style="margin-bottom:10px">
      <div class="pick-grid" id="panelGrid">
        <?php foreach ($panels as $product): ?>
          <?php pick_card($product, 'panel_id', ($old['panel_id'] ?? '') === $product['id']); ?>
        <?php endforeach; ?>
      </div>
      <div class="qty-bar">
        <span>عدد الألواح</span>
        <div class="stepper">
          <button type="button" data-stepper="panel_qty" data-dir="-1">−</button>
          <input class="input num" id="panel_qty" name="panel_qty" min="1" value="<?php echo h($old['panel_qty']); ?>">
          <button type="button" data-stepper="panel_qty" data-dir="1">+</button>
        </div>
        <strong id="panelLine"></strong>
      </div>
      <div id="panelFit" class="notice is-hidden" aria-live="polite"></div>
    <?php endif; ?>
  </section>

  <section class="step">
    <h2>الإنفرتر</h2>
    <p class="hint">إذا كانت قدرته أعلى من المطلوب أو أقل، يظهر تنبيه قبل الإرسال.</p>
    <?php if (!$inverters): ?>
      <div class="notice notice-low">لا توجد إنفرترات مضافة.</div>
    <?php else: ?>
      <input class="input" data-filter="inverterGrid" placeholder="بحث عن إنفرتر" style="margin-bottom:10px">
      <div class="pick-grid" id="inverterGrid">
        <?php foreach ($inverters as $product): ?>
          <?php pick_card($product, 'inverter_id', ($old['inverter_id'] ?? '') === $product['id']); ?>
        <?php endforeach; ?>
      </div>
      <div class="qty-bar">
        <span>العدد</span>
        <div class="stepper">
          <button type="button" data-stepper="inverter_qty" data-dir="-1">−</button>
          <input class="input num" id="inverter_qty" name="inverter_qty" min="1" value="<?php echo h($old['inverter_qty']); ?>">
          <button type="button" data-stepper="inverter_qty" data-dir="1">+</button>
        </div>
        <strong id="inverterLine"></strong>
      </div>
      <div id="inverterFit" class="notice is-hidden" aria-live="polite"></div>
    <?php endif; ?>
  </section>

  <section class="step">
    <h2>البطارية</h2>
    <p class="hint">السعة بالكيلو واط ساعة. النظام يقارنها بالطاقة التي يحتاجها الليل.</p>
    <div class="pick-grid" id="batteryGrid">
      <label class="pick-card">
        <input type="radio" name="battery_id" value=""<?php echo ($old['battery_id'] ?? '') === '' ? ' checked' : ''; ?>>
        <span class="pick-body">
          <span class="ph ph-battery"></span>
          <span class="pick-text"><strong>بدون بطارية</strong><small>فقط إذا ما في حمل ليلي</small></span>
        </span>
      </label>
      <?php foreach ($batteries as $product): ?>
        <?php pick_card($product, 'battery_id', ($old['battery_id'] ?? '') === $product['id']); ?>
      <?php endforeach; ?>
    </div>
    <div class="qty-bar">
      <span>عدد البطاريات</span>
      <div class="stepper">
        <button type="button" data-stepper="battery_qty" data-dir="-1">−</button>
        <input class="input num" id="battery_qty" name="battery_qty" min="1" value="<?php echo h($old['battery_qty']); ?>">
        <button type="button" data-stepper="battery_qty" data-dir="1">+</button>
      </div>
      <strong id="batteryLine"></strong>
    </div>
    <div id="batteryFit" class="notice is-hidden" aria-live="polite"></div>
  </section>

  <section class="step">
    <h2>البوردات</h2>
    <p class="hint">كل بورد على حدة. النظام يختار المدى من أكبر أمبير بين النهار والليل: حتى 20 يستخدم 10-20، وحتى 30 يستخدم 20-30، وأكثر من 30 يستخدم 30-40. تقدر تغيّر الاختيار. العدد 1.</p>
    <h3>بورد AC</h3>
    <?php if (!$combinersAc): ?>
      <div class="notice notice-low">لا توجد بوردات AC. <?php if (is_admin()): ?>أضفها من صفحة المنتجات.<?php else: ?>اطلب من الإدارة إضافة البوردات.<?php endif; ?></div>
    <?php else: ?>
      <div class="pick-grid" id="combinerAcGrid">
        <label class="pick-card">
          <input type="radio" name="combiner_ac_id" value=""<?php echo ($old['combiner_ac_id'] ?? '') === '' ? ' checked' : ''; ?>>
          <span class="pick-body">
            <span class="ph ph-combiner_ac"></span>
            <span class="pick-text"><strong>بدون بورد AC</strong><small>فقط إذا كان الأمبير صفراً</small></span>
          </span>
        </label>
        <?php foreach ($combinersAc as $product): ?>
          <?php pick_card($product, 'combiner_ac_id', ($old['combiner_ac_id'] ?? '') === $product['id']); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <input type="hidden" name="combiner_ac_qty" value="1">
    <div class="qty-bar">
      <span>العدد 1</span>
      <strong id="combinerAcLine"></strong>
    </div>
    <div id="combinerAcFit" class="notice is-hidden" aria-live="polite"></div>

    <h3>بورد DC</h3>
    <?php if (!$combinersDc): ?>
      <div class="notice notice-low">لا توجد بوردات DC. <?php if (is_admin()): ?>أضفها من صفحة المنتجات.<?php else: ?>اطلب من الإدارة إضافة البوردات.<?php endif; ?></div>
    <?php else: ?>
      <div class="pick-grid" id="combinerDcGrid">
        <label class="pick-card">
          <input type="radio" name="combiner_dc_id" value=""<?php echo ($old['combiner_dc_id'] ?? '') === '' ? ' checked' : ''; ?>>
          <span class="pick-body">
            <span class="ph ph-combiner_dc"></span>
            <span class="pick-text"><strong>بدون بورد DC</strong><small>فقط إذا كان الأمبير صفراً</small></span>
          </span>
        </label>
        <?php foreach ($combinersDc as $product): ?>
          <?php pick_card($product, 'combiner_dc_id', ($old['combiner_dc_id'] ?? '') === $product['id']); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <input type="hidden" name="combiner_dc_qty" value="1">
    <div class="qty-bar">
      <span>العدد 1</span>
      <strong id="combinerDcLine"></strong>
    </div>
    <div id="combinerDcFit" class="notice is-hidden" aria-live="polite"></div>
  </section>

  <section class="step">
    <h2>الملخص والإرسال</h2>
    <div id="summaryBox"></div>
    <div class="field">
      <label for="notes">ملاحظة للمبيعات</label>
      <textarea class="textarea" id="notes" name="notes" placeholder="مكان التركيب أو موعد الزيارة"><?php echo h($old['notes'] ?? ''); ?></textarea>
    </div>
    <p class="hint">بعد إكمال التصميم يظهر السعر النهائي. من صفحة الطلب يُصدَّر ملف PDF بعنوان عرض منظومة.</p>
  </section>

  <div id="stepError" class="notice notice-low is-hidden"></div>
  <div class="wizard-nav">
    <button type="button" class="btn is-hidden" id="prevBtn">السابق</button>
    <button type="button" class="btn btn-gold" id="nextBtn">التالي</button>
    <button type="submit" class="btn is-hidden" id="draftBtn" name="action" value="draft">حفظ مسودة</button>
    <button type="submit" class="btn btn-gold is-hidden" id="sendBtn" name="action" value="send">إرسال إلى المبيعات</button>
  </div>
</form>
<script>
window.SOLAR = <?php echo json_for_script([
    'openStep' => $openStep,
    'isAdmin' => is_admin(),
    'settings' => $jsSettings,
    'products' => $jsProducts,
]); ?>;
</script>
<?php
render_footer(['assets/js/quote.js?v=8']);
