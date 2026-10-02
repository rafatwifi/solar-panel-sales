<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/calc.php';
require __DIR__ . '/includes/layout.php';
require_login();

function meter_width($selected, $required)
{
    $required = (float) $required;
    $selected = (float) $selected;
    if ($required <= 0) {
        return $selected > 0 ? 100 : 0;
    }
    return (int) max(0, min(100, round(($selected / $required) * 100)));
}

$id = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));
$order = find_order($id);
$user = current_user();
if (!$order) {
    flash('الطلب غير موجود', 'bad');
    redirect('orders.php');
}
$owns = ($order['employee_id'] ?? '') === ($user['id'] ?? '');
if (!is_admin() && !$owns) {
    flash('لا يمكنك فتح هذا الطلب', 'bad');
    redirect('orders.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!is_admin()) {
        flash('تعديل حالة الطلب للإدارة فقط', 'bad');
        redirect('order.php?id=' . urlencode($id));
    }
    check_csrf();
    $orders = read_json('orders', []);
    $index = -1;
    foreach ($orders as $i => $row) {
        if (($row['id'] ?? '') === $id) {
            $index = $i;
            break;
        }
    }
    if ($index < 0) {
        flash('الطلب غير موجود', 'bad');
        redirect('orders.php');
    }
    if (($_POST['do'] ?? '') === 'delete') {
        array_splice($orders, $index, 1);
        write_json('orders', array_values($orders));
        flash('تم حذف الطلب');
        redirect('orders.php');
    }
    $statuses = ['draft', 'sent', 'reviewing', 'approved', 'done', 'rejected'];
    $status = (string) ($_POST['status'] ?? '');
    if (!in_array($status, $statuses, true)) {
        $status = $orders[$index]['status'] ?? 'sent';
    }
    $panelInstall = max(0, (int) round(num($_POST['cost_panel_install'] ?? 0)));
    $otherCost = max(0, (int) round(num($_POST['cost_other'] ?? 0)));
    $panelMeta = [];
    foreach ($orders[$index]['hidden_costs'] ?? [] as $row) {
        if (($row['key'] ?? '') === 'panel_install') {
            $panelMeta = $row;
            break;
        }
    }
    $panelRow = [
        'key' => 'panel_install',
        'name' => 'أجور تركيب الألواح',
        'amount' => $panelInstall,
    ];
    if (isset($panelMeta['qty'])) {
        $panelRow['qty'] = $panelMeta['qty'];
        $panelRow['unit'] = $panelMeta['unit'] ?? 0;
    }
    $hasCombinerItem = false;
    foreach ($orders[$index]['items'] ?? [] as $itemRow) {
        if (is_combiner_category($itemRow['category'] ?? '')) {
            $hasCombinerItem = true;
            break;
        }
    }
    $hiddenCosts = [];
    if (!$hasCombinerItem) {
        foreach ($orders[$index]['hidden_costs'] ?? [] as $row) {
            $legacyKey = (string) ($row['key'] ?? '');
            if ($legacyKey === 'combiner_ac' || $legacyKey === 'dc') {
                $hiddenCosts[] = $row;
            }
        }
    }
    $hiddenCosts[] = $panelRow;
    $hiddenCosts[] = ['key' => 'other', 'name' => 'أجور أخرى', 'amount' => $otherCost];
    $fee = hidden_cost_total($hiddenCosts);
    $orders[$index]['status'] = $status;
    $orders[$index]['hidden_costs'] = $hiddenCosts;
    $orders[$index]['installation_fee'] = $fee;
    $orders[$index]['grand_total'] = (int) ($orders[$index]['equipment_total'] ?? 0) + $fee;
    $orders[$index]['admin_note'] = clean_text($_POST['admin_note'] ?? '', 1000);
    $orders[$index]['updated_at'] = now_iso();
    if ($status === 'sent' && empty($orders[$index]['sent_at'])) {
        $orders[$index]['sent_at'] = now_iso();
    }
    write_json('orders', $orders);
    flash('تم تحديث الطلب');
    redirect('order.php?id=' . urlencode($id));
}

