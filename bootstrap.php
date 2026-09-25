<?php

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

date_default_timezone_set('Asia/Baghdad');

define('ROOT', __DIR__);
define('DATA_DIR', ROOT . '/data');
define('UPLOAD_DIR', ROOT . '/uploads');

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0775, true);
}
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0775, true);
}

$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
error_reporting(E_ALL);
ini_set('display_errors', $isLocal ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', DATA_DIR . '/php-error.log');
ini_set('default_charset', 'UTF-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('SOLARSESSID');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $https,
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $https, true);
    }
    session_start();
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, no-cache, must-revalidate');

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function str_len($value)
{
    $value = (string) $value;
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    return strlen($value);
}

function clean_text($value, $max)
{
    $value = strip_tags((string) $value);
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = trim($value);
    if (str_len($value) > $max) {
        if (function_exists('mb_substr')) {
            $value = mb_substr($value, 0, $max, 'UTF-8');
        } else {
            $value = substr($value, 0, $max);
        }
    }
    return $value;
}

function num($value)
{
    $s = trim((string) $value);
    $s = str_replace([' ', '٬'], '', $s);
    $s = str_replace(['،', ','], '.', $s);
    if ($s === '' || !is_numeric($s)) {
        return 0.0;
    }
    return (float) $s;
}

function uid($prefix)
{
    return $prefix . '_' . bin2hex(random_bytes(4));
}

function now_iso()
{
    return date('c');
}

function redirect($path)
{
    header('Location: ' . $path);
    exit;
}

function json_path($name)
{
    if (!preg_match('/^(users|settings|products|orders)$/', $name)) {
        throw new RuntimeException('invalid store');
    }
    return DATA_DIR . '/' . $name . '.json';
}

