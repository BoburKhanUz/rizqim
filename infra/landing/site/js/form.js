/* "Hamkor bo'lish" formasi: ikki qadam, mijoz tekshiruvi (server qoidalariga mos), POST /api/v1/store-applications. */
(function () {
  'use strict';
  var RZ = window.RZ;
  var form = document.getElementById('applyForm');
  if (!RZ || !form) return;

  var API = (RZ.cfg.apiBase || '/api/v1').replace(/\/$/, '');
  var FORMATS = ['restaurant', 'cafe', 'fast_food', 'canteen', 'coffee_shop', 'bakery', 'teahouse', 'catering', 'other'];
  var $ = function (id) { return document.getElementById(id); };
  var el = {
    owner_name: $('f-owner_name'), phone: $('f-phone'), name: $('f-name'), format: $('f-format'), region: $('f-region'),
    city: $('f-city'), tin: $('f-tin'), comment: $('f-comment'), consent: $('f-consent'),
  };
  var errBox = { owner_name: $('e-owner_name'), phone: $('e-phone'), name: $('e-name'), format: $('e-format'), region: $('e-region'), tin: $('e-tin'), comment: $('e-comment'), consent: $('e-consent') };
  var ps1 = $('ps1'), ps2 = $('ps2'), st1 = $('st1'), st2 = $('st2');
  var banner = $('banner'), submitBtn = $('submitBtn'), submitLabel = $('submitLabel');
  var errors = {};        // field -> { key } | { text }
  var step = 1;
  var sending = false;
  var bannerState = null; // 'fail' | 'throttle' | 'fix' | null
  var regionsOk = false;

  /* ---------- phone mask: +998 XX XXX XX XX ---------- */
  function phoneDigits(v) {
    var raw = String(v).trim();
    var d = raw.replace(/\D+/g, '');
    // 998 country code is dropped only when typed as +998... or when the number is longer than 9 digits (pasted 998901234567)
    if (d.indexOf('998') === 0 && (raw.charAt(0) === '+' || d.length > 9)) d = d.slice(3);
    return d.slice(0, 9);
  }
  function formatPhone(d) {
    if (!d) return '';
    var s = '+998 ' + d.slice(0, 2);
    if (d.length > 2) s += ' ' + d.slice(2, 5);
    if (d.length > 5) s += ' ' + d.slice(5, 7);
    if (d.length > 7) s += ' ' + d.slice(7, 9);
    return s;
  }
  el.phone.addEventListener('input', function () { el.phone.value = formatPhone(phoneDigits(el.phone.value)); clearErr('phone'); });
  el.tin.addEventListener('input', function () { el.tin.value = el.tin.value.replace(/\D+/g, '').slice(0, 9); clearErr('tin'); });

  /* ---------- validation (same rules as the server) ---------- */
  var rules = {
    owner_name: function (v) { v = v.trim(); return v.length >= 2 && v.length <= 120; },
    phone: function (v) { return phoneDigits(v).length === 9; },
    name: function (v) { v = v.trim(); return v.length >= 2 && v.length <= 120; },
    format: function (v) { return FORMATS.indexOf(v) >= 0; },
    region: function () { return regionsOk ? /^\d+$/.test(el.region.value) : el.city.value.trim().length >= 2 && el.city.value.trim().length <= 120; },
    tin: function (v) { return v === '' || /^\d{9}$/.test(v); },
    comment: function (v) { return v.length <= 1000; },
  };
  function value(f) { return f === 'region' ? (regionsOk ? el.region.value : el.city.value) : el[f].value; }
  function invalidKey(f) { return f === 'region' && !regionsOk ? 'err.city' : 'err.' + f; }
  function check(fields) {
    var first = null;
    fields.forEach(function (f) {
      if (!rules[f](value(f))) { errors[f] = { key: invalidKey(f) }; if (!first) first = f; } else delete errors[f];
    });
    return first;
  }

  function control(f) { return f === 'region' ? (regionsOk ? el.region : el.city) : el[f]; }
  function renderErrors() {
    Object.keys(errBox).forEach(function (f) {
      var e = errors[f], box = errBox[f];
      box.textContent = '';
      if (e) {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'icon'); svg.setAttribute('aria-hidden', 'true');
        var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        use.setAttribute('href', '/img/icons.svg#i-alert');
        svg.appendChild(use);
        var span = document.createElement('span');
        span.textContent = e.text || RZ.t(e.key);
        box.appendChild(svg); box.appendChild(span);
      }
      var c = f === 'consent' ? el.consent : control(f);
      c.setAttribute('aria-invalid', e ? 'true' : 'false');
      if (f === 'region') { el.region.setAttribute('aria-invalid', e && regionsOk ? 'true' : 'false'); el.city.setAttribute('aria-invalid', e && !regionsOk ? 'true' : 'false'); }
    });
  }
  function clearErr(f) { if (errors[f]) { delete errors[f]; renderErrors(); } if (bannerState === 'fix') setBanner(null); }

  Object.keys(el).forEach(function (f) {
    var k = f === 'city' ? 'region' : f;
    el[f].addEventListener('input', function () { clearErr(k); });
    el[f].addEventListener('change', function () { clearErr(k); });
  });

  /* ---------- banner (error / throttled / fix) ---------- */
  function setBanner(state) {
    bannerState = state;
    banner.textContent = '';
    banner.hidden = !state;
    if (!state) return;
    banner.className = 'banner' + (state === 'throttle' || state === 'fix' ? ' warn' : '');
    var svg = '<svg class="icon" aria-hidden="true"><use href="/img/icons.svg#i-' + (state === 'fail' ? 'x' : 'alert') + '"/></svg>';
    var box = document.createElement('div');
    var h = document.createElement('h3'), p = document.createElement('p');
    if (state === 'fail') { h.textContent = RZ.t('fail.title'); p.textContent = RZ.t('fail.text'); }
    else if (state === 'throttle') { h.textContent = RZ.t('throttle.title'); p.textContent = RZ.t('throttle.text'); }
    else { h.textContent = RZ.t('err.fix'); p.hidden = true; }
    box.appendChild(h); box.appendChild(p);
    if (state === 'fail') {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'btn btn-outline btn-sm'; b.id = 'retryBtn';
      b.innerHTML = '<svg class="icon" aria-hidden="true"><use href="/img/icons.svg#i-refresh"/></svg><span></span>';
      b.lastChild.textContent = RZ.t('fail.retry');
      b.addEventListener('click', submit);
      box.appendChild(b);
    }
    banner.innerHTML = svg;
    banner.appendChild(box);
  }

  /* ---------- steps ---------- */
  function showStep(n) {
    step = n;
    form.setAttribute('data-step', String(n));
    ps1.classList.toggle('off', false);
    ps2.classList.toggle('off', n !== 2);
    // On a phone only the active card shows (css .off); on desktop both show, the inactive one dimmed
    ps1.classList.toggle('off', n === 2 && window.matchMedia('(max-width: 899px)').matches);
    if (n === 2) ps2.removeAttribute('inert'); else ps2.setAttribute('inert', '');
    if (n === 1) ps1.removeAttribute('inert'); else if (!window.matchMedia('(max-width: 899px)').matches) ps1.setAttribute('inert', ''); else ps1.removeAttribute('inert');
    st1.className = n === 2 ? 'done' : '';
    st1.setAttribute('aria-current', n === 1 ? 'step' : 'false');
    st2.setAttribute('aria-current', n === 2 ? 'step' : 'false');
    st1.querySelector('.n').innerHTML = n === 2 ? '<svg class="icon" aria-hidden="true"><use href="/img/icons.svg#i-check"/></svg>' : '1';
    setBanner(null);
  }
  window.addEventListener('resize', function () { showStep(step); });
  showStep(1);

  function focusField(f) { var c = f === 'consent' ? el.consent : control(f); if (c) c.focus(); }

  $('nextBtn').addEventListener('click', function () {
    var bad = check(['owner_name', 'phone']);
    renderErrors();
    if (bad) { focusField(bad); return; }
    showStep(2);
    el.name.focus();
    loadRegions();
  });
  $('backBtn').addEventListener('click', function () { showStep(1); el.owner_name.focus(); });
  el.owner_name.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('nextBtn').click(); } });
  el.phone.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('nextBtn').click(); } });

  /* ---------- selects ---------- */
  var regionList = [];
  function fillFormats() {
    var cur = el.format.value;
    el.format.innerHTML = '';
    FORMATS.forEach(function (f) { var o = document.createElement('option'); o.value = f; o.textContent = RZ.t('fmt.' + f); el.format.appendChild(o); });
    el.format.value = cur || 'restaurant';
  }
  function fillRegions(loading) {
    var cur = el.region.value;
    el.region.innerHTML = '';
    var ph = document.createElement('option');
    ph.value = ''; ph.textContent = loading ? RZ.t('f.regionsLoading') : RZ.t('f.region.ph');
    el.region.appendChild(ph);
    regionList.forEach(function (r) { var o = document.createElement('option'); o.value = String(r.id); o.textContent = r.name; el.region.appendChild(o); });
    el.region.value = cur;
  }
  function loadRegions() {
    if (regionsOk) return;
    fillRegions(true);
    var ctrl = typeof AbortController === 'function' ? new AbortController() : null;
    var timer = setTimeout(function () { if (ctrl) ctrl.abort(); }, 10000);
    fetch(API + '/locations', { headers: { Accept: 'application/json' }, signal: ctrl ? ctrl.signal : undefined })
      .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.json(); })
      .then(function (j) {
        var list = (j && j.data && j.data.regions || []).filter(function (r) { return r.is_active !== false; });
        if (!list.length) throw new Error('empty');
        regionList = list; regionsOk = true;
        el.region.hidden = false; el.city.hidden = true;
        fillRegions(false);
      })
      .catch(function () {
        // Locations unavailable: a free-text field still lets the person apply (the server accepts `city`)
        regionsOk = false;
        el.region.hidden = true; el.city.hidden = false; el.city.removeAttribute('hidden');
        el.region.removeAttribute('required');
      })
      .then(function () { clearTimeout(timer); });
  }

  /* ---------- submit ---------- */
  function setSending(on) {
    sending = on;
    submitBtn.disabled = on;
    submitBtn.setAttribute('aria-busy', String(on));
    if (on) {
      submitLabel.textContent = RZ.t('f.sending');
      submitBtn.querySelector('.icon').style.display = 'none';
      if (!submitBtn.querySelector('.spinner')) { var s = document.createElement('span'); s.className = 'spinner'; s.setAttribute('aria-hidden', 'true'); submitBtn.insertBefore(s, submitLabel); }
    } else {
      submitLabel.textContent = RZ.t('f.submit');
      submitBtn.querySelector('.icon').style.display = '';
      var sp = submitBtn.querySelector('.spinner'); if (sp) sp.remove();
    }
    var rb = $('retryBtn'); if (rb) rb.disabled = on;
  }

  function payload() {
    var p = {
      name: el.name.value.trim(),
      format: el.format.value,
      owner_name: el.owner_name.value.trim(),
      phone: '+998' + phoneDigits(el.phone.value),
      source: 'website',
      website: form.elements.rz_site_url.value,
    };
    if (regionsOk) p.region_id = parseInt(el.region.value, 10); else p.city = el.city.value.trim();
    if (el.tin.value) p.tin = el.tin.value;
    if (el.comment.value.trim()) p.comment = el.comment.value.trim();
    return p;
  }

  var SERVER_FIELD = { owner_name: 'owner_name', phone: 'phone', name: 'name', format: 'format', region_id: 'region', city: 'region', tin: 'tin', comment: 'comment' };

  function submit(ev) {
    if (ev && ev.preventDefault) ev.preventDefault();
    if (sending) return;
    var bad = check(['owner_name', 'phone', 'name', 'format', 'region', 'tin', 'comment']);
    if (!el.consent.checked) { errors.consent = { key: 'err.consent' }; if (!bad) bad = 'consent'; } else delete errors.consent;
    renderErrors();
    if (bad) {
      if (errors.owner_name || errors.phone) { showStep(1); }
      setBanner('fix');
      focusField(bad);
      return;
    }
    setBanner(null);
    setSending(true);
    var ctrl = typeof AbortController === 'function' ? new AbortController() : null;
    var timer = setTimeout(function () { if (ctrl) ctrl.abort(); }, 20000);
    fetch(API + '/store-applications', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload()),
      signal: ctrl ? ctrl.signal : undefined,
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) { return { status: r.status, body: j }; });
    }).then(function (res) {
      clearTimeout(timer);
      setSending(false);
      if (res.status === 201 || res.status === 200) return success();
      if (res.status === 429) return setBanner('throttle');
      if (res.status === 422) return serverErrors(res.body);
      setBanner('fail');
    }).catch(function () {
      clearTimeout(timer);
      setSending(false);
      setBanner('fail');
    });
  }

  function serverErrors(body) {
    var e = (body && body.errors) || {};
    var first = null, any = false;
    Object.keys(e).forEach(function (k) {
      var f = SERVER_FIELD[k];
      if (!f) return;
      var msg = Array.isArray(e[k]) ? e[k][0] : e[k];
      // The server speaks Uzbek: in Russian show the local message of that field
      errors[f] = RZ.lang === 'uz' && typeof msg === 'string' ? { text: msg } : { key: invalidKey(f) };
      any = true;
      if (!first) first = f;
    });
    if (!any) return setBanner('fail');
    renderErrors();
    if (errors.owner_name || errors.phone) showStep(1);
    setBanner('fix');
    focusField(first);
  }

  function success() {
    $('formArea').hidden = true;
    var s = $('successArea');
    s.hidden = false;
    s.focus();
    try { s.scrollIntoView({ block: 'center' }); } catch (e) { /* old browser */ }
  }

  form.addEventListener('submit', submit);

  /* ---------- language ---------- */
  function rerender() {
    fillFormats();
    fillRegions(false);
    renderErrors();
    if (bannerState) setBanner(bannerState);
    if (sending) submitLabel.textContent = RZ.t('f.sending');
  }
  RZ.onLang(rerender);
  fillFormats();
  fillRegions(false);
  // Regions are needed only at step 2; start early on an idle moment so the list is ready
  setTimeout(loadRegions, 600);
})();
