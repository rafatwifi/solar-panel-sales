<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_admin();

$settings = app_settings();
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf();
    $next = $settings;
    $next['company_name'] = clean_text($_POST['company_name'] ?? '', 80);
    $next['company_phone'] = clean_text($_POST['company_phone'] ?? '', 30);
    $next['currency'] = clean_text($_POST['currency'] ?? '', 12);
    $next['ac_voltage'] = num($_POST['ac_voltage'] ?? 220);
    $next['day_hours'] = num($_POST['day_hours'] ?? 6);
    $next['night_hours'] = num($_POST['night_hours'] ?? 8);
    $next['day_panel_factor'] = num($_POST['day_panel_factor'] ?? 1.75);
    $next['peak_sun_hours'] = num($_POST['peak_sun_hours'] ?? 5);
    $next['pv_efficiency_percent'] = num($_POST['pv_efficiency_percent'] ?? 75);
    $next['inverter_margin_percent'] = num($_POST['inverter_margin_percent'] ?? 25);
    $next['battery_reserve_percent'] = num($_POST['battery_reserve_percent'] ?? 20);
    $next['battery_efficiency_percent'] = num($_POST['battery_efficiency_percent'] ?? 90);
    $next['battery_dod_percent'] = max(1, 100 - $next['battery_reserve_percent']);
    $next['cost_panel_install'] = max(0, (int) round(num($_POST['cost_panel_install'] ?? 0)));
    $next['cost_other'] = max(0, (int) round(num($_POST['cost_other'] ?? 0)));

    if (str_len($next['company_name']) < 2) {
        $errors[] = 'اكتب اسم المركز';
    }
    if ($next['currency'] === '') {
        $next['currency'] = 'د.ع';
    }
    if ($next['ac_voltage'] < 12 || $next['ac_voltage'] > 1000) {
        $errors[] = 'جهد الكهرباء غير منطقي';
    }
    if ($next['day_hours'] < 0.1 || $next['day_hours'] > 24 || $next['night_hours'] < 0.1 || $next['night_hours'] > 24) {
        $errors[] = 'ساعات التشغيل بين 0.1 و 24';
    }
    if ($next['day_panel_factor'] < 0.01 || $next['day_panel_factor'] > 100) {
        $errors[] = 'معامل التجهيز النهاري بين 0.01 و 100';
    }
    if ($next['peak_sun_hours'] < 0.5 || $next['peak_sun_hours'] > 16) {
        $errors[] = 'ساعات الشمس بين 0.5 و 16';
    }
    foreach (['pv_efficiency_percent' => 'كفاءة الألواح', 'battery_efficiency_percent' => 'كفاءة البطارية'] as $key => $label) {
        if ($next[$key] < 1 || $next[$key] > 100) {
            $errors[] = $label . ' بين 1 و 100';
        }
    }
    if ($next['battery_reserve_percent'] < 0 || $next['battery_reserve_percent'] > 99) {
        $errors[] = 'النسبة المتبقية في البطارية بين 0 و 99';
    }
    if ($next['inverter_margin_percent'] < 0 || $next['inverter_margin_percent'] > 300) {
        $errors[] = 'هامش الإنفرتر بين 0 و 300';
    }
    $logo = (string) ($settings['logo'] ?? '');
    if (!$errors && isset($_POST['remove_logo'])) {
        delete_upload($logo);
        $logo = '';
    }
    if (!$errors) {
        $upload = store_image('logo');
        if (!$upload['ok']) {
            $errors[] = $upload['error'];
        } elseif (!empty($upload['path'])) {
            if ($logo !== '') {
                delete_upload($logo);
            }
            $logo = $upload['path'];
        }
    }
    $next['logo'] = $logo;
    unset($next['cost_combiner_ac'], $next['cost_dc']);

    if (!$errors) {
        if (!write_json('settings', $next)) {
            $errors[] = 'تعذر حفظ الإعدادات';
        } else {
            app_settings(true);
            flash('تم حفظ الإعدادات');
            redirect('settings.php');
        }
    }
    $settings = array_merge($settings, $next);
}

render_header('الإعدادات', 'settings.php');
?>
<?php foreach ($errors as $error): ?>
  <div class="notice notice-low"><?php echo h($error); ?></div>
