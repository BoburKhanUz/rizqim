// pos.rizqim.uz: reads the public manifest (GET /api/v1/pos/releases) and lists, per app and platform, the latest build.
// No build step and no libraries; every value from the manifest is put in with textContent, never as HTML.
(function () {
  'use strict';

  var APPS = [
    ['cashier', 'Kassa'],
    ['waiter', 'Ofitsiant'],
    ['kds', 'Oshxona ekrani'],
    ['customer_display', 'Mijoz ekrani']
  ];
  var PLATFORMS = [['android', 'Android'], ['windows', 'Windows']];
  var EMPTY = "Hozircha yuklab olish uchun versiya yo'q";
  var MONTHS = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr'];

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
  }

  function sizeText(bytes) {
    var n = Number(bytes) || 0;
    if (n < 1024 * 1024) return Math.max(1, Math.round(n / 1024)) + ' KB';
    return (n / 1024 / 1024).toFixed(1).replace('.', ',') + ' MB';
  }

  function dateText(iso) {
    var d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    return d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear();
  }

  // The latest build of one app on one platform: the row the API marks, else the first one it listed (newest first)
  function latestOf(rows) {
    for (var i = 0; i < rows.length; i++) if (rows[i].is_latest) return rows[i];
    return rows[0] || null;
  }

  function card(platformName, release) {
    var box = el('div', 'card');
    box.appendChild(el('h3', '', platformName));
    if (!release) {
      box.appendChild(el('p', 'none', EMPTY));
      return box;
    }
    var version = release.version + (release.build ? '+' + release.build : '');
    var line = el('p', 'meta');
    line.appendChild(el('span', 'version', 'Versiya ' + version));
    var date = dateText(release.released_at);
    if (date) line.appendChild(document.createTextNode(' · ' + date));
    line.appendChild(document.createTextNode(' · ' + sizeText(release.size_bytes)));
    box.appendChild(line);
    if (release.notes) box.appendChild(el('p', 'notes', release.notes));
    box.appendChild(el('p', 'sha', 'SHA-256: ' + release.sha256));
    // Only https links become links
    if (typeof release.file_url === 'string' && release.file_url.indexOf('https://') === 0) {
      var link = el('a', 'download', 'Yuklab olish (' + sizeText(release.size_bytes) + ')');
      link.href = release.file_url;
      link.setAttribute('download', '');
      link.rel = 'noopener';
      box.appendChild(link);
    }
    return box;
  }

  function render(releases) {
    var status = document.getElementById('status');
    var root = document.getElementById('apps');
    root.textContent = '';
    if (!releases.length) {
      status.textContent = EMPTY;
      return;
    }
    status.textContent = '';
    APPS.forEach(function (app) {
      var rows = releases.filter(function (r) { return r.app === app[0]; });
      if (!rows.length) return;
      var section = el('section', 'app');
      section.appendChild(el('h2', '', app[1]));
      var grid = el('div', 'grid');
      PLATFORMS.forEach(function (platform) {
        var mine = rows.filter(function (r) { return r.platform === platform[0]; });
        if (mine.length) grid.appendChild(card(platform[1], latestOf(mine)));
      });
      section.appendChild(grid);
      root.appendChild(section);
    });
  }

  function failed() {
    document.getElementById('status').textContent = "Versiyalar ro'yxatini yuklab bo'lmadi. Birozdan keyin sahifani qayta oching.";
  }

  fetch('/api/v1/pos/releases', { headers: { Accept: 'application/json' } })
    .then(function (response) {
      if (!response.ok) throw new Error('http ' + response.status);
      return response.json();
    })
    .then(function (body) {
      var list = body && body.data && Array.isArray(body.data.releases) ? body.data.releases : null;
      if (!list) throw new Error('shape');
      render(list);
    })
    .catch(failed);
})();
