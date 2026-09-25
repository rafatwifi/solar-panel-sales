<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_admin();

$users = read_json('users', []);
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf();
    $do = (string) ($_POST['do'] ?? '');
    $id = (string) ($_POST['id'] ?? '');
    $index = -1;
    foreach ($users as $i => $user) {
        if (($user['id'] ?? '') === $id) {
            $index = $i;
            break;
        }
    }

    if ($do === 'create') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $name = clean_text($_POST['name'] ?? '', 80);
        $password = (string) ($_POST['password'] ?? '');
        $role = (($_POST['role'] ?? '') === 'admin') ? 'admin' : 'employee';
        if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $username)) {
            $errors[] = 'اسم المستخدم بالإنجليزية ومن 3 إلى 30 حرفاً';
        }
        if (str_len($name) < 2) {
            $errors[] = 'اكتب اسم الموظف';
        }
        if (strlen($password) < 6) {
            $errors[] = 'كلمة المرور 6 أحرف على الأقل';
        }
        foreach ($users as $user) {
            if (strcasecmp((string) ($user['username'] ?? ''), $username) === 0) {
                $errors[] = 'اسم المستخدم مستعمل';
                break;
            }
        }
        if (!$errors) {
            $users[] = [
                'id' => uid('u'),
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name,
                'role' => $role,
                'active' => true,
                'created_at' => now_iso(),
            ];
            if (!write_json('users', $users)) {
                $errors[] = 'تعذر الحفظ';
            } else {
                flash('تمت إضافة المستخدم');
                redirect('users.php');
            }
        }
    } elseif ($index < 0) {
        $errors[] = 'المستخدم غير موجود';
    } elseif ($do === 'password') {
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 6) {
            $errors[] = 'كلمة المرور 6 أحرف على الأقل';
        } else {
            $users[$index]['password'] = password_hash($password, PASSWORD_DEFAULT);
            write_json('users', $users);
            flash('تم تغيير كلمة المرور');
            redirect('users.php');
        }
    } elseif ($do === 'toggle') {
        $willActive = empty($users[$index]['active']);
        if (!$willActive && ($users[$index]['role'] ?? '') === 'admin' && count_active_admins($users) <= 1) {
            $errors[] = 'لا يمكن إيقاف آخر مدير';
        } else {
            $users[$index]['active'] = $willActive;
            write_json('users', $users);
            flash($willActive ? 'تم تفعيل الحساب' : 'تم إيقاف الحساب');
            redirect('users.php');
        }
    } elseif ($do === 'delete') {
        if (($users[$index]['role'] ?? '') === 'admin') {
            $errors[] = 'لا يُحذف حساب المدير، يمكن إيقافه';
        } elseif (($users[$index]['id'] ?? '') === (current_user()['id'] ?? '')) {
            $errors[] = 'لا تحذف حسابك الحالي';
        } else {
            array_splice($users, $index, 1);
            write_json('users', array_values($users));
            flash('تم حذف الموظف');
            redirect('users.php');
        }
    }
}

render_header('الموظفون', 'users.php');
?>
<?php foreach ($errors as $error): ?>
  <div class="notice notice-low"><?php echo h($error); ?></div>
<?php endforeach; ?>
<div class="card" style="max-width:720px">
  <h2>مستخدم جديد</h2>
  <form method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="create">
    <div class="grid-2">
      <div class="field">
        <label for="name">الاسم</label>
        <input class="input" id="name" name="name" required>
      </div>
      <div class="field">
        <label for="username">اسم الدخول</label>
        <input class="input ltr" id="username" name="username" required>
      </div>
      <div class="field">
        <label for="password">كلمة المرور</label>
        <input class="input ltr" id="password" name="password" type="text" required>
      </div>
      <div class="field">
        <label for="role">الصلاحية</label>
        <select class="select" id="role" name="role">
          <option value="employee">موظف</option>
          <option value="admin">إدارة</option>
        </select>
      </div>
    </div>
    <button class="btn btn-gold" type="submit">إضافة</button>
  </form>
</div>

<div class="orders">
  <?php foreach ($users as $user): ?>
    <div class="card">
      <div class="sheet-top">
        <div>
          <strong><?php echo h($user['name'] ?? ''); ?></strong>
          <div class="muted ltr"><?php echo h($user['username'] ?? ''); ?></div>
        </div>
        <span class="status <?php echo !empty($user['active']) ? 'status-approved' : 'status-rejected'; ?>">
          <?php echo ($user['role'] ?? '') === 'admin' ? 'إدارة' : 'موظف'; ?>
          · <?php echo !empty($user['active']) ? 'نشط' : 'موقف'; ?>
        </span>
      </div>
      <form method="post" class="row-actions" style="margin-top:10px">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo h($user['id']); ?>">
        <input class="input ltr" name="password" placeholder="كلمة مرور جديدة" style="max-width:220px">
        <button class="btn btn-sm" name="do" value="password" type="submit">تغيير الكلمة</button>
        <button class="btn btn-sm" name="do" value="toggle" type="submit"><?php echo !empty($user['active']) ? 'إيقاف' : 'تفعيل'; ?></button>
        <?php if (($user['role'] ?? '') !== 'admin'): ?>
          <button class="btn btn-danger btn-sm" name="do" value="delete" type="submit" data-confirm="حذف هذا الموظف؟">حذف</button>
        <?php endif; ?>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php
render_footer();
