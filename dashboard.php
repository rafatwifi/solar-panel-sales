<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_login();

if (isset($_GET['hide_guide'])) {
    $_SESSION['hide_guide'] = 1;
    redirect('dashboard.php');
}

$user = current_user();
$orders = read_json('orders', []);
if (!is_admin()) {
    $orders = array_values(array_filter($orders, function ($order) use ($user) {
        return ($order['employee_id'] ?? '') === $user['id'];
    }));
}
usort($orders, function ($a, $b) {
    return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
});

$pending = 0;
$today = 0;
$drafts = 0;
$todayKey = date('Y-m-d');
foreach ($orders as $order) {
    if (($order['status'] ?? '') === 'sent') {
        $pending++;
    }
    if (($order['status'] ?? '') === 'draft') {
        $drafts++;
    }
    $created = substr((string) ($order['created_at'] ?? ''), 0, 10);
    if ($created === $todayKey) {
        $today++;
    }
}
$productCount = count(read_json('products', []));
$hour = (int) date('G');
$hello = $hour < 12 ? 'صباح الخير' : 'مساء الخير';

render_header('الرئيسية', 'dashboard.php');
?>
<p class="hint" style="margin-top:0"><?php echo h($hello); ?>، <?php echo h($user['name']); ?></p>

<?php if (!storage_writable()): ?>
  <div class="notice notice-low">مجلد data أو uploads غير قابل للكتابة. من لوحة الاستضافة امنحه صلاحية 775.</div>
<?php endif; ?>

<?php if (is_admin() && default_password_active()): ?>
  <div class="notice notice-high">كلمة مرور المدير ما زالت الافتراضية. غيّرها من صفحة الحساب.</div>
<?php endif; ?>

<?php if (is_admin() && empty($_SESSION['hide_guide'])): ?>
  <div class="card">
    <h2>بداية سريعة</h2>
    <p>1. أضف المواد وصورها وأسعارها من صفحة المنتجات.</p>
    <p>2. من المنتجات عدّل أسعار بوردات الكومباينر، وشغّل إظهار السعر إذا أردت أن يراها الموظف. من الإعدادات سعّر أجور تركيب الألواح والأجور الأخرى. هذه الأجور لا يراها الموظف.</p>
    <p>3. أضف حسابات الموظفين. بعد ما يكتمل التصميم يوصل الطلب إليك في المبيعات.</p>
    <a class="btn btn-sm" href="dashboard.php?hide_guide=1">إخفاء</a>
  </div>
<?php endif; ?>

<a class="hero-cta" href="quote.php">
  <span>تصميم منظومة جديدة</span>
  <small>اسم الزبون، الأمبير، الألواح، الإنفرتر، البطارية، والبوردات</small>
</a>

<div class="stats">
  <div class="stat"><b><?php echo count($orders); ?></b><span>الطلبات</span></div>
  <div class="stat"><b><?php echo (int) $pending; ?></b><span>بانتظار المبيعات</span></div>
  <div class="stat"><b><?php echo (int) $today; ?></b><span>طلبات اليوم</span></div>
  <div class="stat"><b><?php echo is_admin() ? (int) $productCount : (int) $drafts; ?></b><span><?php echo is_admin() ? 'المنتجات' : 'مسودات'; ?></span></div>
</div>

<div class="card">
  <div class="sheet-top">
    <h2>آخر الطلبات</h2>
    <a class="btn btn-sm" href="orders.php">كل الطلبات</a>
  </div>
  <?php if (!$orders): ?>
    <p class="muted">لا توجد طلبات بعد.</p>
  <?php else: ?>
    <div class="orders">
      <?php foreach (array_slice($orders, 0, 6) as $order): ?>
        <a class="order-row" href="order.php?id=<?php echo h($order['id']); ?>">
          <div>
            <strong><?php echo h($order['customer_name'] ?? ''); ?></strong>
            <small class="ltr"><?php echo h($order['code'] ?? ''); ?> · <?php echo h($order['customer_phone'] ?? ''); ?></small>
            <?php $materials = order_materials_text($order, is_admin()); ?>
            <?php if ($materials !== ''): ?><small><?php echo h($materials); ?></small><?php endif; ?>
          </div>
          <span class="status status-<?php echo h($order['status'] ?? 'draft'); ?>"><?php echo h(status_label($order['status'] ?? '')); ?></span>
          <span class="money"><?php echo h(money($order['grand_total'] ?? 0)); ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php
render_footer();
