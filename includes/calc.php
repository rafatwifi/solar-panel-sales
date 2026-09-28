<?php

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function product_by_id($products, $id)
{
    foreach ($products as $product) {
        if ((string) ($product['id'] ?? '') === (string) $id) {
            return $product;
        }
    }
    return null;
}

function products_by_cat($products, $category)
{
    $out = [];
    foreach ($products as $product) {
        if (!empty($product['active']) && ($product['category'] ?? '') === $category) {
            $out[] = $product;
        }
    }
    usort($out, function ($a, $b) use ($category) {
        if ($category === 'battery') {
            return ((float) $a['kwh']) <=> ((float) $b['kwh']);
        }
        if ($category === 'panel' || $category === 'inverter') {
            return ((float) $a['watts']) <=> ((float) $b['watts']);
        }
        if ($category === 'combiner_ac' || $category === 'combiner_dc') {
            $byMax = ((float) ($a['amp_max'] ?? 0)) <=> ((float) ($b['amp_max'] ?? 0));
            if ($byMax !== 0) {
                return $byMax;
            }
            return ((float) ($a['amp_min'] ?? 0)) <=> ((float) ($b['amp_min'] ?? 0));
        }
        return strcmp((string) $a['name'], (string) $b['name']);
    });
    return $out;
}

function calculate_design($input, $settings)
{
    $voltage = max(1, (float) $settings['ac_voltage']);
    $dayWatts = (float) $input['day_amps'] * $voltage;
    $nightWatts = (float) $input['night_amps'] * $voltage;
    $dayHours = (float) $input['day_hours'];
    $nightHours = (float) $input['night_hours'];
    $dailyWh = ($dayWatts * $dayHours) + ($nightWatts * $nightHours);
    $sunHours = max(0.1, (float) $settings['peak_sun_hours']);
    $efficiency = max(0.01, min(1, ((float) $settings['pv_efficiency_percent']) / 100));
    $margin = 1 + (max(0, (float) $settings['inverter_margin_percent']) / 100);
    $reserve = max(0, min(99, (float) ($settings['battery_reserve_percent'] ?? (100 - (float) $settings['battery_dod_percent']))));
    $dod = max(0.01, (100 - $reserve) / 100);
    $batteryEfficiency = max(0.01, min(1, ((float) $settings['battery_efficiency_percent']) / 100));
    $pvWatts = $dailyWh / ($sunHours * $efficiency);
    $inverterWatts = max($dayWatts, $nightWatts) * $margin;
    $batteryKwh = (($nightWatts * $nightHours) / ($dod * $batteryEfficiency)) / 1000;

    return [
        'day_watts' => $dayWatts,
        'night_watts' => $nightWatts,
        'daily_wh' => $dailyWh,
        'daily_kwh' => $dailyWh / 1000,
        'panel_count' => 0,
        'pv_watts' => $pvWatts,
        'inverter_watts' => $inverterWatts,
        'battery_kwh' => $batteryKwh,
        'voltage' => $voltage,
    ];
}

function fit_status($selected, $required)
{
    $selected = (float) $selected;
    $required = (float) $required;
    if ($required <= 0.0001) {
        return $selected > 0.0001 ? 'high' : 'ok';
    }
    $ratio = $selected / $required;
    if ($ratio < 0.99) {
        return 'low';
    }
    if ($ratio > 1.01) {
        return 'high';
    }
    return 'ok';
}

function fit_text($noun, $status, $selectedText, $requiredText)
{
    if ($status === 'low') {
        return $noun . ' أقل من المطلوب: المختار ' . $selectedText . ' والمطلوب ' . $requiredText;
    }
    if ($status === 'high') {
        return $noun . ' أعلى من المطلوب: المختار ' . $selectedText . ' والمطلوب ' . $requiredText;
    }
    return $noun . ' مطابق للمطلوب: ' . $selectedText;
}

function system_amps($dayAmps, $nightAmps)
{
    return max((float) $dayAmps, (float) $nightAmps);
}

function recommended_board_range($amps)
{
    $amps = (float) $amps;
    if ($amps <= 0) {
        return null;
    }
    if ($amps <= 20) {
        return ['min' => 10, 'max' => 20];
    }
    if ($amps <= 30) {
        return ['min' => 20, 'max' => 30];
    }
    return ['min' => 30, 'max' => 40];
}