$order = find_order($id);
$catalog = read_json('products', []);
$result = $order['result'] ?? [];
$input = $order['input'] ?? [];
$settings = app_settings();
$logo = upload_url($settings['logo'] ?? '');
$wa = whatsapp_link($order['customer_phone'] ?? '');
$panelStatus = $result['panel_status'] ?? 'ok';
$inverterStatus = $result['inverter_status'] ?? 'ok';
$batteryStatus = $result['battery_status'] ?? 'ok';
$batteryNeed = (float) ($result['battery_kwh'] ?? 0);
$batteryChosen = (float) ($result['chosen_battery_kwh'] ?? 0);
if ($batteryNeed <= 0 && $batteryChosen <= 0) {
    $batteryMessage = 'لا حاجة لبطارية لأن الحمل الليلي صفر';
    $batteryStatus = 'ok';
} else {
    $batteryMessage = fit_text('البطارية', $batteryStatus, kwh_text($batteryChosen), kwh_text($batteryNeed));
}
$panelQtyChosen = (int) ($result['panel_qty'] ?? 0);
if ($panelQtyChosen <= 0) {
    foreach ($order['items'] ?? [] as $row) {
        if (($row['category'] ?? '') === 'panel') {
            $panelQtyChosen = (int) ($row['qty'] ?? 0);
            break;
        }
    }
}
if (array_key_exists('panel_count', $result)) {
    $panelNeedCount = (int) $result['panel_count'];
    if ($panelNeedCount <= 0 && $panelQtyChosen <= 0) {
        $panelMessage = 'لا حاجة لألواح لأن الاستهلاك صفر';
        $panelStatus = 'ok';
    } else {
        $panelMessage = fit_text('الألواح', $panelStatus, $panelQtyChosen . ' لوح', $panelNeedCount . ' لوح');
    }
    $panelMeterSelected = $panelQtyChosen;
    $panelMeterNeed = $panelNeedCount;
} else {
    $panelMessage = fit_text('الألواح', $panelStatus, watts_text($result['panel_watts'] ?? 0), watts_text($result['pv_watts'] ?? 0));
    $panelMeterSelected = $result['panel_watts'] ?? 0;
    $panelMeterNeed = $result['pv_watts'] ?? 0;
}
$inverterMessage = fit_text('الإنفرتر', $inverterStatus, watts_text($result['chosen_inverter_watts'] ?? 0), watts_text($result['inverter_watts'] ?? 0));
$systemAmps = system_amps($input['day_amps'] ?? 0, $input['night_amps'] ?? 0);
$boardRange = recommended_board_range($systemAmps);
$boardNeedAmp = $systemAmps > 40 ? $systemAmps : ($boardRange ? (float) $boardRange['max'] : 0);

render_header('طلب ' . ($order['code'] ?? ''), 'orders.php');
?>
<div class="print-head">
  <div>
    <strong><?php echo h($settings['company_name']); ?></strong>
    <?php if (!empty($settings['company_phone'])): ?><div class="ltr"><?php echo h($settings['company_phone']); ?></div><?php endif; ?>
  </div>
  <div>
    <strong>عرض منظومة شمسية</strong>
    <div class="ltr"><?php echo h($order['code'] ?? ''); ?></div>
  </div>
</div>

