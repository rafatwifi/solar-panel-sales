<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_login();

$me = current_user();
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf();
    $users = read_json('users', []);
    $index = -1;
    foreach ($users as $i => $user) {
        if (($user['id'] ?? '') === $me['id']) {
            $index = $i;
            break;
        }
    }
    $name = clean_text($_POST['name'] ?? '', 80);
    $current = (string) ($_POST['current_password'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    if (str_len($name) < 2) {
        $errors[] = 'الاسم قصير';
    }
    if ($index < 0) {
        $errors[] = 'الحساب غير موجود';
    } elseif ($password !== '') {
        if (!password_verify($current, (string) ($users[$index]['password'] ?? ''))) {
            $errors[] = 'كلمة المرور الحالية غير صحيحة';
        }
        if (strlen($password) < 6) {
            $errors[] = 'كلمة المرور الجديدة 6 أحرف على الأقل';
        }
        if ($password !== $confirm) {
            $errors[] = 'تأكيد كلمة المرور غير مطابق';
        }
    }
    if (!$errors) {
        $users[$index]['name'] = $name;
        if ($password !== '') {
            $users[$index]['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        if (!write_json('users', $users)) {
            $errors[] = 'تعذر الحفظ';
        } else {
            current_user(true);
            flash('تم تحديث الحساب');
            redirect('account.php');
        }
    }
}

render_header('حسابي', '');
?>
<div class="card" style="max-width:640px">
  <?php foreach ($errors as $error): ?>
    <div class="notice notice-low"><?php echo h($error); ?></div>
  <?php endforeach; ?>
  <form method="post">
    <?php echo csrf_field(); ?>
    <div class="field">
      <label for="name">الاسم الظاهر</label>
      <input class="input" id="name" name="name" value="<?php echo h($me['name'] ?? ''); ?>">
    </div>
    <p class="muted">اسم الدخول: <b class="ltr"><?php echo h($me['username'] ?? ''); ?></b></p>
    <div class="field">
      <label for="current_password">كلمة المرور الحالية</label>
      <input class="input ltr" id="current_password" name="current_password" type="password" autocomplete="current-password">
      <p class="hint">مطلوبة فقط عند تغيير كلمة المرور.</p>
    </div>
    <div class="grid-2">
      <div class="field">
        <label for="password">كلمة مرور جديدة</label>
        <input class="input ltr" id="password" name="password" type="password" autocomplete="new-password">
      </div>
      <div class="field">
        <label for="confirm_password">تأكيدها</label>
        <input class="input ltr" id="confirm_password" name="confirm_password" type="password" autocomplete="new-password">
      </div>
    </div>
    <button class="btn btn-gold" type="submit">حفظ</button>
  </form>
</div>
<?php
render_footer();