function board_fit_status($item, $amps)
{
    $amps = (float) $amps;
    $chosenMax = is_array($item) ? (float) ($item['amp_max'] ?? 0) : 0;
    $chosenMin = is_array($item) ? (float) ($item['amp_min'] ?? 0) : 0;
    if ($amps <= 0) {
        return $chosenMax > 0 ? 'high' : 'ok';
    }
    if ($chosenMax <= 0) {
        return 'low';
    }
    $range = recommended_board_range($amps);
    $reqMin = (float) $range['min'];
    $reqMax = (float) $range['max'];
    $sameRange = abs($chosenMin - $reqMin) < 0.001 && abs($chosenMax - $reqMax) < 0.001;
    if ($sameRange) {
        if ($amps > $chosenMax + 0.001) {
            return 'low';
        }
        return 'ok';
    }
    if ($chosenMax < $reqMax - 0.001) {
        return 'low';
    }
    return 'high';
}

function board_required_text($amps)
{
    $amps = (float) $amps;
    if ($amps <= 0) {
        return '0 أمبير';
    }
    if ($amps > 40) {
        return 'أكثر من 40 أمبير';
    }
    $range = recommended_board_range($amps);
    return amp_range_text($range['min'], $range['max']);
}

function board_notice($label, $item, $amps, $status = null)
{
    $amps = (float) $amps;
    if ($status === null || $status === '') {
        $status = board_fit_status($item, $amps);
    }
    if ($amps <= 0 && !$item) {
        return ['status' => 'ok', 'text' => 'لا حاجة ل' . $label . ' لأن الأمبير صفر'];
    }
    if (!$item) {
        return ['status' => 'low', 'text' => 'اختر ' . $label];
    }
    $selectedText = amp_range_text($item['amp_min'] ?? 0, $item['amp_max'] ?? 0);
    return [
        'status' => $status,
        'text' => fit_text($label, $status, $selectedText, board_required_text($amps)),
    ];
}

function hidden_cost_rows($settings, $panelQty)
{
    $panelQty = max(0, (int) $panelQty);
    $perPanel = max(0, (int) round((float) ($settings['cost_panel_install'] ?? 0)));
    return [
        [
            'key' => 'panel_install',
            'name' => 'أجور تركيب الألواح',
            'qty' => $panelQty,
            'unit' => $perPanel,
            'amount' => $perPanel * $panelQty,
        ],
        [
            'key' => 'other',
            'name' => 'أجور أخرى',
            'amount' => max(0, (int) round((float) ($settings['cost_other'] ?? 0))),
        ],
    ];
}

function hidden_cost_total($rows)
{
    $sum = 0;
    foreach ($rows as $row) {
        $sum += (int) ($row['amount'] ?? 0);
    }
    return $sum;
}

function line_item($product, $qty)
{
    $qty = (int) $qty;
    $unit = (int) round((float) $product['price']);
    return [
        'product_id' => $product['id'],
        'category' => $product['category'],
        'name' => $product['name'],
        'brand' => $product['brand'] ?? '',
        'specs' => $product['specs'] ?? '',
        'image' => $product['image'] ?? '',
        'qty' => $qty,
        'unit_watts' => (float) ($product['watts'] ?? 0),
        'unit_kwh' => (float) ($product['kwh'] ?? 0),
        'amp_min' => (float) ($product['amp_min'] ?? 0),
        'amp_max' => (float) ($product['amp_max'] ?? 0),
        'unit_price' => $unit,
        'line_total' => $unit * $qty,
        'show_price' => shows_price($product),
    ];
}

function next_order_code($orders)
{
    $prefix = 'SH-' . date('ymd') . '-';
    $max = 0;
    foreach ($orders as $order) {
        $code = (string) ($order['code'] ?? '');
        if (strpos($code, $prefix) === 0) {
            $n = (int) substr($code, strlen($prefix));
            if ($n > $max) {
                $max = $n;
            }
        }
    }
    return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
}

