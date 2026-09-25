<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_login();

$user = current_user();
$orders = read_json('orders', []);
if (!is_admin()) {
    $orders = array_values(array_filter($orders, function ($order) use ($user) {
        return ($order['employee_id'] ?? '') === $user['id'];
    }));
}
$q = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$statuses = ['draft', 'sent', 'reviewing', 'approved', 'done', 'rejected'];
if (!in_array($status, $statuses, true)) {
    $status = '';
}
$visible = [];
foreach ($orders as $order) {
    if ($status !== '' && ($order['status'] ?? '') !== $status) {
        continue;
    }
    if ($q !== '') {
        $hay = ($order['customer_name'] ?? '') . ' ' . ($order['customer_phone'] ?? '') . ' ' . ($order['code'] ?? '') . ' ' . ($order['employee_name'] ?? '');
        if (function_exists('mb_stripos')) {
            if (mb_stripos($hay, $q, 0, 'UTF-8') === false) {
                continue;
            }
        } elseif (stripos($hay, $q) === false) {
            continue;
        }
    }
    $visible[] = $order;
}
usort($visible, function ($a, $b) {
    return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
});

render_header('الطلبات', 'orders.php');
?>
<form class="filters" method="get">
  <input class="input" type="search" name="q" value="<?php echo h($q); ?>" placeholder="اسم، هاتف، أو رقم الطلب">
  <select class="select" name="status" style="max-width:220px">
    <option value="">كل الحالات</option>
    <?php foreach ($statuses as $key): ?>
      <option value="<?php echo h($key); ?>"<?php echo $status === $key ? ' selected' : ''; ?>><?php echo h(status_label($key)); ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn" type="submit">تصفية</button>
  <a class="btn btn-gold" href="quote.php">طلب جديد</a>
</form>
<?php if (!$visible): ?>
  <div class="card"><p>لا توجد طلبات.</p></div>
<?php else: ?>
  <div class="orders">
    <?php foreach ($visible as $order): ?>
      <a class="order-row" href="order.php?id=<?php echo h($order['id']); ?>">
        <div>
          <strong><?php echo h($order['customer_name'] ?? ''); ?></strong>
          <small>
            <span class="ltr"><?php echo h($order['code'] ?? ''); ?></span>
            · <span class="ltr"><?php echo h($order['customer_phone'] ?? ''); ?></span>
            · <?php echo h($order['employee_name'] ?? ''); ?>
            · <?php echo h(dt($order['created_at'] ?? '')); ?>
          </small>
          <?php $materials = order_materials_text($order, is_admin()); ?>
          <?php if ($materials !== ''): ?><small><?php echo h($materials); ?></small><?php endif; ?>
        </div>
        <span class="status status-<?php echo h($order['status'] ?? 'draft'); ?>"><?php echo h(status_label($order['status'] ?? '')); ?></span>
        <span class="money"><?php echo h(money($order['grand_total'] ?? 0)); ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
render_footer();
