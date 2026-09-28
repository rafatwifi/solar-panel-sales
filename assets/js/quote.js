(function () {
  var form = document.getElementById('quoteForm');
  if (!form || !window.SOLAR) return;

  var steps = Array.prototype.slice.call(document.querySelectorAll('.step'));
  var dots = Array.prototype.slice.call(document.querySelectorAll('.wstep'));
  var index = Number(window.SOLAR.openStep || 0);
  var products = window.SOLAR.products || [];

  function num(value) {
    if (typeof value === 'number') return isFinite(value) ? value : 0;
    var s = String(value || '').trim().replace(/\s/g, '').replace(/٬/g, '').replace(/،/g, '.').replace(',', '.');
    var n = parseFloat(s);
    return isNaN(n) ? 0 : n;
  }

  function val(id) {
    var el = document.getElementById(id);
    return el ? el.value : '';
  }

  function product(id) {
    for (var i = 0; i < products.length; i++) {
      if (products[i].id === id) return products[i];
    }
    return null;
  }

  function selected(name) {
    var el = form.querySelector('input[name="' + name + '"]:checked');
    return el ? el.value : '';
  }

  function money(amount) {
    var currency = (window.SOLAR.settings.currency || 'د.ع');
    return Math.round(amount).toLocaleString('en-US') + ' ' + currency;
  }

  function trimNum(n) {
    return (Math.round(n * 100) / 100).toString();
  }

  function wattsText(w) {
    w = Number(w) || 0;
    if (Math.abs(w) >= 1000) return trimNum(w / 1000) + ' كيلو واط';
    return Math.round(w).toLocaleString('en-US') + ' واط';
  }

  function kwhText(k) {
    return trimNum(k) + ' كيلو واط ساعة';
  }

  function currentDesign() {
    var s = window.SOLAR.settings;
    var voltage = Math.max(1, num(s.ac_voltage));
    var dayW = num(val('day_amps')) * voltage;
    var nightW = num(val('night_amps')) * voltage;
    var dayH = num(val('day_hours'));
    var nightH = num(val('night_hours'));
    var dailyWh = dayW * dayH + nightW * nightH;
    var psh = Math.max(0.1, num(s.peak_sun_hours));
    var eff = Math.max(0.01, Math.min(1, num(s.pv_efficiency_percent) / 100));
    var margin = 1 + Math.max(0, num(s.inverter_margin_percent)) / 100;
    var reserve = Math.max(0, Math.min(99, num(s.battery_reserve_percent)));
    var dod = Math.max(0.01, (100 - reserve) / 100);
    var beff = Math.max(0.01, Math.min(1, num(s.battery_efficiency_percent) / 100));
    var dayAmps = num(val('day_amps'));
    var nightAmps = num(val('night_amps'));
    return {
      dayW: dayW,
      nightW: nightW,
      dayAmps: dayAmps,
      nightAmps: nightAmps,
      systemAmps: Math.max(dayAmps, nightAmps),
      dailyKwh: dailyWh / 1000,
      pv: Math.round(dailyWh / (psh * eff)),
      psh: psh,
      eff: eff,
      inv: Math.round(Math.max(dayW, nightW) * margin),
      battKwh: Math.round((((nightW * nightH) / (dod * beff)) / 1000) * 100) / 100
    };
  }

  function recommendedRange(amps) {
    amps = num(amps);
    if (amps <= 0) return null;
    if (amps <= 20) return { min: 10, max: 20 };
    if (amps <= 30) return { min: 20, max: 30 };
    return { min: 30, max: 40 };
  }

  function rangeText(min, max) {
    return trimNum(min) + '-' + trimNum(max) + ' أمبير';
  }

  function boardFit(item, amps) {
    amps = num(amps);
    var chosenMax = item ? num(item.ampMax) : 0;
    var chosenMin = item ? num(item.ampMin) : 0;
    if (amps <= 0) return chosenMax > 0 ? 'high' : 'ok';
    if (chosenMax <= 0) return 'low';
    var range = recommendedRange(amps);
    var same = Math.abs(chosenMin - range.min) < 0.001 && Math.abs(chosenMax - range.max) < 0.001;
    if (same) return amps > chosenMax + 0.001 ? 'low' : 'ok';
    if (chosenMax < range.max - 0.001) return 'low';
    return 'high';
  }

  function boardRequiredText(amps) {
    amps = num(amps);
    if (amps <= 0) return '0 أمبير';
    if (amps > 40) return 'أكثر من 40 أمبير';
    var range = recommendedRange(amps);
    return rangeText(range.min, range.max);
  }

  var lastBoardKey = null;
  function boardKey() {
    var range = recommendedRange(systemAmps());
    return range ? (range.min + '-' + range.max) : '0';
  }
  function syncBoardSelection() {
    var key = boardKey();
    if (lastBoardKey === key) return;
    lastBoardKey = key;
    var range = recommendedRange(systemAmps());
    selectBoard('combiner_ac_id', 'combiner_ac', range);
    selectBoard('combiner_dc_id', 'combiner_dc', range);
  }

  function systemAmps() {
    return Math.max(num(val('day_amps')), num(val('night_amps')));
  }

  var lastBatteryKey = null;
  function batteryKey() {
    return String(Math.round(currentDesign().battKwh * 100) / 100);
  }
  function recommendBattery(needKwh) {
    var list = [];
    for (var i = 0; i < products.length; i++) {
      if (products[i].category === 'battery' && num(products[i].kwh) > 0) list.push(products[i]);
    }
    if (needKwh <= 0.05 || !list.length) return { id: '', qty: 1 };
    var best = null;
    var bestTotal = 0;
    var bestQty = 0;
    for (var j = 0; j < list.length; j++) {
      var kwh = num(list[j].kwh);
      var qty = Math.max(1, Math.ceil((needKwh / kwh) - 1e-9));
      var total = qty * kwh;
      var better = !best
        || qty < bestQty
        || (qty === bestQty && total < bestTotal - 0.001);
      if (better) {
        best = list[j];
        bestTotal = total;
        bestQty = qty;
      }
    }
    return { id: best.id, qty: bestQty };
  }
  function applyBattery(choice) {
    var radios = form.querySelectorAll('input[name="battery_id"]');
    var found = false;
    for (var i = 0; i < radios.length; i++) {
      var on = radios[i].value === choice.id;
      radios[i].checked = on;
      if (on) found = true;
    }
    if (!found && choice.id) return;
    var qtyInput = document.getElementById('battery_qty');
    if (qtyInput) qtyInput.value = String(choice.qty);
  }
  function syncBatterySelection() {
    var key = batteryKey();
    if (lastBatteryKey === key) return;
    lastBatteryKey = key;
    applyBattery(recommendBattery(currentDesign().battKwh));
  }

  var lastInverterKey = null;
  function inverterKey() {
    return String(Math.round(currentDesign().inv));
  }
  function recommendInverter(needW) {
    var list = [];
    for (var i = 0; i < products.length; i++) {
      if (products[i].category === 'inverter' && num(products[i].watts) > 0) list.push(products[i]);
    }
    if (needW <= 0 || !list.length) return null;
    var best = null;
    var bestTotal = 0;
    var bestQty = 0;
    for (var j = 0; j < list.length; j++) {
      var watts = num(list[j].watts);
      var qty = Math.max(1, Math.ceil((needW / watts) - 1e-9));
      var total = qty * watts;
      var better = !best
        || total < bestTotal - 0.001
        || (Math.abs(total - bestTotal) < 0.001 && qty < bestQty);
      if (better) {
        best = list[j];
        bestTotal = total;
        bestQty = qty;
      }
    }
    return { id: best.id, qty: bestQty };
  }
  function applyInverter(choice) {
    var radios = form.querySelectorAll('input[name="inverter_id"]');
    var found = false;
    for (var i = 0; i < radios.length; i++) {
      var on = radios[i].value === choice.id;
      radios[i].checked = on;
      if (on) found = true;
    }
    if (!found) return;
    var qtyInput = document.getElementById('inverter_qty');
    if (qtyInput) qtyInput.value = String(choice.qty);
  }
  function syncInverterSelection() {
    var key = inverterKey();
    if (lastInverterKey === key) return;
    lastInverterKey = key;
    var choice = recommendInverter(currentDesign().inv);
    if (choice) applyInverter(choice);
  }

  var lastPanelKey = null;
  function panelCatalog() {
    var list = [];
    for (var i = 0; i < products.length; i++) {
      if (products[i].category === 'panel' && num(products[i].watts) > 0) list.push(products[i]);
    }
    return list;
  }
  function panelQtyFor(panel, pv) {
    pv = num(pv);
    var watts = panel ? num(panel.watts) : 0;
    if (pv <= 0 || watts <= 0) return 0;
    return Math.max(1, Math.ceil((pv / watts) - 1e-9));
  }
  function recommendPanel(pv) {
    var list = panelCatalog();
    if (pv <= 0 || !list.length) return null;
    var best = null;
    var bestQty = 0;
    var bestWatts = 0;
    for (var i = 0; i < list.length; i++) {
      var qty = panelQtyFor(list[i], pv);
      var watts = num(list[i].watts);
      var better = !best || qty < bestQty || (qty === bestQty && watts > bestWatts);
      if (better) {
        best = list[i];
        bestQty = qty;
        bestWatts = watts;
      }
    }
    return { id: best.id, qty: bestQty };
  }
  function requiredPanelQty(panel) {
    var pv = currentDesign().pv;
    if (pv <= 0) return 0;
    if (panel) return panelQtyFor(panel, pv);
    var recommended = recommendPanel(pv);
    return recommended ? recommended.qty : 0;
  }
  function applyPanel(choice) {
    var radios = form.querySelectorAll('input[name="panel_id"]');
    if (!choice) {
      for (var i = 0; i < radios.length; i++) radios[i].checked = false;
      return;
    }
    var found = false;
    for (var j = 0; j < radios.length; j++) {
      var on = radios[j].value === choice.id;
      radios[j].checked = on;
      if (on) found = true;
    }
    if (!found) return;
    var qtyInput = document.getElementById('panel_qty');
    if (qtyInput) qtyInput.value = String(choice.qty);
  }
  function syncPanelSelection() {
    var key = String(currentDesign().pv);
    if (lastPanelKey === key) return;
    lastPanelKey = key;
    applyPanel(recommendPanel(currentDesign().pv));
  }

  function selectBoard(inputName, category, range) {
    var id = '';
    if (range) {
      for (var i = 0; i < products.length; i++) {
        var p = products[i];
        if (p.category !== category) continue;
        if (Math.abs(num(p.ampMin) - range.min) < 0.001 && Math.abs(num(p.ampMax) - range.max) < 0.001) {
          id = p.id;
          break;
        }
      }
    }
    var radios = form.querySelectorAll('input[name="' + inputName + '"]');
    for (var j = 0; j < radios.length; j++) {
      radios[j].checked = radios[j].value === id;
    }
  }

  function fitInfo(selectedValue, required) {
    selectedValue = Number(selectedValue) || 0;
    required = Number(required) || 0;
    if (required <= 0.0001) return selectedValue > 0.0001 ? 'high' : 'ok';
    var ratio = selectedValue / required;
    if (ratio < 0.99) return 'low';
    if (ratio > 1.01) return 'high';
    return 'ok';
  }

  function fitText(noun, status, selectedText, requiredText) {
    if (status === 'low') return noun + ' أقل من المطلوب: المختار ' + selectedText + ' والمطلوب ' + requiredText;
    if (status === 'high') return noun + ' أعلى من المطلوب: المختار ' + selectedText + ' والمطلوب ' + requiredText;
    return noun + ' مطابق للمطلوب: ' + selectedText;
  }

  function setNotice(id, status, text) {
    var el = document.getElementById(id);
    if (!el) return;
    if (!text) {
      el.className = 'notice is-hidden';
      el.textContent = '';
      return;
    }
    el.className = 'notice notice-' + status;
    el.textContent = text;
  }

  function setError(text) {
    var el = document.getElementById('stepError');
    if (!text) {
      el.className = 'notice notice-low is-hidden';
      el.textContent = '';
      return;
    }
    el.className = 'notice notice-low';
    el.textContent = text;
  }

  function choice() {
    var design = currentDesign();
    var panel = product(selected('panel_id'));
    var inverter = product(selected('inverter_id'));
    var battery = product(selected('battery_id'));
    var combinerAc = product(selected('combiner_ac_id'));
    var combinerDc = product(selected('combiner_dc_id'));
    var panelQty = Math.max(0, Math.round(num(val('panel_qty'))));
    var inverterQty = Math.max(0, Math.round(num(val('inverter_qty'))));
    var batteryQty = Math.max(0, Math.round(num(val('battery_qty'))));
    var panelWatts = panel ? panel.watts * panelQty : 0;
    var inverterWatts = inverter ? inverter.watts * inverterQty : 0;
    var batteryKwh = battery ? battery.kwh * batteryQty : 0;
    return {
      design: design,
      panel: panel,
      inverter: inverter,
      battery: battery,
      panelQty: panelQty,
      inverterQty: inverterQty,
      batteryQty: batteryQty,
      panelWatts: panelWatts,
      inverterWatts: inverterWatts,
      batteryKwh: batteryKwh,
      panelStatus: fitInfo(panel ? panelQty : 0, requiredPanelQty(panel)),
      inverterStatus: inverter ? fitInfo(inverterWatts, design.inv) : '',
      batteryStatus: fitInfo(batteryKwh, design.battKwh),
      combinerAc: combinerAc,
      combinerDc: combinerDc,
      acStatus: boardFit(combinerAc, design.systemAmps),
      dcStatus: boardFit(combinerDc, design.systemAmps)
    };
  }

  function showsPrice(item) {
    if (!item) return false;
    return !!window.SOLAR.isAdmin || !!item.showPrice;
  }

  function lineTotal(item, qty) {
    if (!item || qty <= 0 || item.price === undefined || item.price === null || item.price === '') return 0;
    return Math.round(item.price) * qty;
  }

  function setText(id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  function liveMeasure(id, suffix) {
    var raw = String(val(id) || '').trim();
    if (raw === '') return '—';
    return trimNum(num(raw)) + ' ' + suffix;
  }

  function renderDesign() {
    setText('loadDayAmps', liveMeasure('day_amps', 'أمبير'));
    setText('loadNightAmps', liveMeasure('night_amps', 'أمبير'));
    setText('loadNightHours', liveMeasure('night_hours', 'ساعة'));
    var design = currentDesign();
    var panelNeed = requiredPanelQty(product(selected('panel_id')));
    var boxes = document.querySelectorAll('#designStrip strong');
    if (boxes.length >= 3) {
      boxes[0].textContent = panelNeed + ' لوح';
      boxes[1].textContent = wattsText(design.inv);
      boxes[2].textContent = kwhText(design.battKwh);
    }
    if (boxes.length >= 4) {
      var range = recommendedRange(design.systemAmps);
      boxes[3].textContent = range ? rangeText(range.min, range.max) : 'اختياري';
    }
    var extra = document.getElementById('dailyLine');
    if (extra) {
      var recommended = recommendPanel(design.pv);
      var line = 'استهلاك اليوم ' + trimNum(design.dailyKwh) + ' كيلو واط ساعة، وقدرة الألواح المطلوبة ' + wattsText(design.pv);
      if (recommended) line += '، والعدد ' + recommended.qty + ' من الحجم الأكبر';
      extra.textContent = line + '. حمل النهار ' + wattsText(design.dayW) + ' وحمل الليل ' + wattsText(design.nightW) + '.';
    }
  }

  function renderFit() {
    var c = choice();
    var panelText = '';
    var panelNoticeStatus = 'ok';
    if (c.design.pv <= 0 && !c.panel) {
      panelText = 'لا حاجة لألواح لأن الاستهلاك صفر';
    } else {
      panelNoticeStatus = c.panelStatus || 'low';
      panelText = fitText('الألواح', panelNoticeStatus, c.panelQty + ' لوح', requiredPanelQty(c.panel) + ' لوح');
    }
    setNotice('panelFit', panelNoticeStatus, panelText);

    var inverterText = '';
    if (c.inverter) {
      inverterText = fitText('الإنفرتر', c.inverterStatus, wattsText(c.inverterWatts), wattsText(c.design.inv));
    }
    setNotice('inverterFit', c.inverterStatus || 'ok', inverterText);

    var batteryText = '';
    if (c.design.battKwh <= 0.05 && !c.battery) {
      batteryText = 'لا حاجة لبطارية لأن الحمل الليلي صفر';
      setNotice('batteryFit', 'ok', batteryText);
    } else if (c.battery || c.design.battKwh > 0.05) {
      batteryText = fitText('البطارية', c.batteryStatus, kwhText(c.batteryKwh), kwhText(c.design.battKwh));
      setNotice('batteryFit', c.batteryStatus, batteryText);
    } else {
      setNotice('batteryFit', 'ok', '');
    }

    var panelLine = document.getElementById('panelLine');
    var inverterLine = document.getElementById('inverterLine');
    var batteryLine = document.getElementById('batteryLine');
    if (panelLine) panelLine.textContent = c.panel && showsPrice(c.panel) ? money(lineTotal(c.panel, c.panelQty)) : '';
    if (inverterLine) inverterLine.textContent = c.inverter && showsPrice(c.inverter) ? money(lineTotal(c.inverter, c.inverterQty)) : '';
    if (batteryLine) batteryLine.textContent = c.battery && showsPrice(c.battery) ? money(lineTotal(c.battery, c.batteryQty)) : '';

    renderBoardFit('combinerAcFit', 'combinerAcLine', 'بورد AC', c.combinerAc, c.acStatus, c.design.systemAmps);
    renderBoardFit('combinerDcFit', 'combinerDcLine', 'بورد DC', c.combinerDc, c.dcStatus, c.design.systemAmps);
  }

  function renderBoardFit(noticeId, lineId, label, item, status, amps) {
    var text = '';
    var noticeStatus = status || 'ok';
    if (amps <= 0 && !item) {
      text = 'لا حاجة ل' + label + ' لأن الأمبير صفر';
      noticeStatus = 'ok';
    } else if (!item && amps > 0) {
      text = 'اختر ' + label;
      noticeStatus = 'low';
    } else if (item) {
      text = fitText(label, noticeStatus, rangeText(item.ampMin, item.ampMax), boardRequiredText(amps));
    }
    setNotice(noticeId, noticeStatus, text);
    var line = document.getElementById(lineId);
    if (line) line.textContent = item && showsPrice(item) ? money(lineTotal(item, 1)) : '';
  }

  function addRow(parent, title, meta, price) {
    var row = document.createElement('div');
    row.className = 'sum-row';
    var left = document.createElement('div');
    var strong = document.createElement('strong');
    strong.textContent = title;
    left.appendChild(strong);
    if (meta) {
      var small = document.createElement('div');
      small.className = 'muted';
      small.textContent = meta;
      left.appendChild(small);
    }
    row.appendChild(left);
    if (price) {
      var right = document.createElement('div');
      right.className = 'money';
      right.textContent = price;
      row.appendChild(right);
    }
    parent.appendChild(row);
  }

  function renderSummary() {
    var box = document.getElementById('summaryBox');
    if (!box) return;
    while (box.firstChild) box.removeChild(box.firstChild);
    var c = choice();
    var list = document.createElement('div');
    list.className = 'summary-list';

    [['panelFit', 'الألواح'], ['inverterFit', 'الإنفرتر'], ['batteryFit', 'البطارية'], ['combinerAcFit', 'بورد AC'], ['combinerDcFit', 'بورد DC']].forEach(function (pair) {
      var src = document.getElementById(pair[0]);
      if (!src || !src.textContent) return;
      var note = document.createElement('div');
      note.className = src.className;
      note.textContent = src.textContent;
      list.appendChild(note);
    });

    var equipment = 0;
    var concealed = false;
    var priced = 0;
    function addPriced(item, qty, meta) {
      if (!item) return;
      var visible = showsPrice(item);
      var total = lineTotal(item, qty);
      equipment += total;
      if (visible) {
        priced += 1;
        addRow(list, item.name, meta, money(total));
      } else {
        concealed = true;
        addRow(list, item.name, meta, '');
      }
    }
    addPriced(c.panel, c.panelQty, c.panel ? (c.panelQty + ' × ' + wattsText(c.panel.watts)) : '');
    addPriced(c.inverter, c.inverterQty, c.inverter ? (c.inverterQty + ' × ' + wattsText(c.inverter.watts)) : '');
    addPriced(c.battery, c.batteryQty, c.battery ? (c.batteryQty + ' × ' + kwhText(c.battery.kwh)) : '');
    addPriced(c.combinerAc, 1, c.combinerAc ? ('1 × ' + rangeText(c.combinerAc.ampMin, c.combinerAc.ampMax)) : '');
    addPriced(c.combinerDc, 1, c.combinerDc ? ('1 × ' + rangeText(c.combinerDc.ampMin, c.combinerDc.ampMax)) : '');

    var settings = window.SOLAR.settings;
    var fee = Math.max(0, Math.round(num(settings.cost_panel_install) * c.panelQty))
      + Math.max(0, Math.round(num(settings.cost_other)));

    if (window.SOLAR.isAdmin) {
      var total = document.createElement('div');
      total.className = 'total-row';
      var label = document.createElement('span');
      label.textContent = 'مجموع المواد';
      var value = document.createElement('b');
      value.textContent = money(equipment);
      total.appendChild(label);
      total.appendChild(value);
      list.appendChild(total);
      var hiddenRows = [
        ['أجور تركيب الألواح', num(settings.cost_panel_install) * c.panelQty],
        ['أجور أخرى', num(settings.cost_other)]
      ];
      hiddenRows.forEach(function (row) {
        var amount = Math.max(0, Math.round(row[1]));
        addRow(list, row[0], 'لا يظهر للموظف', money(amount));
      });
      var grand = document.createElement('div');
      grand.className = 'total-row';
      var gl = document.createElement('span');
      gl.textContent = 'السعر النهائي';
      var gv = document.createElement('b');
      gv.textContent = money(equipment + fee);
      grand.appendChild(gl);
      grand.appendChild(gv);
      list.appendChild(grand);
    } else {
      var grandEmp = document.createElement('div');
      grandEmp.className = 'total-row';
      var gel = document.createElement('span');
      gel.textContent = 'السعر النهائي';
      var gev = document.createElement('b');
      gev.textContent = money(equipment + fee);
      grandEmp.appendChild(gel);
      grandEmp.appendChild(gev);
      list.appendChild(grandEmp);
    }

    var daily = document.createElement('p');
    daily.className = 'hint';
    daily.textContent = 'استهلاك اليوم ' + trimNum(c.design.dailyKwh) + ' كيلو واط ساعة.';
    list.appendChild(daily);
    box.appendChild(list);
  }

  function show(next) {
    syncBoardSelection();
    syncBatterySelection();
    syncInverterSelection();
    syncPanelSelection();
    index = Math.max(0, Math.min(steps.length - 1, next));
    steps.forEach(function (el, n) { el.classList.toggle('active', n === index); });
    dots.forEach(function (el, n) {
      el.classList.toggle('active', n === index);
      el.classList.toggle('done', n < index);
    });
    document.getElementById('prevBtn').classList.toggle('is-hidden', index === 0);
    var last = index === steps.length - 1;
    document.getElementById('nextBtn').classList.toggle('is-hidden', last);
    document.getElementById('sendBtn').classList.toggle('is-hidden', !last);
    document.getElementById('draftBtn').classList.toggle('is-hidden', !last);
    document.getElementById('stepCount').textContent = 'الخطوة ' + (index + 1) + ' من ' + steps.length;
    var currentDot = dots[index];
    var currentLabel = currentDot ? currentDot.querySelector('.step-label') : null;
    if (currentLabel && currentLabel.textContent) {
      document.getElementById('stepCount').textContent += ' — ' + currentLabel.textContent;
    }
    var strip = document.getElementById('designStrip');
    if (strip) strip.classList.toggle('is-hidden', index === 0);
    var daily = document.getElementById('dailyLine');
    if (daily) daily.classList.toggle('is-hidden', index === 0);
    renderDesign();
    renderFit();
    if (last) renderSummary();
    setError('');
  }

  function validate(step) {
    if (step === 0) {
      if (val('customer_name').trim().length < 2) return 'اكتب اسم الزبون';
      if (val('customer_phone').replace(/\D/g, '').length < 7) return 'اكتب رقم هاتف صحيح';
    }
    if (step === 1) {
      if (num(val('day_amps')) <= 0 && num(val('night_amps')) <= 0) return 'أدخل الأمبير النهاري أو الليلي';
      var dayH = num(val('day_hours'));
      var nightH = num(val('night_hours'));
      if (dayH < 0.1 || nightH < 0.1 || dayH > 24 || nightH > 24) return 'ساعات التشغيل بين 0.1 و 24';
    }
    if (step === 2) {
      if (currentDesign().pv > 0 && !selected('panel_id')) return 'اختر نوع اللوح';
      if (selected('panel_id') && num(val('panel_qty')) < 1) return 'حدد عدد الألواح';
    }
    if (step === 3) {
      if (!selected('inverter_id')) return 'اختر الإنفرتر';
      if (num(val('inverter_qty')) < 1) return 'حدد عدد الإنفرترات';
    }
    if (step === 4) {
      var design = currentDesign();
      if (design.battKwh > 0.05 && !selected('battery_id')) return 'اختر بطارية تغطي الحمل الليلي';
      if (selected('battery_id') && num(val('battery_qty')) < 1) return 'حدد عدد البطاريات';
    }
    if (step === 5) {
      syncBoardSelection();
      var amps = systemAmps();
      if (amps > 0 && !selected('combiner_ac_id')) return 'اختر بورد AC';
      if (amps > 0 && !selected('combiner_dc_id')) return 'اختر بورد DC';
    }
    return '';
  }

  function hasLow() {
    var c = choice();
    return c.panelStatus === 'low' || c.inverterStatus === 'low' || c.batteryStatus === 'low' || c.acStatus === 'low' || c.dcStatus === 'low';
  }

  document.getElementById('nextBtn').addEventListener('click', function () {
    var error = validate(index);
    if (error) {
      setError(error);
      return;
    }
    show(index + 1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
  document.getElementById('prevBtn').addEventListener('click', function () {
    show(index - 1);
  });
  dots.forEach(function (dot, n) {
    dot.addEventListener('click', function () {
      if (n <= index) {
        show(n);
        return;
      }
      for (var i = index; i < n; i++) {
        var error = validate(i);
        if (error) {
          setError(error);
          show(i);
          return;
        }
      }
      show(n);
    });
  });

  form.addEventListener('change', function (event) {
    var target = event.target;
    if (target && target.name === 'panel_id' && target.value) {
      var panelPick = product(target.value);
      var panelNeed = currentDesign().pv;
      var qtyInput = document.getElementById('panel_qty');
      if (qtyInput) qtyInput.value = String(panelQtyFor(panelPick, panelNeed));
    }
    if (target && target.name === 'inverter_id' && target.value) {
      var inverter = product(target.value);
      var inverterNeed = currentDesign();
      if (inverter && inverter.watts > 0) {
        var inverterQty = inverterNeed.inv > 0 ? Math.max(1, Math.ceil((inverterNeed.inv / inverter.watts) - 1e-9)) : 1;
        document.getElementById('inverter_qty').value = String(inverterQty);
      }
    }
    if (target && target.name === 'battery_id' && target.value) {
      var battery = product(target.value);
      var need = currentDesign();
      if (battery && battery.kwh > 0) {
        var qty = need.battKwh > 0 ? Math.max(1, Math.ceil((need.battKwh / battery.kwh) - 1e-9)) : 1;
        document.getElementById('battery_qty').value = String(qty);
      }
    }
    if (target && (target.id === 'day_amps' || target.id === 'night_amps')) {
      syncBoardSelection();
    }
    syncBatterySelection();
    syncInverterSelection();
    syncPanelSelection();
    renderDesign();
    renderFit();
    if (index === steps.length - 1) renderSummary();
  });
  form.addEventListener('input', function (event) {
    var target = event.target;
    if (target && (target.id === 'day_amps' || target.id === 'night_amps')) {
      syncBoardSelection();
    }
    syncBatterySelection();
    syncInverterSelection();
    syncPanelSelection();
    renderDesign();
    renderFit();
    if (index === steps.length - 1) renderSummary();
  });
  form.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA') {
      event.preventDefault();
      if (index < steps.length - 1) document.getElementById('nextBtn').click();
    }
  });

  document.querySelectorAll('[data-stepper]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-stepper'));
      if (!input) return;
      var min = num(input.min || 0);
      input.value = String(Math.max(min, Math.round(num(input.value) + num(btn.getAttribute('data-dir')))));
      renderDesign();
      renderFit();
      if (index === steps.length - 1) renderSummary();
    });
  });

  function guard(event, confirmLow) {
    for (var i = 0; i < steps.length - 1; i++) {
      var error = validate(i);
      if (error) {
        event.preventDefault();
        show(i);
        setError(error);
        return;
      }
    }
    if (confirmLow && hasLow() && !window.confirm('في مكونات أقل من المطلوب. تريد إرسال الطلب للمبيعات؟')) {
      event.preventDefault();
    }
  }
  document.getElementById('sendBtn').addEventListener('click', function (event) { guard(event, true); });
  document.getElementById('draftBtn').addEventListener('click', function (event) { guard(event, false); });

  document.querySelectorAll('[data-filter]').forEach(function (input) {
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();
      var grid = document.getElementById(input.getAttribute('data-filter'));
      if (!grid) return;
      grid.querySelectorAll('.pick-card, .acc-row').forEach(function (card) {
        card.hidden = q !== '' && card.textContent.toLowerCase().indexOf(q) === -1;
      });
    });
  });

  if (!selected('combiner_ac_id')) {
    selectBoard('combiner_ac_id', 'combiner_ac', recommendedRange(systemAmps()));
  }
  if (!selected('combiner_dc_id')) {
    selectBoard('combiner_dc_id', 'combiner_dc', recommendedRange(systemAmps()));
  }
  lastBoardKey = boardKey();
  if (selected('battery_id')) lastBatteryKey = batteryKey();
  if (selected('inverter_id')) lastInverterKey = inverterKey();
  if (selected('panel_id') && currentDesign().pv > 0) {
    lastPanelKey = String(currentDesign().pv);
  }
  show(index);
})();