function read_json($name, $fallback)
{
    $path = json_path($name);
    if (!is_file($path)) {
        return $fallback;
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $fallback;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $fallback;
}

function write_json($name, $data)
{
    $path = json_path($name);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    $fh = fopen($path, 'c+');
    if (!$fh) {
        return false;
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        return false;
    }
    ftruncate($fh, 0);
    rewind($fh);
    $ok = fwrite($fh, $json . "\n") !== false;
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $ok;
}

function default_settings()
{
    return [
        'company_name' => 'مركز الطاقة الشمسية',
        'company_phone' => '',
        'currency' => 'د.ع',
        'logo' => '',
        'ac_voltage' => 220,
        'day_hours' => 6,
        'night_hours' => 8,
        'day_panel_factor' => 1.75,
        'peak_sun_hours' => 5,
        'pv_efficiency_percent' => 75,
        'inverter_margin_percent' => 25,
        'battery_dod_percent' => 80,
        'battery_reserve_percent' => 20,
        'battery_efficiency_percent' => 90,
        'cost_panel_install' => 0,
        'cost_other' => 0,
    ];
}

function seed_products()
{
    $now = now_iso();
    $rows = [
        ['p_550', 'لوح أحادي 550 واط', 'panel', 'نصف خلية', 550, 0, 95000, 'لوح مونو نصف خلية، مناسب للسطح والأرضي.'],
        ['p_585', 'لوح أحادي 585 واط', 'panel', 'نصف خلية', 585, 0, 110000, 'قدرة أعلى لنفس المساحة تقريباً.'],
        ['p_615', 'لوح أحادي 615 واط', 'panel', 'N-Type', 615, 0, 125000, 'كفاءة مرتفعة للأسطح المحدودة.'],
        ['i_5', 'إنفرتر 5 كيلو واط', 'inverter', 'هايبرد', 5000, 0, 450000, 'إنفرتر هجين 5 كيلو واط، موجة نقية.'],
        ['i_6', 'إنفرتر 6 كيلو واط', 'inverter', 'هايبرد', 6000, 0, 550000, 'إنفرتر هجين 6 كيلو واط مع شاحن شمسي.'],
        ['i_10', 'إنفرتر 10 كيلو واط', 'inverter', 'هايبرد', 10000, 0, 850000, 'للأحمال الكبيرة والمنازل الواسعة.'],
        ['b_5', 'بطارية ليثيوم 5 كيلو واط ساعة', 'battery', 'LiFePO4', 0, 5, 700000, 'سعة 5 كيلو واط ساعة، تفريغ عميق.'],
        ['b_10', 'بطارية ليثيوم 10 كيلو واط ساعة', 'battery', 'LiFePO4', 0, 10, 1250000, 'سعة 10 كيلو واط ساعة للاستخدام الليلي.'],
        ['b_15', 'بطارية ليثيوم 15 كيلو واط ساعة', 'battery', 'LiFePO4', 0, 15, 1800000, 'سعة 15 كيلو واط ساعة للأحمال الليلية العالية.'],
    ];
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => $row[0],
            'name' => $row[1],
            'category' => $row[2],
            'brand' => $row[3],
            'watts' => $row[4],
            'kwh' => $row[5],
            'voltage' => 0,
            'price' => $row[6],
            'show_price' => false,
            'specs' => $row[7],
            'image' => '',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
    foreach (combiner_catalog() as $row) {
        $out[] = combiner_product_row($row, $now);
    }
    return $out;
}

function combiner_catalog()
{
    return [
        ['c_ac_20', 'كومباينر بوكس AC 10-20 أمبير', 'combiner_ac', 10, 20, 85000, 'كومباينر تيار متردد للمنظومات من 10 إلى 20 أمبير.'],
        ['c_ac_30', 'كومباينر بوكس AC 20-30 أمبير', 'combiner_ac', 20, 30, 110000, 'كومباينر تيار متردد للمنظومات من 20 إلى 30 أمبير.'],
        ['c_ac_40', 'كومباينر بوكس AC 30-40 أمبير', 'combiner_ac', 30, 40, 140000, 'كومباينر تيار متردد للمنظومات من 30 إلى 40 أمبير.'],
        ['c_dc_20', 'كومباينر بوكس DC 10-20 أمبير', 'combiner_dc', 10, 20, 75000, 'كومباينر تيار مستمر للمنظومات من 10 إلى 20 أمبير.'],
        ['c_dc_30', 'كومباينر بوكس DC 20-30 أمبير', 'combiner_dc', 20, 30, 100000, 'كومباينر تيار مستمر للمنظومات من 20 إلى 30 أمبير.'],
        ['c_dc_40', 'كومباينر بوكس DC 30-40 أمبير', 'combiner_dc', 30, 40, 130000, 'كومباينر تيار مستمر للمنظومات من 30 إلى 40 أمبير.'],
    ];
}

function combiner_product_row($row, $now)
{
    return [
        'id' => $row[0],
        'name' => $row[1],
        'category' => $row[2],
        'brand' => '',
        'watts' => 0,
        'kwh' => 0,
        'amp_min' => $row[3],
        'amp_max' => $row[4],
        'voltage' => 0,
        'price' => $row[5],
        'show_price' => false,
        'specs' => $row[6],
        'image' => '',
        'active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ];
}

function merge_missing_combiners($products)
{
    if (!is_array($products)) {
        $products = [];
    }
    $ids = [];
    foreach ($products as $product) {
        if (is_array($product)) {
            $ids[(string) ($product['id'] ?? '')] = true;
        }
    }
    $now = now_iso();
    $changed = false;
    foreach (combiner_catalog() as $row) {
        if (!empty($ids[$row[0]])) {
            continue;
        }
        $products[] = combiner_product_row($row, $now);
        $ids[$row[0]] = true;
        $changed = true;
    }
    return ['products' => array_values($products), 'changed' => $changed];
}

function ensure_storage()
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0775, true);
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    if (!is_file(json_path('users'))) {
        write_json('users', [[
            'id' => 'u_admin',
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'name' => 'المدير',
            'role' => 'admin',
            'active' => true,
            'created_at' => now_iso(),
        ]]);
    }
    if (!is_file(json_path('settings'))) {
        write_json('settings', default_settings());
    }
    if (!is_file(json_path('products'))) {
        write_json('products', seed_products());
    } else {
        $existingProducts = read_json('products', null);
        if (is_array($existingProducts)) {
            $mergedProducts = merge_missing_combiners($existingProducts);
            if (!empty($mergedProducts['changed'])) {
                write_json('products', $mergedProducts['products']);
            }
        }
    }
    if (!is_file(json_path('orders'))) {
        write_json('orders', []);
    }
}

function app_settings($refresh = false)
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    $saved = read_json('settings', []);
    $cache = array_merge(default_settings(), $saved);
    if (!array_key_exists('battery_reserve_percent', $saved)) {
        $cache['battery_reserve_percent'] = max(0, min(99, 100 - (float) $cache['battery_dod_percent']));
    }
    return $cache;
}