function find_order($id)
{
    foreach (read_json('orders', []) as $order) {
        if (($order['id'] ?? '') === $id) {
            return $order;
        }
    }
    return null;
}

function default_quote_form($settings)
{
    return [
        'order_id' => '',
        'customer_name' => '',
        'customer_phone' => '',
        'day_amps' => '',
        'night_amps' => '',
        'day_hours' => $settings['day_hours'],
        'night_hours' => $settings['night_hours'],
        'panel_id' => '',
        'panel_qty' => 1,
        'inverter_id' => '',
        'inverter_qty' => 1,
        'battery_id' => '',
        'battery_qty' => 1,
        'combiner_ac_id' => '',
        'combiner_dc_id' => '',
        'notes' => '',
        'acc' => [],
    ];
}

function order_form_values($order)
{
    $values = [
        'order_id' => $order['id'] ?? '',
        'customer_name' => $order['customer_name'] ?? '',
        'customer_phone' => $order['customer_phone'] ?? '',
        'day_amps' => $order['input']['day_amps'] ?? '',
        'night_amps' => $order['input']['night_amps'] ?? '',
        'day_hours' => $order['input']['day_hours'] ?? 6,
        'night_hours' => $order['input']['night_hours'] ?? 8,
        'panel_id' => '',
        'panel_qty' => 1,
        'inverter_id' => '',
        'inverter_qty' => 1,
        'battery_id' => '',
        'battery_qty' => 1,
        'combiner_ac_id' => '',
        'combiner_dc_id' => '',
        'notes' => $order['notes'] ?? '',
        'acc' => [],
    ];
    foreach ($order['items'] ?? [] as $item) {
        $category = $item['category'] ?? '';
        if ($category === 'panel') {
            $values['panel_id'] = $item['product_id'];
            $values['panel_qty'] = $item['qty'];
        } elseif ($category === 'inverter') {
            $values['inverter_id'] = $item['product_id'];
            $values['inverter_qty'] = $item['qty'];
        } elseif ($category === 'battery') {
            $values['battery_id'] = $item['product_id'];
            $values['battery_qty'] = $item['qty'];
        } elseif ($category === 'combiner_ac') {
            $values['combiner_ac_id'] = $item['product_id'];
        } elseif ($category === 'combiner_dc') {
            $values['combiner_dc_id'] = $item['product_id'];
        } elseif ($category === 'accessory') {
            $values['acc'][$item['product_id']] = $item['qty'];
        }
    }
    return $values;
}

