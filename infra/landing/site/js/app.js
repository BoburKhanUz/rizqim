/* rizqim.uz: til (uz/ru), menyu, sozlama (site.config.js) va FAQ javoblari. Kutubxonasiz, build qadamisiz. */
(function () {
  'use strict';
  var DEFAULT = 'uz';
  var CFG = window.RZ_SITE || {};
  var LANGS = ['uz', 'ru'];
  var listeners = [];
  var lang = DEFAULT;

  function store(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { /* private mode */ } return null; }
  function pick() {
    var q = null;
    try { q = new URLSearchParams(location.search).get('lang'); } catch (e) { /* old browser */ }
    if (LANGS.indexOf(q) >= 0) return q;
    var s = store('rz_lang');
    return LANGS.indexOf(s) >= 0 ? s : DEFAULT;
  }
  function dict(l) { return (window.RZ_I18N && window.RZ_I18N[l]) || {}; }
  function t(key) {
    var d = dict(lang);
    if (d[key] != null) return d[key];
    var u = dict(DEFAULT);
    return u[key] != null ? u[key] : key;
  }
  function get(path) { return path.split('.').reduce(function (o, k) { return o == null ? o : o[k]; }, CFG); }
  function localised(v) { return v && typeof v === 'object' ? (v[lang] || v[DEFAULT] || '') : (v || ''); }

  function applyI18n() {
    document.documentElement.lang = lang;
    document.querySelectorAll('[data-i18n]').forEach(function (el) { el.textContent = t(el.getAttribute('data-i18n')); });
    document.querySelectorAll('[data-i18n-html]').forEach(function (el) { el.innerHTML = t(el.getAttribute('data-i18n-html')); });
    document.querySelectorAll('[data-i18n-attr]').forEach(function (el) {
      el.getAttribute('data-i18n-attr').split(',').forEach(function (pair) {
        var i = pair.indexOf(':');
        el.setAttribute(pair.slice(0, i).trim(), t(pair.slice(i + 1).trim()));
      });
    });
    var title = document.querySelector('title[data-i18n]');
    if (title) document.title = t(title.getAttribute('data-i18n'));
    document.querySelectorAll('[data-lang]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-lang') === lang)); });
    var og = document.querySelector('meta[property="og:locale"]');
    if (og) og.setAttribute('content', lang === 'ru' ? 'ru_RU' : 'uz_UZ');
    applyConfig();
  }

  function applyConfig() {
    document.querySelectorAll('[data-cfg]').forEach(function (el) { var v = get(el.getAttribute('data-cfg')); if (v) el.textContent = v; });
    document.querySelectorAll('[data-cfg-i18n]').forEach(function (el) { var v = localised(get(el.getAttribute('data-cfg-i18n'))); if (v) el.textContent = v; });
    document.querySelectorAll('[data-cfg-href]').forEach(function (el) { var v = get(el.getAttribute('data-cfg-href')); if (v) el.setAttribute('href', v); });
    document.querySelectorAll('[data-cfg-mailto]').forEach(function (el) { var v = get(el.getAttribute('data-cfg-mailto')); if (v) el.setAttribute('href', 'mailto:' + v); });
    document.querySelectorAll('[data-cfg-host]').forEach(function (el) { var v = get(el.getAttribute('data-cfg-host')); if (v) el.textContent = String(v).replace(/^https?:\/\//, '').replace(/\/$/, ''); });
    document.querySelectorAll('[data-cfg-show]').forEach(function (el) { el.hidden = !localised(get(el.getAttribute('data-cfg-show'))); });
    document.querySelectorAll('a[data-cfg-href^="social."]').forEach(function (el) { el.hidden = !get(el.getAttribute('data-cfg-href')); });
    var any = document.querySelectorAll('.social a:not([hidden])').length > 0;
    document.querySelectorAll('.social').forEach(function (s) { s.hidden = !any; });
    // footer: hide the empty contacts list and let the CSS close the gap
    document.querySelectorAll('.contacts').forEach(function (ul) {
      var has = ul.querySelectorAll('li:not([hidden])').length > 0;
      ul.hidden = !has;
      var foot = ul.closest('.site-footer');
      if (foot) foot.classList.toggle('no-contacts', !has);
    });
    // FAQ: the owner's answer, or a neutral "Tez orada"
    document.querySelectorAll('[data-faq-ans]').forEach(function (el) {
      var a = localised(get('faq.' + el.getAttribute('data-faq-ans')));
      el.textContent = a || t('faq.soon');
      el.classList.toggle('soon', !a);
    });
    // Tariffs: shown only when the owner gave at least one real value
    var shown = 0;
    document.querySelectorAll('[data-tariff]').forEach(function (el) {
      var v = localised(get('tariffs.' + el.getAttribute('data-tariff')));
      el.hidden = !v;
      if (v) { el.querySelector('dd').textContent = v; shown++; }
    });
    var sec = document.getElementById('tariffs');
    if (sec) sec.hidden = shown === 0;
  }

  function setLang(l) {
    if (LANGS.indexOf(l) < 0) return;
    lang = l;
    store('rz_lang', l);
    applyI18n();
    listeners.forEach(function (fn) { fn(l); });
  }

  function initMenu() {
    var btn = document.getElementById('menuBtn');
    var nav = document.getElementById('mobileNav');
    if (!btn || !nav) return;
    function set(open) { nav.classList.toggle('open', open); btn.setAttribute('aria-expanded', String(open)); btn.setAttribute('aria-label', t(open ? 'nav.close' : 'nav.menu')); }
    btn.addEventListener('click', function () { set(!nav.classList.contains('open')); });
    nav.addEventListener('click', function (e) { if (e.target.closest && e.target.closest('a')) set(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && nav.classList.contains('open')) { set(false); btn.focus(); } });
  }

  // Photo slots: <img src="/img/x.svg" data-photo="name">. When photos.name is true in site.config.js, the owner's files
  // /img/photos/name-1x.webp and name.webp (2x) replace the SVG drawing; if they fail to load, the drawing comes back.
  // No inline handlers (CSP).
  function initPhotos() {
    var on = CFG.photos || {};
    document.querySelectorAll('img[data-photo]').forEach(function (img) {
      var name = img.getAttribute('data-photo');
      if (on[name] !== true) return;
      var fallback = img.getAttribute('src');
      var base = '/img/photos/' + name;
      img.addEventListener('error', function () { img.classList.remove('is-photo'); img.removeAttribute('srcset'); img.src = fallback; }, { once: true });
      img.classList.add('is-photo');
      img.srcset = base + '-1x.webp 1x, ' + base + '.webp 2x';
      img.src = base + '.webp';
    });
  }

  window.RZ = {
    t: t,
    cfg: CFG,
    get lang() { return lang; },
    setLang: setLang,
    onLang: function (fn) { listeners.push(fn); },
  };

  function init() {
    lang = pick();
    document.querySelectorAll('[data-lang]').forEach(function (b) { b.addEventListener('click', function () { setLang(b.getAttribute('data-lang')); }); });
    initMenu();
    initPhotos();
    applyI18n();
    document.documentElement.setAttribute('data-ready', '1');
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
