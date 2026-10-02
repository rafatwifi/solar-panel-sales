<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/layout.php';

if (current_user()) {
    redirect(is_guest() ? 'quote.php' : 'dashboard.php');
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (($_POST['guest'] ?? '') === '1')) {
    check_csrf();
    start_guest();
    redirect('quote.php');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (time() - (int) ($_SESSION['login_fail_at'] ?? 0) > 600) {
        $_SESSION['login_fails'] = 0;
    }
    if ((int) ($_SESSION['login_fails'] ?? 0) >= 8) {
        $error = 'محاولات كثيرة. انتظر عشر دقائق ثم أعد المحاولة.';
    } else {
        check_csrf();
        $result = attempt_login($_POST['username'] ?? '', $_POST['password'] ?? '');
        if ($result === 'ok') {
            redirect('dashboard.php');
        }
        $_SESSION['login_fails'] = (int) ($_SESSION['login_fails'] ?? 0) + 1;
        $_SESSION['login_fail_at'] = time();
        $error = $result === 'inactive' ? 'هذا الحساب موقف' : 'اسم المستخدم أو كلمة المرور غير صحيحة';
    }
}

$settings = app_settings();
$showDemo = default_password_active();
render_head('دخول');
?>
<body class="login-page">
  <div>
    <div class="login-card">
      <svg class="sun" viewBox="0 0 84 84" aria-hidden="true">
        <circle cx="42" cy="42" r="16" fill="#f6c453"/>
        <g stroke="#f6c453" stroke-width="4" stroke-linecap="round">
          <path d="M42 6v10M42 68v10M6 42h10M68 42h10M15 15l8 8M61 61l8 8M69 15l-8 8M23 61l-8 8"/>
        </g>
      </svg>
      <h1><?php echo h($settings['company_name']); ?></h1>
      <p class="lead">حساب وتصميم منظومة الطاقة الشمسية</p>
      <?php if (!storage_writable()): ?><div class="notice notice-low">مجلد data أو uploads غير قابل للكتابة. من الاستضافة اجعل صلاحيته 775.</div><?php endif; ?>
      <?php if ($error): ?><div class="notice notice-low"><?php echo h($error); ?></div><?php endif; ?>
      <?php render_flash_box(); ?>
      <?php if ($showDemo): ?>
        <div class="demo-box">
          <div>اسم المستخدم: <b class="ltr">admin</b></div>
          <div>كلمة المرور: <b class="ltr">admin123</b></div>
          <div class="hint">غيّر كلمة المرور بعد أول دخول.</div>
        </div>
      <?php endif; ?>
      <form method="post" autocomplete="off">
        <?php echo csrf_field(); ?>
        <div class="field">
          <label for="username">اسم المستخدم</label>
          <input class="input ltr" id="username" name="username" autocomplete="username" required>
        </div>
        <div class="field">
          <label for="password">كلمة المرور</label>
          <input class="input ltr" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="btn btn-gold" type="submit" style="width:100%">دخول</button>
      </form>
      <div class="login-or">أو</div>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="guest" value="1">
        <button class="btn btn-navy" type="submit" style="width:100%">دخول الضيوف</button>
        <p class="hint" style="text-align:center">بدون حساب: صمّم المنظومة، شوف السعر، وأرسلها للمبيعات</p>
      </form>
    </div>
    <p class="login-foot">البيانات تُحفظ بصيغة JSON داخل الاستضافة</p>
  </div>
</body>
</html>
