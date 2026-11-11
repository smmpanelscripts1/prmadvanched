@php
  $isSettings = request()->is('admin/settings') && !request()->is('admin/settings/*');
@endphp

@if($isSettings)
  <style>
    .prm-adv-box { margin-top: 18px; }
    .prm-adv-slot { padding: 14px 0; border-top: 1px solid #2c3b4c; }
    .prm-adv-slot:first-of-type { border-top: 0; padding-top: 0; }
    .prm-adv-row { display: flex; gap: 18px; align-items: flex-start; flex-wrap: wrap; }
    .prm-adv-preview {
      width: 180px; height: 110px; flex: 0 0 180px;
      display: flex; align-items: center; justify-content: center;
      background: #111827; border: 1px solid #3c4b5c; border-radius: 6px; overflow: hidden;
      background-size: cover; background-position: center;
    }
    .prm-adv-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .prm-adv-preview span { color: #9aa5b1; font-size: 12px; padding: 8px; text-align: center; }
    .prm-adv-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
    .prm-adv-hint { margin: 10px 0 0; color: #9aa5b1; font-size: 12px; }
    .prm-adv-msg { margin-top: 8px; font-size: 13px; display: none; }
    .prm-adv-msg.is-ok { display: block; color: #00a65a; }
    .prm-adv-msg.is-fail { display: block; color: #dd4b39; }
    .prm-adv-slot h4 { margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #ecf0f5; }
    .prm-adv-field { margin-bottom: 10px; }
    .prm-adv-field label { display: block; font-size: 11px; font-weight: 700; color: #9aa5b1; margin-bottom: 5px; text-transform: uppercase; letter-spacing: .04em; }
    .prm-adv-field input, .prm-adv-field select { width: 100%; max-width: 420px; height: 36px; border: 1px solid #3c4b5c; border-radius: 4px; background: #111827; color: #ecf0f5; padding: 0 10px; }
  </style>
  <script>
  (function () {
    var path = location.pathname.replace(/\/+$/, '');
    if (path !== '/admin/settings') return;
    var slots = [
      { id: 'logo', title: 'Panel logo', hint: 'Login, şifre sıfırlama ve 2FA sol görseli. PNG/JPG/WEBP/GIF/SVG, 2 MB.', empty: 'Varsayılan pterodactyl görseli', accept: 'image/png,image/jpeg,image/webp,image/gif,image/svg+xml', cover: false },
      { id: 'panel', title: 'Kullanıcı paneli arka planı', hint: 'Dashboard ve sunucu sayfalarının arka planı. PNG/JPG/WEBP/GIF, 8 MB.', empty: 'Varsayılan koyu arka plan', accept: 'image/png,image/jpeg,image/webp,image/gif', cover: true },
      { id: 'auth', title: 'Giriş arka planı', hint: 'Login, kayıt, şifre sıfırlama, yeni şifre ve 2FA. PNG/JPG/WEBP/GIF, 8 MB.', empty: 'Varsayılan giriş arka planı', accept: 'image/png,image/jpeg,image/webp,image/gif', cover: true }
    ];

    function csrf() {
      var m = document.querySelector('meta[name="csrf-token"], meta[name="_token"]');
      return m ? m.getAttribute('content') : '';
    }

    function inject() {
      if (document.getElementById('prm-adv-box')) return true;
      var host = document.querySelector('.content .box, section.content .box');
      if (!host || !host.parentNode) return false;

      var html = '<div class="box-header with-border"><h3 class="box-title">Görünüm</h3></div><div class="box-body">';
      slots.forEach(function (s) {
        html +=
          '<div class="prm-adv-slot">' +
            '<h4>' + s.title + '</h4>' +
            '<div class="prm-adv-row">' +
              '<div class="prm-adv-preview" id="prm-adv-preview-' + s.id + '"><span>' + s.empty + '</span></div>' +
              '<div>' +
                '<input type="file" id="prm-adv-file-' + s.id + '" accept="' + s.accept + '">' +
                '<div class="prm-adv-actions">' +
                  '<button type="button" class="btn btn-sm btn-primary" data-adv-save="' + s.id + '">Yükle</button>' +
                  '<button type="button" class="btn btn-sm btn-default" data-adv-clear="' + s.id + '">Kaldır</button>' +
                '</div>' +
                '<p class="prm-adv-hint">' + s.hint + '</p>' +
                '<div class="prm-adv-msg" id="prm-adv-msg-' + s.id + '"></div>' +
              '</div>' +
            '</div>' +
          '</div>';
      });
      html += '</div>';

      var box = document.createElement('div');
      box.id = 'prm-adv-box';
      box.className = 'box prm-adv-box';
      box.innerHTML = html;
      host.parentNode.insertBefore(box, host.nextSibling);

      if (!document.getElementById('prm-adv-captcha-box')) {
        var cap = document.createElement('div');
        cap.id = 'prm-adv-captcha-box';
        cap.className = 'box prm-adv-box';
        cap.innerHTML =
          '<div class="box-header with-border"><h3 class="box-title">Captcha</h3></div>' +
          '<div class="box-body">' +
            '<p>Login, şifre sıfırlama, yeni şifre ve 2FA formlarında gösterilir. Panelin kendi reCAPTCHA ayarını kapalı tut.</p>' +
            '<div class="prm-adv-field"><label>Sağlayıcı</label>' +
              '<select id="prm-adv-cap-provider">' +
                '<option value="off">Kapalı</option>' +
                '<option value="hcaptcha">hCaptcha</option>' +
                '<option value="recaptcha_v2">reCAPTCHA v2</option>' +
                '<option value="turnstile">Cloudflare Turnstile</option>' +
              '</select></div>' +
            '<div class="prm-adv-field"><label>Site key</label><input type="text" id="prm-adv-cap-site" autocomplete="off"></div>' +
            '<div class="prm-adv-field"><label>Secret key</label><input type="password" id="prm-adv-cap-secret" placeholder="Kayıtlıysa değiştirmemek için boş bırak" autocomplete="new-password"></div>' +
            '<div class="prm-adv-actions"><button type="button" class="btn btn-sm btn-primary" id="prm-adv-cap-save">Kaydet</button></div>' +
            '<div class="prm-adv-msg" id="prm-adv-msg-captcha"></div>' +
          '</div>';
        box.parentNode.insertBefore(cap, box.nextSibling);
      }
      if (!document.getElementById('prm-adv-fw-box')) {
        var fw = document.createElement('div');
        fw.id = 'prm-adv-fw-box';
        fw.className = 'box prm-adv-box';
        fw.innerHTML =
          '<div class="box-header with-border"><h3 class="box-title">IP firewall</h3></div>' +
          '<div class="box-body">' +
            '<p>Ban herkese uygulanır. Panel whitelist sadece tarayıcı/login içindir. API whitelist ayrıdır (otomasyon). Wings <code>/api/remote</code> muaf.</p>' +
            '<p class="prm-adv-hint" id="prm-adv-fw-me">Senin IP: —</p>' +
            '<div class="prm-adv-field"><label>Durum</label>' +
              '<select id="prm-adv-fw-enabled"><option value="1">Açık</option><option value="0">Kapalı</option></select></div>' +
            '<div class="prm-adv-field"><label>Başarısız girişte otomatik ban</label>' +
              '<select id="prm-adv-fw-auto"><option value="0">Kapalı</option><option value="1">Açık</option></select></div>' +
            '<div class="prm-adv-field"><label>Kaç başarısız girişten sonra</label>' +
              '<input type="number" id="prm-adv-fw-strikes" min="2" max="50" value="5"></div>' +
            '<div class="prm-adv-field"><label>Panel whitelist (sadece listedekiler panele girer)</label>' +
              '<select id="prm-adv-fw-panel-wl"><option value="0">Kapalı</option><option value="1">Açık</option></select></div>' +
            '<div class="prm-adv-field"><label>API whitelist (sadece listedekiler client/application API kullanır)</label>' +
              '<select id="prm-adv-fw-api-wl"><option value="0">Kapalı</option><option value="1">Açık</option></select></div>' +
            '<div class="prm-adv-actions"><button type="button" class="btn btn-sm btn-primary" id="prm-adv-fw-save">Ayarları kaydet</button></div>' +
            '<div class="prm-adv-msg" id="prm-adv-msg-fwset"></div>' +
            '<h4 style="margin-top:18px">Ban listesi</h4>' +
            '<div class="prm-adv-field"><label>IP veya CIDR</label>' +
              '<input type="text" id="prm-adv-fw-ip" placeholder="203.0.113.10 veya 203.0.113.0/24" autocomplete="off"></div>' +
            '<div class="prm-adv-field"><label>Not (opsiyonel)</label>' +
              '<input type="text" id="prm-adv-fw-note" placeholder="ör. brute force" autocomplete="off"></div>' +
            '<div class="prm-adv-actions"><button type="button" class="btn btn-sm btn-danger" id="prm-adv-fw-add">Banla</button></div>' +
            '<div class="prm-adv-msg" id="prm-adv-msg-fwadd"></div>' +
            '<div id="prm-adv-fw-list" style="margin-top:12px"></div>' +
            '<h4 style="margin-top:18px">Panel allow</h4>' +
            '<p class="prm-adv-hint">Açınca listedeki IP dışında login/panel 403. Senin IP otomatik eklenir.</p>' +
            '<div class="prm-adv-field"><label>IP veya CIDR</label>' +
              '<input type="text" id="prm-adv-fw-panel-ip" placeholder="203.0.113.10" autocomplete="off"></div>' +
            '<div class="prm-adv-field"><label>Not</label>' +
              '<input type="text" id="prm-adv-fw-panel-note" placeholder="ev, ofis" autocomplete="off"></div>' +
            '<div class="prm-adv-actions"><button type="button" class="btn btn-sm btn-primary" id="prm-adv-fw-panel-add">Panele ekle</button></div>' +
            '<div class="prm-adv-msg" id="prm-adv-msg-fwpanel"></div>' +
            '<div id="prm-adv-fw-panel-list" style="margin-top:12px"></div>' +
            '<h4 style="margin-top:18px">API allow</h4>' +
            '<p class="prm-adv-hint">Otomasyon / bot IP. Panel whitelist’ten bağımsız. Boşken açılamaz.</p>' +
            '<div class="prm-adv-field"><label>IP veya CIDR</label>' +
              '<input type="text" id="prm-adv-fw-api-ip" placeholder="198.51.100.20" autocomplete="off"></div>' +
            '<div class="prm-adv-field"><label>Not</label>' +
              '<input type="text" id="prm-adv-fw-api-note" placeholder="smm bot, n8n" autocomplete="off"></div>' +
            '<div class="prm-adv-actions"><button type="button" class="btn btn-sm btn-primary" id="prm-adv-fw-api-add">API’ye ekle</button></div>' +
            '<div class="prm-adv-msg" id="prm-adv-msg-fwapi"></div>' +
            '<div id="prm-adv-fw-api-list" style="margin-top:12px"></div>' +
          '</div>';
        var after = document.getElementById('prm-adv-captcha-box') || box;
        after.parentNode.insertBefore(fw, after.nextSibling);
      }
      return true;
    }

    function msg(id, text, ok) {
      var el = document.getElementById('prm-adv-msg-' + id);
      if (!el) return;
      el.textContent = text || '';
      el.className = 'prm-adv-msg' + (text ? (ok ? ' is-ok' : ' is-fail') : '');
    }

    function preview(id, url) {
      var box = document.getElementById('prm-adv-preview-' + id);
      var slot = slots.filter(function (s) { return s.id === id; })[0];
      if (!box) return;
      box.style.backgroundImage = '';
      if (!url) {
        box.innerHTML = '<span>' + (slot ? slot.empty : '') + '</span>';
        return;
      }
      if (slot && slot.cover) {
        box.innerHTML = '';
        box.style.backgroundImage = 'url("' + url + '")';
        return;
      }
      box.innerHTML = '<img alt="" src="' + url + '">';
    }

    function load() {
      fetch('/extensions/prmadvanched/assets', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data) return;
          slots.forEach(function (s) { preview(s.id, data[s.id]); });
        })
        .catch(function () {});
    }

    function upload(id) {
      var input = document.getElementById('prm-adv-file-' + id);
      if (!input || !input.files || !input.files[0]) {
        msg(id, 'Bir dosya seç.', false);
        return;
      }
      var body = new FormData();
      body.append('type', id);
      body.append('file', input.files[0]);
      fetch('/extensions/prmadvanched/asset', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': csrf(),
          'X-XSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        },
        body: body
      }).then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, body: j }; });
      }).then(function (res) {
        if (!res.ok) {
          msg(id, (res.body && res.body.message) || 'Yükleme başarısız.', false);
          return;
        }
        preview(id, res.body.url);
        msg(id, 'Kaydedildi.', true);
      }).catch(function () { msg(id, 'Yükleme başarısız.', false); });
    }

    function clearAsset(id) {
      fetch('/extensions/prmadvanched/asset?type=' + encodeURIComponent(id), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': csrf(),
          'X-XSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        }
      }).then(function (r) { return r.ok ? r.json() : null; })
        .then(function () {
          var input = document.getElementById('prm-adv-file-' + id);
          if (input) input.value = '';
          preview(id, null);
          msg(id, 'Varsayılan geri yüklendi.', true);
        }).catch(function () { msg(id, 'Kaldırılamadı.', false); });
    }

    function loadCaptcha() {
      fetch('/extensions/prmadvanched/captcha-settings', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data) return;
          var sel = document.getElementById('prm-adv-cap-provider');
          var site = document.getElementById('prm-adv-cap-site');
          var secret = document.getElementById('prm-adv-cap-secret');
          if (sel) sel.value = data.provider || 'off';
          if (site) site.value = data.siteKey || '';
          if (secret) secret.placeholder = data.hasSecret ? 'Kayıtlı (değiştirmek için yaz)' : 'Secret key';
        })
        .catch(function () {});
    }

    function saveCaptcha() {
      var sel = document.getElementById('prm-adv-cap-provider');
      var site = document.getElementById('prm-adv-cap-site');
      var secret = document.getElementById('prm-adv-cap-secret');
      fetch('/extensions/prmadvanched/captcha-settings', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf(),
          'X-XSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        },
        body: JSON.stringify({
          provider: sel ? sel.value : 'off',
          siteKey: site ? site.value : '',
          secret: secret ? secret.value : ''
        })
      }).then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, body: j }; });
      }).then(function (res) {
        if (!res.ok) {
          msg('captcha', (res.body && res.body.message) || 'Kaydedilemedi.', false);
          return;
        }
        if (secret) secret.value = '';
        msg('captcha', 'Captcha ayarları kaydedildi.', true);
      }).catch(function () { msg('captcha', 'Kaydedilemedi.', false); });
    }

    function renderIpTable(listId, rows, emptyText, delAttr) {
      var list = document.getElementById(listId);
      if (!list) return;
      if (!rows || !rows.length) {
        list.innerHTML = '<p class="prm-adv-hint">' + emptyText + '</p>';
        return;
      }
      var html = '<table class="table table-condensed" style="margin:0"><thead><tr><th>IP / CIDR</th><th>Not</th><th></th></tr></thead><tbody>';
      rows.forEach(function (row) {
        var note = (row.note || '') + (row.source === 'auto' ? ' (otomatik)' : '');
        html += '<tr><td><code>' + String(row.ip || '').replace(/</g, '') + '</code></td><td>' + String(note).replace(/</g, '') + '</td>' +
          '<td><button type="button" class="btn btn-xs btn-default" ' + delAttr + '="' + String(row.ip || '').replace(/"/g, '') + '">Kaldır</button></td></tr>';
      });
      html += '</tbody></table>';
      list.innerHTML = html;
    }

    function renderBans(bans) {
      renderIpTable('prm-adv-fw-list', bans, 'Banlı IP yok.', 'data-fw-del');
      var list = document.getElementById('prm-adv-fw-list');
      if (!list) return;
      list.querySelectorAll('[data-fw-del]').forEach(function (btn) {
        btn.addEventListener('click', function () { unban(btn.getAttribute('data-fw-del')); });
      });
    }

    function renderAllow(scope, rows) {
      var id = scope === 'api' ? 'prm-adv-fw-api-list' : 'prm-adv-fw-panel-list';
      var attr = scope === 'api' ? 'data-fw-api-del' : 'data-fw-panel-del';
      renderIpTable(id, rows, 'Allow listesi boş.', attr);
      var list = document.getElementById(id);
      if (!list) return;
      list.querySelectorAll('[' + attr + ']').forEach(function (btn) {
        btn.addEventListener('click', function () { removeAllow(scope, btn.getAttribute(attr)); });
      });
    }

    function loadFirewall() {
      fetch('/extensions/prmadvanched/firewall', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data) return;
          var me = document.getElementById('prm-adv-fw-me');
          if (me) me.textContent = 'Senin IP: ' + (data.currentIp || '—') + ' — panel whitelist açılınca bu IP allow’a yazılır.';
          var en = document.getElementById('prm-adv-fw-enabled');
          var au = document.getElementById('prm-adv-fw-auto');
          var st = document.getElementById('prm-adv-fw-strikes');
          var pw = document.getElementById('prm-adv-fw-panel-wl');
          var aw = document.getElementById('prm-adv-fw-api-wl');
          if (en) en.value = data.enabled ? '1' : '0';
          if (au) au.value = data.autoban ? '1' : '0';
          if (st) st.value = data.strikesMax || 5;
          if (pw) pw.value = data.panelWhitelist ? '1' : '0';
          if (aw) aw.value = data.apiWhitelist ? '1' : '0';
          renderBans(data.bans || []);
          renderAllow('panel', data.panelAllow || []);
          renderAllow('api', data.apiAllow || []);
        })
        .catch(function () {});
    }

    function fwHeaders() {
      return {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-XSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json'
      };
    }

    function saveFirewall() {
      var en = document.getElementById('prm-adv-fw-enabled');
      var au = document.getElementById('prm-adv-fw-auto');
      var st = document.getElementById('prm-adv-fw-strikes');
      var pw = document.getElementById('prm-adv-fw-panel-wl');
      var aw = document.getElementById('prm-adv-fw-api-wl');
      fetch('/extensions/prmadvanched/firewall/settings', {
        method: 'POST',
        credentials: 'same-origin',
        headers: fwHeaders(),
        body: JSON.stringify({
          enabled: en ? en.value === '1' : true,
          autoban: au ? au.value === '1' : false,
          strikesMax: st ? parseInt(st.value, 10) || 5 : 5,
          panelWhitelist: pw ? pw.value === '1' : false,
          apiWhitelist: aw ? aw.value === '1' : false
        })
      }).then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, body: j }; });
      }).then(function (res) {
        if (!res.ok) {
          msg('fwset', (res.body && res.body.message) || 'Kaydedilemedi.', false);
          return;
        }
        msg('fwset', 'Firewall ayarları kaydedildi.', true);
        if (res.body.panelAllow) renderAllow('panel', res.body.panelAllow);
        if (res.body.apiAllow) renderAllow('api', res.body.apiAllow);
        loadFirewall();
      }).catch(function () { msg('fwset', 'Kaydedilemedi.', false); });
    }

    function addAllow(scope) {
      var ipEl = document.getElementById(scope === 'api' ? 'prm-adv-fw-api-ip' : 'prm-adv-fw-panel-ip');
      var noteEl = document.getElementById(scope === 'api' ? 'prm-adv-fw-api-note' : 'prm-adv-fw-panel-note');
      var msgId = scope === 'api' ? 'fwapi' : 'fwpanel';
      fetch('/extensions/prmadvanched/firewall/allow', {
        method: 'POST',
        credentials: 'same-origin',
        headers: fwHeaders(),
        body: JSON.stringify({
          scope: scope,
          ip: ipEl ? ipEl.value : '',
          note: noteEl ? noteEl.value : ''
        })
      }).then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, body: j }; });
      }).then(function (res) {
        if (!res.ok) {
          msg(msgId, (res.body && res.body.message) || 'Eklenemedi.', false);
          return;
        }
        if (ipEl) ipEl.value = '';
        if (noteEl) noteEl.value = '';
        msg(msgId, 'Allow listesine eklendi.', true);
        renderAllow(scope, res.body.allow || []);
      }).catch(function () { msg(msgId, 'Eklenemedi.', false); });
    }

    function removeAllow(scope, ip) {
      var msgId = scope === 'api' ? 'fwapi' : 'fwpanel';
      fetch('/extensions/prmadvanched/firewall/allow?scope=' + encodeURIComponent(scope) + '&ip=' + encodeURIComponent(ip), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: fwHeaders()
      }).then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, body: j }; });
      }).then(function (res) {
        if (!res.ok) {
          msg(msgId, (res.body && res.body.message) || 'Kaldırılamadı.', false);
          return;
        }
        msg(msgId, 'Allow’dan çıkarıldı.', true);
        renderAllow(scope, res.body.allow || []);
        loadFirewall();
      }).catch(function () { msg(msgId, 'Kaldırılamadı.', false); });
    }

    function banIp() {
      var ip = document.getElementById('prm-adv-fw-ip');
      var note = document.getElementById('prm-adv-fw-note');
      fetch('/extensions/prmadvanched/firewall/ban', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf(),
          'X-XSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        },
        body: JSON.stringify({
          ip: ip ? ip.value : '',
          note: note ? note.value : ''
        })
      }).then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, body: j }; });
      }).then(function (res) {
        if (!res.ok) {
          msg('fwadd', (res.body && res.body.message) || 'Banlanamadı.', false);
          return;
        }
        if (ip) ip.value = '';
        if (note) note.value = '';
        msg('fwadd', 'IP banlandı.', true);
        renderBans(res.body.bans || []);
      }).catch(function () { msg('fwadd', 'Banlanamadı.', false); });
    }

    function unban(ip) {
      fetch('/extensions/prmadvanched/firewall/ban?ip=' + encodeURIComponent(ip), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': csrf(),
          'X-XSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        }
      }).then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, body: j }; });
      }).then(function (res) {
        if (!res.ok) {
          msg('fwadd', (res.body && res.body.message) || 'Kaldırılamadı.', false);
          return;
        }
        msg('fwadd', 'Ban kaldırıldı.', true);
        renderBans(res.body.bans || []);
      }).catch(function () { msg('fwadd', 'Kaldırılamadı.', false); });
    }

    function boot() {
      if (!inject()) return;
      var capSave = document.getElementById('prm-adv-cap-save');
      if (capSave && !capSave.getAttribute('data-bound')) {
        capSave.setAttribute('data-bound', '1');
        capSave.addEventListener('click', saveCaptcha);
      }
      var fwSave = document.getElementById('prm-adv-fw-save');
      if (fwSave && !fwSave.getAttribute('data-bound')) {
        fwSave.setAttribute('data-bound', '1');
        fwSave.addEventListener('click', saveFirewall);
      }
      var fwAdd = document.getElementById('prm-adv-fw-add');
      if (fwAdd && !fwAdd.getAttribute('data-bound')) {
        fwAdd.setAttribute('data-bound', '1');
        fwAdd.addEventListener('click', banIp);
      }
      var panelAdd = document.getElementById('prm-adv-fw-panel-add');
      if (panelAdd && !panelAdd.getAttribute('data-bound')) {
        panelAdd.setAttribute('data-bound', '1');
        panelAdd.addEventListener('click', function () { addAllow('panel'); });
      }
      var apiAdd = document.getElementById('prm-adv-fw-api-add');
      if (apiAdd && !apiAdd.getAttribute('data-bound')) {
        apiAdd.setAttribute('data-bound', '1');
        apiAdd.addEventListener('click', function () { addAllow('api'); });
      }
      loadCaptcha();
      loadFirewall();
      document.querySelectorAll('[data-adv-save]').forEach(function (btn) {
        if (btn.getAttribute('data-bound')) return;
        btn.setAttribute('data-bound', '1');
        btn.addEventListener('click', function () { upload(btn.getAttribute('data-adv-save')); });
      });
      document.querySelectorAll('[data-adv-clear]').forEach(function (btn) {
        if (btn.getAttribute('data-bound')) return;
        btn.setAttribute('data-bound', '1');
        btn.addEventListener('click', function () { clearAsset(btn.getAttribute('data-adv-clear')); });
      });
      load();
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', boot);
    } else {
      boot();
    }
  })();
  </script>
@endif