<div class="card">
  <div class="sheet-top">
    <div>
      <?php if ($logo): ?><img class="logo-lg" src="<?php echo h($logo); ?>" alt=""><?php endif; ?>
      <h2 style="margin:8px 0 0"><?php echo h($order['customer_name'] ?? ''); ?></h2>
      <div><a class="ltr" href="tel:<?php echo h(preg_replace('/\s+/', '', (string) ($order['customer_phone'] ?? ''))); ?>"><?php echo h($order['customer_phone'] ?? ''); ?></a></div>
      <p class="muted" style="margin:6px 0 0">
        <?php echo h($order['code'] ?? ''); ?>
        · <?php echo h($order['employee_name'] ?? ''); ?>
        · <?php echo h(dt($order['created_at'] ?? '')); ?>
      </p>
    </div>
    <span class="status status-<?php echo h($order['status'] ?? 'draft'); ?>"><?php echo h(status_label($order['status'] ?? '')); ?></span>
  </div>
  <div class="row-actions no-print" style="margin-top:12px">
    <?php if (($order['status'] ?? '') === 'draft'): ?>
      <a class="btn btn-gold btn-sm" href="quote.php?id=<?php echo h($order['id']); ?>">إكمال التصميم</a>
    <?php endif; ?>
    <a class="btn btn-gold btn-sm" href="offer.php?id=<?php echo h($order['id']); ?>&download=1">تصدير عرض PDF</a>
    <?php if ($wa): ?><a class="btn btn-sm" target="_blank" rel="noopener" href="<?php echo h($wa); ?>">واتساب</a><?php endif; ?>
    <?php if (is_admin()): ?>
      <button class="btn btn-sm" type="button" onclick="printQuote('customer')">طباعة للزبون</button>
      <button class="btn btn-sm" type="button" onclick="printQuote('internal')">طباعة داخلية</button>
    <?php endif; ?>
  </div>
</div>

<div class="stats">
  <div class="stat"><b><?php echo h(amp_text($input['day_amps'] ?? 0)); ?></b><span>أمبير نهاري · <?php echo h($input['day_hours'] ?? 0); ?> ساعة</span></div>
  <div class="stat"><b><?php echo h(amp_text($input['night_amps'] ?? 0)); ?></b><span>أمبير ليلي · <?php echo h($input['night_hours'] ?? 0); ?> ساعة</span></div>
  <div class="stat"><b><?php echo h(rtrim(rtrim(number_format((float) ($result['daily_kwh'] ?? 0), 2, '.', ''), '0'), '.')); ?></b><span>كيلو واط ساعة باليوم</span></div>
  <div class="stat"><b><?php echo h(watts_text($result['day_watts'] ?? 0)); ?></b><span>حمل النهار · الليل <?php echo h(watts_text($result['night_watts'] ?? 0)); ?></span></div>
</div>

