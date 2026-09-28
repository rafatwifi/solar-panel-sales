<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/calc.php';
require_login();

$id = trim((string) ($_GET['id'] ?? ''));
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

$settings = app_settings();
$logo = upload_url($settings['logo'] ?? '');
$created = strtotime((string) ($order['created_at'] ?? ''));
$offerDate = $created ? date('Y/m/d', $created) : date('Y/m/d');
$code = (string) ($order['code'] ?? '');
$safeCode = preg_replace('/[^A-Za-z0-9\-]/', '', $code);
if ($safeCode === '') {
    $safeCode = 'offer';
}
$filename = 'عرض-منظومة-' . $safeCode . '.pdf';

$catalog = read_json('products', []);
$lines = [];
foreach (['panel', 'inverter', 'battery', 'combiner_ac', 'combiner_dc'] as $category) {
    foreach ($order['items'] ?? [] as $item) {
        if (($item['category'] ?? '') === $category) {
            $lines[] = $item;
        }
    }
}
$pricedLines = 0;
foreach ($lines as $item) {
    if (item_shows_price($item, $catalog)) {
        $pricedLines++;
    }
}
if (array_key_exists('grand_total', $order)) {
    $grandTotal = (int) $order['grand_total'];
} else {
    $grandTotal = (int) ($order['equipment_total'] ?? 0) + (int) ($order['installation_fee'] ?? 0);
}
$auto = (($_GET['download'] ?? '') === '1') ? '1' : '0';
$input = (isset($order['input']) && is_array($order['input'])) ? $order['input'] : [];
$dayAmpsText = amp_text($input['day_amps'] ?? 0);
$nightAmpsText = amp_text($input['night_amps'] ?? 0);
$nightHoursRaw = round((float) ($input['night_hours'] ?? 0), 2);
$nightHoursText = rtrim(rtrim(number_format($nightHoursRaw, 2, '.', ''), '0'), '.') . ' ساعة';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>عرض منظومة | <?php echo h($settings['company_name']); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      background: #e6dfd2;
      color: #1c2430;
      font-family: "Tajawal", Tahoma, "Segoe UI", sans-serif;
    }
    .bar {
      position: sticky;
      top: 0;
      z-index: 5;
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      align-items: center;
      gap: 8px;
      padding: 12px;
      background: #10243f;
      border-bottom: 3px solid #e39b16;
    }
    .bar a, .bar button {
      border: 0;
      border-radius: 999px;
      padding: 10px 18px;
      font: inherit;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
    }
    .bar a { background: transparent; color: #fff; margin-left: 8px; }
    .bar button { background: #e39b16; color: #10243f; }
    .bar button:disabled { opacity: .7; cursor: wait; }
    .stage { padding: 22px 12px 36px; overflow: auto; }
    .sheet-shadow {
      width: 794px;
      margin: 0 auto;
      box-shadow: 0 18px 48px rgba(16, 36, 63, .16);
    }
    .sheet {
      width: 794px;
      margin: 0 auto;
      background: #fff;
      color: #1c2430;
    }
    .letterhead {
      background: #10243f;
      background-image: linear-gradient(160deg, #1b3f6e 0%, #10243f 58%);
      color: #fff;
      padding: 26px 36px 20px;
      border-bottom: 6px solid #e39b16;
    }
    .head-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .brand { display: flex; align-items: center; flex: 1; min-width: 0; }
    .logo-well {
      width: 78px;
      height: 78px;
      flex: 0 0 78px;
      background: #fff;
      border: 3px solid #e39b16;
      border-radius: 18px;
      padding: 7px;
      margin-left: 14px;
      overflow: hidden;
    }
    .logo-well img,
    .logo-well svg {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: contain;
    }
    .brand-text { min-width: 0; }
    .brand-text strong {
      display: block;
      font-size: 22px;
      font-weight: 800;
      line-height: 1.35;
      color: #fff;
      overflow-wrap: break-word;
    }
    .phone {
      display: block;
      margin-top: 4px;
      color: rgba(255, 255, 255, .82);
      font-size: 14px;
      font-weight: 500;
    }
    .phone .ltr {
      color: #ffd56a;
      font-weight: 700;
      margin-right: 6px;
    }
    .doc { text-align: left; flex: 0 0 auto; max-width: 390px; margin-right: 16px; }
    .doc h1 {
      margin: 0;
      font-size: 36px;
      font-weight: 800;
      line-height: 1.1;
      color: #fff;
    }
    .chips { margin-top: 10px; }
    .chip {
      display: inline-block;
      background: #1a3358;
      border: 1px solid #e39b16;
      color: #fff;
      border-radius: 999px;
      padding: 3px 12px 4px;
      font-size: 13px;
      font-weight: 500;
      margin-top: 6px;
      margin-left: 6px;
    }
    .chip b {
      color: #ffd56a;
      font-weight: 700;
      margin-left: 6px;
    }
    .ltr { direction: ltr; unicode-bidi: isolate; }
    .sheet-body { padding: 22px 36px 18px; }
    .parties { display: flex; margin-bottom: 20px; }
    .party {
      flex: 1;
      background: #f7f4ec;
      border-radius: 14px;
      padding: 12px 14px;
      border-right: 4px solid #e39b16;
    }
    .party + .party { margin-right: 12px; }
    .party .k {
      display: block;
      color: #8a6a22;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 2px;
    }
    .party strong {
      display: block;
      color: #10243f;
      font-size: 18px;
      font-weight: 800;
      line-height: 1.35;
      overflow-wrap: break-word;
    }
    .party strong.ltr { text-align: right; }
    .facts { display: flex; margin-bottom: 20px; }
    .fact {
      flex: 1;
      min-width: 0;
      background: #10243f;
      color: #fff;
      border-radius: 14px;
      padding: 12px 10px;
      text-align: center;
      border-bottom: 4px solid #e39b16;
    }
    .fact + .fact { margin-right: 10px; }
    .fact .k {
      display: block;
      color: #ffd56a;
      font-size: 12px;
      font-weight: 700;
      line-height: 1.3;
    }
    .fact strong {
      display: block;
      margin-top: 4px;
      color: #fff;
      font-size: 20px;
      font-weight: 800;
      line-height: 1.3;
    }
    .sec {
      display: flex;
      align-items: center;
      margin: 0 0 10px;
    }
    .sec h2 {
      margin: 0;
      font-size: 16px;
      font-weight: 800;
      color: #10243f;
    }
    .dot {
      display: inline-block;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #e39b16;
      margin-left: 8px;
      vertical-align: middle;
    }
    .sec-line {
      flex: 1;
      height: 1px;
      background: #eadfce;
      margin-right: 12px;
    }
    .table-wrap { border: 1px solid #e4d8c4; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td {
      text-align: right;
      padding: 11px 12px;
      vertical-align: middle;
    }
    th {
      background: #10243f;
      color: #fff;
      font-size: 13px;
      font-weight: 700;
      border-bottom: 3px solid #e39b16;
    }
    td {
      border-bottom: 1px solid #f0e6d6;
      font-size: 14px;
    }
    tbody tr:last-child td { border-bottom: 0; }
    tbody tr:nth-child(even) td { background: #fbf8f3; }
    .w-cat { width: 148px; }
    .w-qty { width: 68px; }
    .w-price { width: 160px; }
    .col-qty, td.qty { text-align: center; }
    .cat {
      display: inline-block;
      border-radius: 999px;
      padding: 3px 8px;
      font-size: 12px;
      font-weight: 700;
      line-height: 1.5;
      white-space: nowrap;
      background: #f3ecdf;
      color: #10243f;
    }
    .cat-panel { background: #fff4dc; color: #8a5a00; }
    .cat-inverter { background: #e7eef8; color: #10243f; }
    .cat-battery { background: #e5f6ee; color: #146b42; }
    .cat-combiner_ac { background: #fff0e6; color: #9a3412; }
    .cat-combiner_dc { background: #e7f5fb; color: #0f5878; }
    .pname {
      font-weight: 800;
      color: #10243f;
      font-size: 15px;
      line-height: 1.4;
      overflow-wrap: break-word;
    }
    .pmeta {
      margin-top: 3px;
      color: #5c6570;
      font-size: 12.5px;
      line-height: 1.5;
    }
    .pmeta span + span::before {
      content: " · ";
      color: #e39b16;
    }
    .pspecs {
      margin-top: 4px;
      color: #3d4652;
      font-size: 12px;
      line-height: 1.45;
      overflow-wrap: break-word;
    }
    td.qty {
      font-weight: 800;
      color: #10243f;
      font-size: 16px;
      white-space: nowrap;
    }
    td.price {
      font-weight: 800;
      color: #10243f;
      white-space: nowrap;
      font-variant-numeric: tabular-nums;
    }
    .empty {
      margin: 0;
      padding: 22px 14px;
      text-align: center;
      color: #5c6570;
      background: #fbf8f3;
      border: 1px dashed #e4d3b0;
      border-radius: 12px;
    }
    .final {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 20px;
      padding: 14px 16px;
      background: #10243f;
      background-image: linear-gradient(135deg, #1b3f6e 0%, #10243f 70%);
      border: 2px solid #e39b16;
      border-radius: 16px;
    }
    .final-label {
      color: #ffd56a;
      font-size: 22px;
      font-weight: 800;
      line-height: 1.2;
    }
    .final-amount {
      background: #e39b16;
      color: #10243f;
      font-size: 28px;
      font-weight: 800;
      line-height: 1.2;
      border-radius: 12px;
      padding: 8px 16px;
      white-space: nowrap;
    }
    .foot {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      margin-top: 16px;
      padding-top: 12px;
      border-top: 1px solid #eadfce;
      color: #5c6570;
      font-size: 12.5px;
    }
    .foot strong { color: #10243f; font-weight: 800; }
    .sep { color: #e39b16; font-weight: 700; margin: 0 4px; }
    .sheet-end { height: 8px; background: #e39b16; }
    @media print {
      * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      body { background: #fff; }
      .bar { display: none; }
      .stage { padding: 0; }
      .sheet-shadow { box-shadow: none; width: auto; max-width: none; }
      .sheet { box-shadow: none; width: auto; max-width: none; }
    }
  </style>
</head>
<body data-filename="<?php echo h($filename); ?>" data-download="<?php echo h($auto); ?>">
  <div class="bar">
    <a href="order.php?id=<?php echo h($order['id']); ?>">رجوع للطلب</a>
    <button type="button" id="pdfBtn">تحميل ملف PDF</button>
  </div>
  <div class="stage">
    <div class="sheet-shadow">
      <article id="sheet" class="sheet">
        <header class="letterhead">
          <div class="head-row">
            <div class="brand">
              <div class="logo-well">
                <?php if ($logo): ?>
                  <img src="<?php echo h($logo); ?>" alt="">
                <?php else: ?>
                  <svg viewBox="0 0 64 64" aria-hidden="true">
                    <circle cx="32" cy="32" r="11" fill="#e39b16"/>
                    <g fill="none" stroke="#e39b16" stroke-width="3" stroke-linecap="round">
                      <path d="M32 6v8M32 50v8M6 32h8M50 32h8"/>
                      <path d="M13.5 13.5l5.7 5.7M44.8 44.8l5.7 5.7M13.5 50.5l5.7-5.7M44.8 19.2l5.7-5.7"/>
                    </g>
                  </svg>
                <?php endif; ?>
              </div>
              <div class="brand-text">
                <strong><?php echo h($settings['company_name']); ?></strong>
                <?php if (!empty($settings['company_phone'])): ?>
                  <span class="phone">هاتف<span class="ltr"><?php echo h($settings['company_phone']); ?></span></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="doc">
              <h1>عرض منظومة</h1>
              <div class="chips">
                <span class="chip"><b>التاريخ</b><?php echo h($offerDate); ?></span>
                <?php if ($code !== ''): ?><span class="chip"><b>رقم العرض</b><?php echo h($code); ?></span><?php endif; ?>
              </div>
            </div>
          </div>
        </header>

        <div class="sheet-body">
          <section class="parties">
            <div class="party">
              <span class="k">الزبون</span>
              <strong><?php echo h($order['customer_name'] ?? ''); ?></strong>
            </div>
            <div class="party">
              <span class="k">الهاتف</span>
              <strong class="ltr"><?php echo h($order['customer_phone'] ?? ''); ?></strong>
            </div>
          </section>

          <section class="facts">
            <div class="fact">
              <span class="k">أمبير نهاري</span>
              <strong><?php echo h($dayAmpsText); ?></strong>
            </div>
            <div class="fact">
              <span class="k">أمبير ليلي</span>
              <strong><?php echo h($nightAmpsText); ?></strong>
            </div>
            <div class="fact">
              <span class="k">ساعات التشغيل الليلي</span>
              <strong><?php echo h($nightHoursText); ?></strong>
            </div>
          </section>

          <div class="sec">
            <h2><span class="dot" aria-hidden="true"></span>المواد</h2>
            <span class="sec-line"></span>
          </div>

          <?php if (!$lines): ?>
            <p class="empty">لا توجد مواد في هذا العرض.</p>
          <?php else: ?>
            <div class="table-wrap">
              <table>
                <colgroup>
                  <col class="w-cat">
                  <col>
                  <col class="w-qty">
                  <?php if ($pricedLines > 0): ?><col class="w-price"><?php endif; ?>
                </colgroup>
                <thead>
                  <tr>
                    <th>النوع</th>
                    <th>المادة</th>
                    <th class="col-qty">العدد</th>
                    <?php if ($pricedLines > 0): ?><th>السعر</th><?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($lines as $item): ?>
                    <?php
                      $detail = product_size_text($item);
                      $meta = [];
                      if (!empty($item['brand'])) {
                          $meta[] = (string) $item['brand'];
                      }
                      if ($detail !== '') {
                          $meta[] = $detail;
                      }
                      $cat = (string) ($item['category'] ?? '');
                      $catClass = 'cat';
                      if ($cat === 'panel' || $cat === 'inverter' || $cat === 'battery' || $cat === 'combiner_ac' || $cat === 'combiner_dc') {
                          $catClass .= ' cat-' . $cat;
                      }
                    ?>
                    <tr>
                      <td><span class="<?php echo h($catClass); ?>"><?php echo h(category_label($cat)); ?></span></td>
                      <td>
                        <div class="pname"><?php echo h($item['name'] ?? ''); ?></div>
                        <?php if ($meta): ?>
                          <div class="pmeta">
                            <?php foreach ($meta as $bit): ?>
                              <span><?php echo h($bit); ?></span>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                        <?php if (!empty($item['specs'])): ?>
                          <div class="pspecs"><?php echo nl2br(h($item['specs'])); ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="qty"><?php echo (int) ($item['qty'] ?? 0); ?></td>
                      <?php if ($pricedLines > 0): ?>
                        <td class="price"><?php if (item_shows_price($item, $catalog)) { echo h(money($item['line_total'] ?? 0)); } ?></td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <section class="final">
            <div class="final-label">السعر النهائي</div>
            <div class="final-amount"><?php echo h(money($grandTotal)); ?></div>
          </section>

          <footer class="foot">
            <span>
              <strong><?php echo h($settings['company_name']); ?></strong>
              <?php if (!empty($settings['company_phone'])): ?>
                <span class="sep">·</span>
                <span class="ltr"><?php echo h($settings['company_phone']); ?></span>
              <?php endif; ?>
            </span>
            <span>عرض منظومة<span class="sep">·</span><?php echo h($offerDate); ?></span>
          </footer>
        </div>
        <div class="sheet-end"></div>
      </article>
    </div>
  </div>
  <script src="assets/vendor/html2canvas.min.js"></script>
  <script src="assets/vendor/jspdf.umd.min.js"></script>
  <script>
  (function () {
    var btn = document.getElementById('pdfBtn');
    function savePdf() {
      var sheet = document.getElementById('sheet');
      if (!sheet || !window.html2canvas || !window.jspdf) {
        window.print();
        return;
      }
      btn.disabled = true;
      btn.textContent = 'جاري إنشاء الملف...';
      var prevWidth = sheet.style.width;
      var prevMax = sheet.style.maxWidth;
      sheet.style.width = '794px';
      sheet.style.maxWidth = 'none';
      var ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
      ready.then(function () {
        return html2canvas(sheet, {
          scale: 2,
          backgroundColor: '#ffffff',
          useCORS: true,
          width: 794,
          windowWidth: 794
        });
      }).then(function (canvas) {
        sheet.style.width = prevWidth;
        sheet.style.maxWidth = prevMax;
        var Doc = window.jspdf.jsPDF;
        var pdf = new Doc({ orientation: 'p', unit: 'mm', format: 'a4' });
        var pageWidth = pdf.internal.pageSize.getWidth();
        var pageHeight = pdf.internal.pageSize.getHeight();
        var imgWidth = pageWidth;
        var imgHeight = canvas.height * imgWidth / canvas.width;
        var img = canvas.toDataURL('image/jpeg', 0.95);
        var heightLeft = imgHeight;
        var position = 0;
        pdf.addImage(img, 'JPEG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
        while (heightLeft > 2) {
          position = heightLeft - imgHeight;
          pdf.addPage();
          pdf.addImage(img, 'JPEG', 0, position, imgWidth, imgHeight);
          heightLeft -= pageHeight;
        }
        pdf.save(document.body.getAttribute('data-filename') || 'عرض-منظومة.pdf');
        btn.disabled = false;
        btn.textContent = 'تحميل ملف PDF';
      }).catch(function () {
        sheet.style.width = '';
        sheet.style.maxWidth = '';
        btn.disabled = false;
        btn.textContent = 'تحميل ملف PDF';
        window.print();
      });
    }
    btn.addEventListener('click', savePdf);
    if (document.body.getAttribute('data-download') === '1') {
      window.addEventListener('load', function () {
        setTimeout(savePdf, 400);
      });
    }
  })();
  </script>
</body>
</html>