function storage_writable()
{
    return is_dir(DATA_DIR) && is_writable(DATA_DIR) && is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR);
}

function current_user($refresh = false)
{
    static $loaded = false;
    static $user = null;
    if ($loaded && !$refresh) {
        return $user;
    }
    $loaded = true;
    $user = null;
    $uid = $_SESSION['uid'] ?? '';
    if ($uid === '') {
        return null;
    }
    foreach (read_json('users', []) as $row) {
        if (($row['id'] ?? '') === $uid && !empty($row['active'])) {
            unset($row['password']);
            $user = $row;
            break;
        }
    }
    return $user;
}

function is_admin()
{
    $user = current_user();
    return $user && ($user['role'] ?? '') === 'admin';
}

function require_login()
{
    if (!current_user()) {
        redirect('index.php');
    }
}

function require_admin()
{
    require_login();
    if (!is_admin()) {
        flash('هذه الصفحة خاصة بالإدارة', 'bad');
        redirect('dashboard.php');
    }
}

function attempt_login($username, $password)
{
    $username = trim((string) $username);
    foreach (read_json('users', []) as $user) {
        if (strcasecmp((string) ($user['username'] ?? ''), $username) !== 0) {
            continue;
        }
        if (empty($user['active'])) {
            return 'inactive';
        }
        if (password_verify($password, (string) ($user['password'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['uid'] = $user['id'];
            $_SESSION['login_fails'] = 0;
            return 'ok';
        }
        return 'bad';
    }
    return 'bad';
}

function default_password_active()
{
    foreach (read_json('users', []) as $user) {
        if (($user['username'] ?? '') === 'admin' && password_verify('admin123', (string) ($user['password'] ?? ''))) {
            return true;
        }
    }
    return false;
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function check_csrf()
{
    $token = $_POST['csrf'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    if (!$known || !is_string($token) || !hash_equals($known, $token)) {
        http_response_code(403);
        exit('انتهت صلاحية النموذج. ارجع للصفحة وأعد المحاولة.');
    }
}

function flash($message, $type = 'ok')
{
    $_SESSION['flash'] = ['msg' => $message, 'type' => $type];
}

function take_flash()
{
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function upload_url($rel)
{
    if (!is_string($rel) || !preg_match('#^uploads/[A-Za-z0-9_\-]+\.(jpg|jpeg|png|webp|gif)$#', $rel)) {
        return '';
    }
    return $rel;
}

function delete_upload($rel)
{
    $rel = upload_url($rel);
    if ($rel === '') {
        return;
    }
    $full = ROOT . '/' . $rel;
    if (is_file($full)) {
        unlink($full);
    }
}

function store_image($field)
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return ['ok' => true, 'path' => null];
    }
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') {
        return ['ok' => true, 'path' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'تعذر رفع الصورة'];
    }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'حجم الصورة أكبر من 2 ميغا'];
    }
    if (!function_exists('getimagesize')) {
        return ['ok' => false, 'error' => 'تعذر فحص الصورة على هذا الخادم'];
    }
    $info = @getimagesize($file['tmp_name']);
    $map = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
    ];
    if (defined('IMAGETYPE_WEBP')) {
        $map[IMAGETYPE_WEBP] = 'webp';
    }
    if (!$info || !isset($map[$info[2]])) {
        return ['ok' => false, 'error' => 'استخدم صورة JPG أو PNG أو WEBP'];
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    $name = 'img_' . bin2hex(random_bytes(8)) . '.' . $map[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        return ['ok' => false, 'error' => 'تعذر حفظ الصورة. امنح مجلد uploads صلاحية الكتابة'];
    }
    return ['ok' => true, 'path' => 'uploads/' . $name];
}

function shows_price($row)
{
    return !empty($row['show_price']);
}

function item_shows_price($item, $products)
{
    if (!is_array($item)) {
        return false;
    }
    $id = (string) ($item['product_id'] ?? '');
    if ($id !== '') {
        foreach ($products as $product) {
            if (($product['id'] ?? '') === $id) {
                return shows_price($product);
            }
        }
    }
    return shows_price($item);
}

function money($amount)
{
    $settings = app_settings();
    $currency = trim((string) ($settings['currency'] ?? ''));
    if ($currency === '') {
        $currency = 'د.ع';
    }
    return number_format((float) $amount, 0, '.', ',') . ' ' . $currency;
}

function watts_text($watts)
{
    $watts = (float) $watts;
    if (abs($watts) >= 1000) {
        $kw = round($watts / 1000, 2);
        $text = rtrim(rtrim(number_format($kw, 2, '.', ''), '0'), '.');
        return $text . ' كيلو واط';
    }
    return number_format(round($watts), 0, '.', ',') . ' واط';
}

function kwh_text($kwh)
{
    $kwh = round((float) $kwh, 2);
    $text = rtrim(rtrim(number_format($kwh, 2, '.', ''), '0'), '.');
    return $text . ' كيلو واط ساعة';
}

function amp_text($amps)
{
    $amps = round((float) $amps, 2);
    $text = rtrim(rtrim(number_format($amps, 2, '.', ''), '0'), '.');
    return $text . ' أمبير';
}

function amp_range_text($min, $max)
{
    $min = round((float) $min, 2);
    $max = round((float) $max, 2);
    $minText = rtrim(rtrim(number_format($min, 2, '.', ''), '0'), '.');
    $maxText = rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.');
    return $minText . '-' . $maxText . ' أمبير';
}

function is_combiner_category($category)
{
    return $category === 'combiner_ac' || $category === 'combiner_dc';
}

function product_size_text($row)
{
    if (!is_array($row)) {
        return '';
    }
    $category = (string) ($row['category'] ?? '');
    if ($category === 'battery') {
        $kwh = $row['kwh'] ?? ($row['unit_kwh'] ?? 0);
        return kwh_text($kwh);
    }
    if (is_combiner_category($category)) {
        return amp_range_text($row['amp_min'] ?? 0, $row['amp_max'] ?? 0);
    }
    if ($category === 'accessory') {
        return '';
    }
    $watts = (float) ($row['watts'] ?? ($row['unit_watts'] ?? 0));
    if ($watts > 0) {
        return watts_text($watts);
    }
    return '';
}

function order_materials_text($order, $includeAccessories = true)
{
    if (!is_array($order)) {
        return '';
    }
    $parts = [];
    foreach ($order['items'] ?? [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (!$includeAccessories && ($item['category'] ?? '') === 'accessory') {
            continue;
        }
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $bit = $name;
        $measure = product_size_text($item);
        if ($measure !== '') {
            $bit .= ' · ' . $measure;
        }
        $bit .= ' · ' . (int) ($item['qty'] ?? 0);
        $parts[] = $bit;
    }
    return implode(' | ', $parts);
}

function dt($iso)
{
    if (!$iso) {
        return '';
    }
    $time = strtotime((string) $iso);
    if (!$time) {
        return '';
    }
    return date('Y/m/d H:i', $time);
}

function category_label($category)
{
    $map = [
        'panel' => 'ألواح شمسية',
        'inverter' => 'إنفرتر',
        'battery' => 'بطاريات',
        'combiner_ac' => 'كومباينر AC',
        'combiner_dc' => 'كومباينر DC',
        'accessory' => 'ملحقات',
    ];
    return $map[$category] ?? $category;
}

function status_label($status)
{
    $map = [
        'draft' => 'مسودة',
        'sent' => 'وصل للمبيعات',
        'reviewing' => 'قيد المتابعة',
        'approved' => 'تمت الموافقة',
        'done' => 'تم التنفيذ',
        'rejected' => 'ملغى',
    ];
    return $map[$status] ?? $status;
}

function excerpt($text, $limit = 90)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
    if (str_len($text) <= $limit) {
        return $text;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $limit, 'UTF-8') . '…';
    }
    return substr($text, 0, $limit) . '…';
}

function first_char($text)
{
    $text = trim((string) $text);
    if ($text === '') {
        return 'م';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, 1, 'UTF-8');
    }
    return substr($text, 0, 1);
}

function json_for_script($data)
{
    return json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
}

function whatsapp_link($phone)
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if ($digits === '') {
        return '';
    }
    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }
    if (strpos($digits, '0') === 0) {
        $digits = '964' . substr($digits, 1);
    }
    return 'https://wa.me/' . $digits;
}

function count_active_admins($users)
{
    $count = 0;
    foreach ($users as $user) {
        if (($user['role'] ?? '') === 'admin' && !empty($user['active'])) {
            $count++;
        }
    }
    return $count;
}

ensure_storage();