<div class="flow">
  <?php
    $blocks = [
        ['panel', 'الألواح', $panelMessage, $panelStatus, $panelMeterSelected, $panelMeterNeed],
        ['inverter', 'الإنفرتر', $inverterMessage, $inverterStatus, $result['chosen_inverter_watts'] ?? 0, $result['inverter_watts'] ?? 0],
        ['battery', 'البطارية', $batteryMessage, $batteryStatus, $batteryChosen, $batteryNeed],
    ];
    foreach ([['combiner_ac', 'بورد AC'], ['combiner_dc', 'بورد DC']] as $pair) {
        $boardItem = null;
        foreach ($order['items'] ?? [] as $row) {
            if (($row['category'] ?? '') === $pair[0]) {
                $boardItem = $row;
                break;
            }
        }
        $statusKey = $pair[0] . '_status';
        if (!$boardItem && !array_key_exists($statusKey, $result)) {
            continue;
        }
        $notice = board_notice($pair[1], $boardItem, $systemAmps, $result[$statusKey] ?? null);
        $chosenAmp = $boardItem ? (float) ($boardItem['amp_max'] ?? 0) : 0;
        $blocks[] = [$pair[0], $pair[1], $notice['text'], $notice['status'], $chosenAmp, $boardNeedAmp];
    }
    foreach ($blocks as $block):
        $item = null;
        foreach ($order['items'] ?? [] as $row) {
            if (($row['category'] ?? '') === $block[0]) {
                $item = $row;
                break;
            }
        }
        $img = $item ? upload_url($item['image'] ?? '') : '';
  ?>
    <article class="flow-card">
      <?php if ($img): ?>
        <img src="<?php echo h($img); ?>" alt="">
      <?php else: ?>
        <span class="ph ph-<?php echo h($block[0]); ?>"></span>
      <?php endif; ?>
      <span class="tag"><?php echo h($block[1]); ?></span>
      <h3><?php echo h($item['name'] ?? 'غير محدد'); ?></h3>
      <?php if ($item): ?>
        <p class="muted" style="margin:0">العدد <?php echo (int) $item['qty']; ?><?php if (!empty($item['brand'])): ?> · <?php echo h($item['brand']); ?><?php endif; ?><?php $boardSize = product_size_text($item); if (is_combiner_category($item['category'] ?? '') && $boardSize !== ''): ?> · <?php echo h($boardSize); ?><?php endif; ?></p>
      <?php endif; ?>
      <div class="notice notice-<?php echo h($block[3]); ?> no-customer"><?php echo h($block[2]); ?></div>
      <div class="meter <?php echo h($block[3] === 'ok' ? '' : $block[3]); ?>">
        <span style="width:<?php echo meter_width($block[4], $block[5]); ?>%"></span>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<div class="card" style="margin-top:14px">
  <h2><?php echo sees_prices() ? 'تفاصيل السعر' : 'تفاصيل المنظومة'; ?></h2>
  <?php foreach ($order['items'] ?? [] as $item): ?>
    <?php if (!is_admin() && ($item['category'] ?? '') === 'accessory') continue; ?>
    <div class="sum-row">
      <div>
        <strong><?php echo h($item['name'] ?? ''); ?></strong>
        <div class="muted">
          <?php echo h(category_label($item['category'] ?? '')); ?>
          · العدد <?php echo (int) ($item['qty'] ?? 0); ?>
          <?php $measure = product_size_text($item); ?>
          <?php if ($measure !== ''): ?>
            · <?php echo h($measure); ?>
          <?php endif; ?>
        </div>
        <?php if (!empty($item['specs'])): ?><div class="muted"><?php echo nl2br(h($item['specs'])); ?></div><?php endif; ?>
      </div>
      <?php if (viewer_sees_line_price($item, $catalog)): ?><div class="money line-price"><?php echo h(money($item['line_total'] ?? 0)); ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if (is_admin()): ?>
    <div class="admin-only">
      <div class="sum-row"><span>مجموع المواد</span><b><?php echo h(money($order['equipment_total'] ?? 0)); ?></b></div>
      <?php if (!empty($order['hidden_costs']) && is_array($order['hidden_costs'])): ?>
        <?php foreach ($order['hidden_costs'] as $cost): ?>
          <div class="sum-row">
            <span>
              <?php echo h($cost['name'] ?? ''); ?>
              <?php if (($cost['key'] ?? '') === 'panel_install' && !empty($cost['qty'])): ?>
                <small class="muted"><?php echo (int) $cost['qty']; ?> × <?php echo h(money($cost['unit'] ?? 0)); ?></small>
              <?php endif; ?>
            </span>
            <b><?php echo h(money($cost['amount'] ?? 0)); ?></b>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="sum-row"><span>أجور قديمة</span><b><?php echo h(money($order['installation_fee'] ?? 0)); ?></b></div>
      <?php endif; ?>
    </div>
    <div class="grand admin-only"><span>السعر النهائي</span><b><?php echo h(money($order['grand_total'] ?? 0)); ?></b></div>
    <div class="grand grand-customer"><span>سعر المنظومة</span><b><?php echo h(money($order['grand_total'] ?? 0)); ?></b></div>
  <?php else: ?>
    <div class="grand"><span>السعر النهائي</span><b><?php echo h(money($order['grand_total'] ?? 0)); ?></b></div>
  <?php endif; ?>

  <?php if (!empty($order['notes'])): ?>
    <p><strong>ملاحظة الموظف:</strong> <?php echo nl2br(h($order['notes'])); ?></p>
  <?php endif; ?>
  <?php if (is_admin() && !empty($order['admin_note'])): ?>
    <p class="admin-only"><strong>ملاحظة داخلية:</strong> <?php echo nl2br(h($order['admin_note'])); ?></p>
  <?php endif; ?>
