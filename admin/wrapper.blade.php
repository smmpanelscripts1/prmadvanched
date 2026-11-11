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

    function boot() {
      if (!inject()) return;
      var capSave = document.getElementById('prm-adv-cap-save');
      if (capSave && !capSave.getAttribute('data-bound')) {
        capSave.setAttribute('data-bound', '1');
        capSave.addEventListener('click', saveCaptcha);
      }
      loadCaptcha();
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
