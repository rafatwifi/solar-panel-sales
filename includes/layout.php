<?php

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function icon($name)
{
    $paths = [
        'home' => '<path d="M4 11.5 12 4l8 7.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-8.5z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"/>',
        'orders' => '<path d="M7 3h8l4 4v14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M15 3v5h5M8 13h8M8 17h6"/>',
        'box' => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5v-9z"/><path d="M12 12 3 7.5M12 12l9-4.5M12 12v9"/>',
        'users' => '<path d="M16 19v-1a3 3 0 0 0-3-3H7a3 3 0 0 0-3 3v1"/><circle cx="10" cy="8" r="3"/><path d="M20 19v-1a3 3 0 0 0-2.2-2.9M16 5.1a3 3 0 0 1 0 5.8"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M4.8 4.8l1.5 1.5M17.7 17.7l1.5 1.5M19.2 4.8l-1.5 1.5M6.3 17.7l-1.5 1.5"/>',
        'logout' => '<path d="M9 6H5v12h4M10 12h10M16 8l4 4-4 4"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    ];
    $path = $paths[$name] ?? '';
    return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round">' . $path . '</svg>';
}

function render_head($title)
{
    $settings = app_settings();
    ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#10243f">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title><?php echo h($title); ?> | <?php echo h($settings['company_name']); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/css/app.css?v=4">
</head>
    <?php
}

function render_flash_box()
{
    $flash = take_flash();
    if (!$flash) {
        return;
    }
    $type = ($flash['type'] ?? '') === 'bad' ? 'low' : ((($flash['type'] ?? '') === 'info') ? 'high' : 'ok');
    echo '<div class="notice notice-' . $type . '">' . h($flash['msg'] ?? '') . '</div>';
}

function render_header($title, $active = '')
{
    $user = current_user();
    $settings = app_settings();
    $logo = upload_url($settings['logo'] ?? '');
    $pending = 0;
    if ($user && ($user['role'] ?? '') === 'admin') {
        foreach (read_json('orders', []) as $order) {
            if (($order['status'] ?? '') === 'sent') {
                $pending++;
            }
        }
    }
    $items = [
        ['dashboard.php', 'home', 'الرئيسية', 'staff'],
        ['quote.php', 'sun', 'تصميم منظومة', 'all'],
        ['orders.php', 'orders', is_guest() ? 'طلباتي' : 'الطلبات', 'all'],
        ['products.php', 'box', 'المنتجات', 'admin'],
        ['users.php', 'users', 'الموظفون', 'admin'],
        ['settings.php', 'gear', 'الإعدادات', 'admin'],
    ];
    render_head($title);
    ?>
<body>
<a class="skip" href="#content">تخطي إلى المحتوى</a>
<div class="shell">
  <input type="checkbox" id="navToggle" class="nav-check" aria-hidden="true">
  <aside class="side">
    <a class="brand" href="<?php echo is_guest() ? 'quote.php' : 'dashboard.php'; ?>">
      <?php if ($logo): ?>
        <img src="<?php echo h($logo); ?>" alt="">
      <?php else: ?>
        <span class="mark"><?php echo icon('sun'); ?></span>
      <?php endif; ?>
      <span>
        <strong><?php echo h($settings['company_name']); ?></strong>
        <small>تصميم المنظومات</small>
      </span>
    </a>
    <nav class="nav">
      <?php foreach ($items as $item): ?>
        <?php if ($item[3] === 'admin' && !is_admin()) continue; ?>
        <?php if ($item[3] === 'staff' && is_guest()) continue; ?>
        <a class="<?php echo $active === $item[0] ? 'active' : ''; ?>" href="<?php echo h($item[0]); ?>">
          <?php echo icon($item[1]); ?>
          <span><?php echo h($item[2]); ?></span>
          <?php if ($item[0] === 'orders.php' && $pending > 0): ?>
            <span class="count"><?php echo (int) $pending; ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a class="nav-link" href="logout.php"><?php echo icon('logout'); ?> خروج</a>
    </div>
  </aside>
  <label for="navToggle" class="nav-backdrop" aria-label="إغلاق القائمة"></label>
  <div class="main">
    <header class="topbar">
      <label for="navToggle" class="icon-btn only-mobile" aria-label="فتح القائمة"><?php echo icon('menu'); ?></label>
      <h1><?php echo h($title); ?></h1>
      <?php $roleLabel = is_admin() ? 'إدارة' : (is_guest() ? 'ضيف' : 'موظف'); ?>
      <?php if (is_guest()): ?>
      <span class="userchip">
        <span class="avatar"><?php echo h(first_char($user['name'] ?? '')); ?></span>
        <span>
          <strong><?php echo h($user['name'] ?? ''); ?></strong>
          <small><?php echo h($roleLabel); ?></small>
        </span>
      </span>
      <?php else: ?>
      <a class="userchip" href="account.php">
        <span class="avatar"><?php echo h(first_char($user['name'] ?? '')); ?></span>
        <span>
          <strong><?php echo h($user['name'] ?? ''); ?></strong>
          <small><?php echo h($roleLabel); ?></small>
        </span>
      </a>
      <?php endif; ?>
    </header>
    <div class="content" id="content">
    <?php
    render_flash_box();
}

function render_footer($scripts = [])
{
    ?>
    </div>
  </div>
</div>
<script src="assets/js/app.js?v=1"></script>
    <?php foreach ($scripts as $script): ?>
<script src="<?php echo h($script); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
    <?php
}