</div>

<?php $assumptions = $order['assumptions'] ?? []; ?>
<details class="card no-customer">
  <summary>أرقام الحساب</summary>
  <p>الجهد <?php echo h($assumptions['ac_voltage'] ?? ''); ?> فولت، شمس <?php echo h($assumptions['peak_sun_hours'] ?? ''); ?> ساعة، كفاءة الألواح <?php echo h($assumptions['pv_efficiency_percent'] ?? ''); ?>%، هامش الإنفرتر <?php echo h($assumptions['inverter_margin_percent'] ?? ''); ?>%.</p>
  <p>البطارية تُستهلك لحد ما يبقى <?php echo h($assumptions['battery_reserve_percent'] ?? max(0, 100 - (float) ($assumptions['battery_dod_percent'] ?? 20))); ?>%، كفاءة البطارية <?php echo h($assumptions['battery_efficiency_percent'] ?? ''); ?>%.</p>
  <p>المطلوب: <?php echo isset($result['panel_count']) ? ((int) $result['panel_count'] . ' لوح') : h(watts_text($result['pv_watts'] ?? 0)); ?>، إنفرتر <?php echo h(watts_text($result['inverter_watts'] ?? 0)); ?>، بطارية <?php echo h(kwh_text($batteryNeed)); ?><?php if ($boardRange): ?>، بورد <?php echo h(amp_range_text($boardRange['min'], $boardRange['max'])); ?> (أكبر أمبير <?php echo h(amp_text($systemAmps)); ?>)<?php endif; ?>.</p>
</details>

<?php if (is_admin()): ?>
  <form class="card no-print admin-only" method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo h($order['id']); ?>">
    <h2>متابعة المبيعات</h2>
    <div class="grid-2">
      <div class="field">
        <label for="status">حالة الطلب</label>
        <select class="select" id="status" name="status">
          <?php foreach (['draft', 'sent', 'reviewing', 'approved', 'done', 'rejected'] as $key): ?>
            <option value="<?php echo h($key); ?>"<?php echo ($order['status'] ?? '') === $key ? ' selected' : ''; ?>><?php echo h(status_label($key)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      </div>
    <?php
      $hiddenNow = [];
      foreach ($order['hidden_costs'] ?? [] as $costRow) {
          $hiddenNow[$costRow['key'] ?? ''] = $costRow['amount'] ?? 0;
      }
      if (!$hiddenNow && !empty($order['installation_fee'])) {
          $hiddenNow['other'] = $order['installation_fee'];
      }
    ?>
    <div class="grid-2">
      <div class="field">
        <label for="cost_panel_install">أجور تركيب الألواح</label>
        <input class="input num" id="cost_panel_install" name="cost_panel_install" value="<?php echo h($hiddenNow['panel_install'] ?? 0); ?>">
      </div>
      <div class="field">
        <label for="cost_other">أجور أخرى</label>
        <input class="input num" id="cost_other" name="cost_other" value="<?php echo h($hiddenNow['other'] ?? 0); ?>">
      </div>
    </div>
    <p class="hint">هاتان الأجرتان لا يراهما الموظف. البوردات ضمن المواد ويظهر سعرها فقط إذا كان إظهار السعر شغالاً. تعديل الأجور هنا يخص هذا الطلب فقط.</p>
    <div class="field">
      <label for="admin_note">ملاحظة داخلية</label>
      <textarea class="textarea" id="admin_note" name="admin_note"><?php echo h($order['admin_note'] ?? ''); ?></textarea>
    </div>
    <div class="row-actions">
      <button class="btn btn-gold" type="submit">حفظ المتابعة</button>
      <button class="btn btn-danger" type="submit" name="do" value="delete" data-confirm="حذف هذا الطلب نهائياً؟">حذف الطلب</button>
    </div>
  </form>
<?php endif; ?>
<?php
render_footer();