function build_quote($post, $user, $settings, $products)
{
    $errors = [];
    $name = clean_text($post['customer_name'] ?? '', 80);
    $phone = clean_text($post['customer_phone'] ?? '', 30);
    $digits = preg_replace('/\D+/', '', $phone);
    if (str_len($name) < 2) {
        $errors[] = 'اكتب اسم الزبون';
    }
    if (strlen($digits) < 7) {
        $errors[] = 'اكتب رقم هاتف صحيح';
    }

    $dayAmps = num($post['day_amps'] ?? 0);
    $nightAmps = num($post['night_amps'] ?? 0);
    $dayHours = num($post['day_hours'] ?? 0);
    $nightHours = num($post['night_hours'] ?? 0);
    if ($dayAmps < 0 || $nightAmps < 0 || $dayAmps > 2000 || $nightAmps > 2000) {
        $errors[] = 'قيمة الأمبير غير صحيحة';
    }
    if ($dayAmps <= 0 && $nightAmps <= 0) {
        $errors[] = 'أدخل الأمبير النهاري أو الليلي';
    }
    if ($dayHours < 0.1 || $nightHours < 0.1 || $dayHours > 24 || $nightHours > 24) {
        $errors[] = 'ساعات التشغيل يجب أن تكون بين 0.1 و 24';
    }

    $design = calculate_design([
        'day_amps' => $dayAmps,
        'night_amps' => $nightAmps,
        'day_hours' => $dayHours,
        'night_hours' => $nightHours,
    ], $settings);

    $pvNeed = (int) round($design['pv_watts']);
    $inverterNeed = (int) round($design['inverter_watts']);
    $batteryNeed = round($design['battery_kwh'], 2);

    $panel = product_by_id($products, $post['panel_id'] ?? '');
    $panelQty = (int) round(num($post['panel_qty'] ?? 0));
    if ($pvNeed > 0 && (!$panel || ($panel['category'] ?? '') !== 'panel' || empty($panel['active']))) {
        $errors[] = 'اختر نوع اللوح';
    } elseif ($panel && (($panel['category'] ?? '') !== 'panel' || empty($panel['active']))) {
        $errors[] = 'اختر نوع اللوح';
    } elseif ($panel && ($panelQty < 1 || $panelQty > 999)) {
        $errors[] = 'عدد الألواح غير صحيح';
    }

    $inverter = product_by_id($products, $post['inverter_id'] ?? '');
    $inverterQty = (int) round(num($post['inverter_qty'] ?? 0));
    if (!$inverter || ($inverter['category'] ?? '') !== 'inverter' || empty($inverter['active'])) {
        $errors[] = 'اختر الإنفرتر';
    } elseif ($inverterQty < 1 || $inverterQty > 99) {
        $errors[] = 'عدد الإنفرترات غير صحيح';
    }

    $batteryId = trim((string) ($post['battery_id'] ?? ''));
    $battery = $batteryId !== '' ? product_by_id($products, $batteryId) : null;
    $batteryQty = (int) round(num($post['battery_qty'] ?? 0));
    if ($batteryNeed > 0.05 && !$battery) {
        $errors[] = 'اختر بطارية للحمل الليلي';
    }
    if ($battery) {
        if (($battery['category'] ?? '') !== 'battery' || empty($battery['active'])) {
            $errors[] = 'البطارية المختارة غير صالحة';
        } elseif ($batteryQty < 1 || $batteryQty > 99) {
            $errors[] = 'عدد البطاريات غير صحيح';
        }
    }

    $systemAmps = system_amps($dayAmps, $nightAmps);
    $boardRange = recommended_board_range($systemAmps);
    $acId = trim((string) ($post['combiner_ac_id'] ?? ''));
    $dcId = trim((string) ($post['combiner_dc_id'] ?? ''));
    $ac = $acId !== '' ? product_by_id($products, $acId) : null;
    $dc = $dcId !== '' ? product_by_id($products, $dcId) : null;
    $acOk = $ac && ($ac['category'] ?? '') === 'combiner_ac' && !empty($ac['active']);
    $dcOk = $dc && ($dc['category'] ?? '') === 'combiner_dc' && !empty($dc['active']);
    if ($systemAmps > 0) {
        if (!$acOk) {
            $errors[] = 'اختر بورد AC';
        }
        if (!$dcOk) {
            $errors[] = 'اختر بورد DC';
        }
    } else {
        if ($ac && !$acOk) {
            $errors[] = 'بورد AC المختار غير صالح';
        }
        if ($dc && !$dcOk) {
            $errors[] = 'بورد DC المختار غير صالح';
        }
    }

    $notes = clean_text($post['notes'] ?? '', 1000);
    $items = [];
    if ($panel && ($panel['category'] ?? '') === 'panel' && $panelQty >= 1 && $panelQty <= 999) {
        $items[] = line_item($panel, $panelQty);
    }
    if ($inverter && ($inverter['category'] ?? '') === 'inverter' && $inverterQty >= 1 && $inverterQty <= 99) {
        $items[] = line_item($inverter, $inverterQty);
    }
    if ($battery && ($battery['category'] ?? '') === 'battery' && $batteryQty >= 1 && $batteryQty <= 99) {
        $items[] = line_item($battery, $batteryQty);
    }
    if ($acOk) {
        $items[] = line_item($ac, 1);
    }
    if ($dcOk) {
        $items[] = line_item($dc, 1);
    }

    $equipment = 0;
    foreach ($items as $item) {
        $equipment += (int) $item['line_total'];
    }
    $hiddenCosts = hidden_cost_rows($settings, $panel ? $panelQty : 0);
    $fee = hidden_cost_total($hiddenCosts);

    $panelWattsEach = $panel ? (float) ($panel['watts'] ?? 0) : 0;
    $panelNeedForChosen = ($pvNeed > 0 && $panelWattsEach > 0) ? (int) ceil(($pvNeed / $panelWattsEach) - 0.0000001) : 0;
    $panelWatts = ($panel && $panelQty > 0) ? (int) round($panelWattsEach * $panelQty) : 0;
    $chosenInverter = ($inverter && $inverterQty > 0) ? (int) round(((float) $inverter['watts']) * $inverterQty) : 0;
    $chosenBattery = ($battery && $batteryQty > 0) ? round(((float) $battery['kwh']) * $batteryQty, 2) : 0;

    $order = [
        'id' => uid('o'),
        'code' => '',
        'customer_name' => $name,
        'customer_phone' => $phone,
        'employee_id' => $user['id'],
        'employee_name' => $user['name'],
        'status' => 'draft',
        'notes' => $notes,
        'admin_note' => '',
        'input' => [
            'day_amps' => $dayAmps,
            'night_amps' => $nightAmps,
            'day_hours' => $dayHours,
            'night_hours' => $nightHours,
        ],
        'result' => [
            'day_watts' => (int) round($design['day_watts']),
            'night_watts' => (int) round($design['night_watts']),
            'daily_kwh' => round($design['daily_kwh'], 2),
            'panel_count' => $panelNeedForChosen,
            'panel_qty' => $panel ? $panelQty : 0,
            'pv_watts' => $pvNeed,
            'inverter_watts' => $inverterNeed,
            'battery_kwh' => $batteryNeed,
            'panel_watts' => $panelWatts,
            'chosen_inverter_watts' => $chosenInverter,
            'chosen_battery_kwh' => $chosenBattery,
            'panel_status' => fit_status($panel ? $panelQty : 0, $panelNeedForChosen),
            'inverter_status' => fit_status($chosenInverter, $inverterNeed),
            'battery_status' => fit_status($chosenBattery, $batteryNeed),
            'system_amps' => round($systemAmps, 2),
            'board_amp_min' => $boardRange ? $boardRange['min'] : 0,
            'board_amp_max' => $boardRange ? $boardRange['max'] : 0,
            'combiner_ac_status' => board_fit_status($acOk ? $ac : null, $systemAmps),
            'combiner_dc_status' => board_fit_status($dcOk ? $dc : null, $systemAmps),
        ],
        'assumptions' => [
            'day_panel_factor' => (float) ($settings['day_panel_factor'] ?? 1.75),
            'ac_voltage' => (float) $settings['ac_voltage'],
            'peak_sun_hours' => (float) $settings['peak_sun_hours'],
            'pv_efficiency_percent' => (float) $settings['pv_efficiency_percent'],
            'inverter_margin_percent' => (float) $settings['inverter_margin_percent'],
            'battery_reserve_percent' => (float) ($settings['battery_reserve_percent'] ?? 20),
            'battery_dod_percent' => round((100 - (float) ($settings['battery_reserve_percent'] ?? 20)), 2),
            'battery_efficiency_percent' => (float) $settings['battery_efficiency_percent'],
        ],
        'items' => $items,
        'equipment_total' => (int) $equipment,
        'hidden_costs' => $hiddenCosts,
        'installation_fee' => $fee,
        'grand_total' => (int) $equipment + $fee,
        'created_at' => now_iso(),
        'updated_at' => now_iso(),
        'sent_at' => '',
    ];

    $old = [
        'order_id' => trim((string) ($post['order_id'] ?? '')),
        'customer_name' => $name,
        'customer_phone' => $phone,
        'day_amps' => $post['day_amps'] ?? '',
        'night_amps' => $post['night_amps'] ?? '',
        'day_hours' => $post['day_hours'] ?? $dayHours,
        'night_hours' => $post['night_hours'] ?? $nightHours,
        'panel_id' => (string) ($post['panel_id'] ?? ''),
        'panel_qty' => max(1, $panelQty),
        'inverter_id' => (string) ($post['inverter_id'] ?? ''),
        'inverter_qty' => max(1, $inverterQty),
        'battery_id' => $batteryId,
        'battery_qty' => max(1, $batteryQty),
        'combiner_ac_id' => $acId,
        'combiner_dc_id' => $dcId,
        'notes' => $notes,
        'acc' => [],
    ];

    return ['errors' => $errors, 'order' => $order, 'old' => $old];
}