<?php endforeach; ?>
<form method="post" enctype="multipart/form-data">
  <?php echo csrf_field(); ?>
  <div class="card">
    <h2>بيانات المركز</h2>
    <div class="grid-2">
      <div class="field">
        <label for="company_name">الاسم</label>
        <input class="input" id="company_name" name="company_name" value="<?php echo h($settings['company_name']); ?>">
      </div>
      <div class="field">
        <label for="company_phone">الهاتف</label>
        <input class="input ltr" id="company_phone" name="company_phone" value="<?php echo h($settings['company_phone']); ?>">
      </div>
    </div>
    <div class="field">
      <label for="currency">العملة</label>
      <input class="input" id="currency" name="currency" value="<?php echo h($settings['currency']); ?>">
    </div>
    <div class="field">
      <label for="image">شعار المركز</label>
      <input class="input" id="image" name="logo" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
      <?php $logo = upload_url($settings['logo'] ?? ''); ?>
      <?php if ($logo): ?>
        <img id="imagePreview" class="preview-img" src="<?php echo h($logo); ?>" alt="">
        <label class="check"><input type="checkbox" name="remove_logo" value="1"> حذف الشعار</label>
      <?php else: ?>
        <img id="imagePreview" class="preview-img" alt="" hidden>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2>طريقة الحساب</h2>
    <p class="hint">اكتب الرقم الذي تريده أنت، والحساب يستخدمه كما هو. ساعات التشغيل يقدر الموظف يغيرها لكل زبون.</p>
    <div class="grid-3">
      <div class="field">
        <label for="day_panel_factor">معامل التجهيز النهاري</label>
        <input class="input num" id="day_panel_factor" name="day_panel_factor" value="<?php echo h($settings['day_panel_factor']); ?>">
        <p class="hint">عدد الألواح = أمبير النهار × هذا الرقم. مثال: 10 × 1.75 = 18 لوح بعد التقريب للأعلى.</p>
      </div>
      <div class="field">
        <label for="ac_voltage">جهد الكهرباء</label>
        <input class="input num" id="ac_voltage" name="ac_voltage" value="<?php echo h($settings['ac_voltage']); ?>">
        <p class="hint">غالباً 220 فولت</p>
      </div>
      <div class="field">
        <label for="day_hours">ساعات النهار</label>
        <input class="input num" id="day_hours" name="day_hours" value="<?php echo h($settings['day_hours']); ?>">
      </div>
      <div class="field">
        <label for="night_hours">ساعات الليل</label>
        <input class="input num" id="night_hours" name="night_hours" value="<?php echo h($settings['night_hours']); ?>">
      </div>
      <div class="field">
        <label for="peak_sun_hours">ساعات ذروة الشمس</label>
        <input class="input num" id="peak_sun_hours" name="peak_sun_hours" value="<?php echo h($settings['peak_sun_hours']); ?>">
        <p class="hint">في العراق غالباً بين 4.5 و 6</p>
      </div>
      <div class="field">
        <label for="pv_efficiency_percent">كفاءة المنظومة %</label>
        <input class="input num" id="pv_efficiency_percent" name="pv_efficiency_percent" value="<?php echo h($settings['pv_efficiency_percent']); ?>">
        <p class="hint">من 1 إلى 100. مثال: 75 تخصم فقد الأسلاك والحرارة، و1 تحسب تقريباً بدون كفاءة.</p>
      </div>
      <div class="field">
        <label for="inverter_margin_percent">هامش الإنفرتر %</label>
        <input class="input num" id="inverter_margin_percent" name="inverter_margin_percent" value="<?php echo h($settings['inverter_margin_percent']); ?>">
        <p class="hint">0 يعني نفس الحمل بالضبط. 25 يعني أكبر من الحمل بـ 25%.</p>
      </div>
      <div class="field">
        <label for="battery_reserve_percent">تستهلك البطارية لحد ما يبقى %</label>
        <input class="input num" id="battery_reserve_percent" name="battery_reserve_percent" value="<?php echo h($settings['battery_reserve_percent']); ?>">
        <p class="hint">1 يعني تستهلكها لحد ما يبقى 1%. 20 يعني يبقى 20% وما ينحسب ضمن الاستخدام.</p>
      </div>
      <div class="field">
        <label for="battery_efficiency_percent">كفاءة البطارية %</label>
        <input class="input num" id="battery_efficiency_percent" name="battery_efficiency_percent" value="<?php echo h($settings['battery_efficiency_percent']); ?>">
        <p class="hint">من 1 إلى 100. كل ما قل الرقم كبرت البطارية المطلوبة.</p>
      </div>
    </div>
    <details>
      <summary>كيف ينحسب المطلوب؟</summary>
      <p>واط النهار = أمبير النهار × الجهد، وواط الليل بنفس الطريقة.</p>
      <p>طاقة اليوم = واط النهار × ساعات النهار + واط الليل × ساعات الليل.</p>
      <p>عدد الألواح = أمبير التجهيز النهاري × معامل الألواح، ثم يُقرَّب للأعلى. قدرة اللوح ما تغيّر العدد، بس تغيّر السعر.</p>
      <p>الإنفرتر = أكبر حمل بين النهار والليل، ثم يضاف هامش الأمان.</p>
      <p>البطارية = طاقة الليل ÷ ((100 − النسبة المتبقية) × كفاءة البطارية). إذا كتبت 1، يُستخدم 99% من سعة البطارية.</p>
      <p>البورد = أكبر أمبير بين النهار والليل. حتى 20 يستخدم مدى 10-20، وحتى 30 يستخدم 20-30، وأكثر من 30 يستخدم 30-40. سعر البورد من صفحة المنتجات.</p>
    </details>
  </div>

  <div class="card">
    <h2>بنود لا يراها الموظف</h2>
    <p class="hint">بوردات الكومباينر AC و DC صارت منتجات. سعّرها من صفحة المنتجات، وشغّل «إظهار السعر» إذا أردت أن يرى الموظف سعر البورد. هنا تبقى أجور التركيب والأجور الأخرى فقط، والموظف يرى السعر النهائي رقماً واحداً.</p>
    <div class="grid-2">
      <div class="field">
        <label for="cost_panel_install">أجور تركيب اللوح الواحد</label>
        <input class="input num" id="cost_panel_install" name="cost_panel_install" value="<?php echo h($settings['cost_panel_install']); ?>">
        <p class="hint">يُضرب بعدد الألواح في كل طلب.</p>
      </div>
      <div class="field">
        <label for="cost_other">أجور أخرى</label>
        <input class="input num" id="cost_other" name="cost_other" value="<?php echo h($settings['cost_other']); ?>">
      </div>
    </div>
  </div>
  <button class="btn btn-gold" type="submit">حفظ الإعدادات</button>
</form>
<?php
render_footer();
