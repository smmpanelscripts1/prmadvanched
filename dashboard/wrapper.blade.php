@php
  $prmAdvRead = static function (string $record, string $fileName) {
    try {
      $row = \Illuminate\Support\Facades\DB::table('settings')->where('key', 'prmadvanched::' . $record)->first();
      $file = null;
      if ($row) {
        try { $file = unserialize($row->value); } catch (\Throwable $e) { $file = $row->value; }
      }
      $path = '/app/.blueprint/extensions/prmadvanched/assets/' . $file;
      if (!is_string($file) || $file === '' || !is_file($path)) {
        return null;
      }
      $ver = '0';
      $verRow = \Illuminate\Support\Facades\DB::table('settings')->where('key', 'prmadvanched::' . $record . '_v')->first();
      if ($verRow) {
        try { $ver = (string) unserialize($verRow->value); } catch (\Throwable $e) { $ver = (string) $verRow->value; }
      }
      return '/assets/extensions/prmadvanched/' . rawurlencode($file) . '?v=' . rawurlencode($ver);
    } catch (\Throwable $e) {
      return null;
    }
  };
  $prmAdvLogo = $prmAdvRead('logo', 'logo');
  $prmAdvFavicon = $prmAdvRead('favicon', 'favicon');
  $prmAdvPanelBg = $prmAdvRead('bg-panel', 'bg-panel');
  $prmAdvAuthBg = $prmAdvRead('bg-auth', 'bg-auth');
  $prmAdvIsAuth = request()->is('auth') || request()->is('auth/*');
  $prmAdvCapProvider = 'off';
  $prmAdvCapSite = '';
  try {
    $capRow = \Illuminate\Support\Facades\DB::table('settings')->where('key', 'prmadvanched::captcha_provider')->first();
    if ($capRow) {
      try { $prmAdvCapProvider = (string) unserialize($capRow->value); } catch (\Throwable $e) { $prmAdvCapProvider = (string) $capRow->value; }
    }
    $keyRow = \Illuminate\Support\Facades\DB::table('settings')->where('key', 'prmadvanched::captcha_site_key')->first();
    if ($keyRow) {
      try { $prmAdvCapSite = (string) unserialize($keyRow->value); } catch (\Throwable $e) { $prmAdvCapSite = (string) $keyRow->value; }
    }
  } catch (\Throwable $e) {}
  if (!in_array($prmAdvCapProvider, ['hcaptcha', 'recaptcha_v2', 'turnstile'], true) || $prmAdvCapSite === '') {
    $prmAdvCapProvider = 'off';
    $prmAdvCapSite = '';
  }
@endphp
<script>
  document.documentElement.classList.toggle('prm-adv-use-auth', location.pathname.indexOf('/auth') === 0);
  document.documentElement.classList.toggle('prm-adv-use-panel', location.pathname.indexOf('/auth') !== 0);
</script>
@if($prmAdvLogo)
  <style>
    img[src*="pterodactyl.svg"]:not([data-prm-adv-logo]),
    img[data-prm-adv-logo] {
      display: block !important;
      width: 12rem !important;
      height: 12rem !important;
      max-width: 100% !important;
      max-height: 12rem !important;
      object-fit: contain !important;
      object-position: center !important;
    }
    img[src*="pterodactyl.svg"]:not([data-prm-adv-logo]) { opacity: 0 !important; }
    img[data-prm-adv-logo] { opacity: 1 !important; }
    @@media (min-width: 768px) {
      img[src*="pterodactyl.svg"]:not([data-prm-adv-logo]),
      img[data-prm-adv-logo] {
        width: 16rem !important;
        height: 16rem !important;
        max-height: 16rem !important;
      }
    }
  </style>
@endif
@if($prmAdvPanelBg)
  <style>
    html.prm-adv-use-panel,
    html.prm-adv-use-panel body {
      background-color: #181f27 !important;
      background-image: linear-gradient(rgba(24, 31, 39, .58), rgba(24, 31, 39, .72)), url(@json($prmAdvPanelBg)) !important;
      background-size: cover !important;
      background-position: center center !important;
      background-attachment: fixed !important;
      background-repeat: no-repeat !important;
    }
  </style>
@endif
@if($prmAdvAuthBg)
  <style>
    html.prm-adv-use-auth,
    html.prm-adv-use-auth body {
      background-color: #1f2933 !important;
      background-image: linear-gradient(rgba(15, 23, 42, .42), rgba(15, 23, 42, .55)), url(@json($prmAdvAuthBg)) !important;
      background-size: cover !important;
      background-position: center center !important;
      background-attachment: fixed !important;
      background-repeat: no-repeat !important;
    }
  </style>
@endif
<script>
(function () {
  var logoUrl = @json($prmAdvLogo);
  var faviconUrl = @json($prmAdvFavicon);

  function applyFavicon() {
    if (!faviconUrl) return;
    var links = document.querySelectorAll('link[rel="icon"], link[rel="shortcut icon"], link[rel="apple-touch-icon"]');
    if (!links.length && document.head) {
      var link = document.createElement('link');
      link.rel = 'icon';
      link.href = faviconUrl;
      document.head.appendChild(link);
      return;
    }
    for (var i = 0; i < links.length; i++) {
      if (links[i].href !== faviconUrl) links[i].href = faviconUrl;
    }
  }

  function applyLogo() {
    if (!logoUrl) return;
    var imgs = document.querySelectorAll('img');
    for (var i = 0; i < imgs.length; i++) {
      var src = imgs[i].getAttribute('src') || '';
      if (src.indexOf('pterodactyl.svg') === -1 && !imgs[i].hasAttribute('data-prm-adv-logo')) continue;
      if (imgs[i].getAttribute('data-prm-adv-logo') === logoUrl) continue;
      imgs[i].src = logoUrl;
      imgs[i].setAttribute('data-prm-adv-logo', logoUrl);
    }
  }

  function syncBgClass() {
    var auth = location.pathname.indexOf('/auth') === 0;
    document.documentElement.classList.toggle('prm-adv-use-auth', auth);
    document.documentElement.classList.toggle('prm-adv-use-panel', !auth);
  }

  applyLogo();
  applyFavicon();
  syncBgClass();
  var n = 0;
  var fast = setInterval(function () {
    applyLogo();
    applyFavicon();
    syncBgClass();
    if (++n > 80) clearInterval(fast);
  }, 50);
  setInterval(function () {
    applyLogo();
    applyFavicon();
    syncBgClass();
  }, 400);
})();
</script>
@if($prmAdvCapProvider !== 'off')
  <style>
    #prm-adv-captcha-host {
      position: fixed;
      z-index: 2147483000;
      width: 300px;
      pointer-events: auto;
    }
    #prm-adv-captcha-spacer { height: 72px; margin: 8px 0 10px; width: 100%; }
    #prm-adv-captcha { min-height: 65px; }
    #prm-adv-captcha-error { display: none; margin: 8px 0 0; color: #dd4b39; font-size: 12px; font-weight: 600; }
    #prm-adv-captcha-error.is-open { display: block; }
    html.prm-adv-use-panel #prm-adv-captcha-host { display: none; }
  </style>
  <script>
  (function () {
    var provider = @json($prmAdvCapProvider);
    var siteKey = @json($prmAdvCapSite);
    var token = '';
    var widgetId = null;
    var lastPath = '';
    var scriptState = 'idle';

    function onAuth() {
      return location.pathname.indexOf('/auth') === 0;
    }

    function submitBtn() {
      return document.querySelector('form button[type="submit"]') || document.querySelector('form button');
    }

    function showError(text) {
      var el = document.getElementById('prm-adv-captcha-error');
      if (!el) return;
      el.textContent = text || '';
      el.classList.toggle('is-open', !!text);
    }

    function ensureSpacer() {
      var btn = submitBtn();
      if (!btn || !btn.parentNode) return null;
      var spacer = document.getElementById('prm-adv-captcha-spacer');
      if (!spacer || !btn.parentNode.contains(spacer)) {
        spacer = document.createElement('div');
        spacer.id = 'prm-adv-captcha-spacer';
        btn.parentNode.insertBefore(spacer, btn);
      }
      return spacer;
    }

    function placeHost(host) {
      var spacer = ensureSpacer();
      if (!spacer) {
        host.style.display = 'none';
        return;
      }
      var r = spacer.getBoundingClientRect();
      host.style.display = 'block';
      host.style.left = r.left + 'px';
      host.style.top = r.top + 'px';
      host.style.width = Math.max(300, r.width) + 'px';
    }

    function ensureBox() {
      var host = document.getElementById('prm-adv-captcha-host');
      if (!host) {
        host = document.createElement('div');
        host.id = 'prm-adv-captcha-host';
        var box = document.createElement('div');
        box.id = 'prm-adv-captcha';
        var err = document.createElement('div');
        err.id = 'prm-adv-captcha-error';
        host.appendChild(box);
        host.appendChild(err);
        document.body.appendChild(host);
      }
      placeHost(host);
      return document.getElementById('prm-adv-captcha');
    }

    function loadScript(src, cb) {
      if (scriptState === 'ready') { cb(); return; }
      if (scriptState === 'loading') return;
      scriptState = 'loading';
      var s = document.createElement('script');
      s.src = src;
      s.async = true;
      s.onload = function () { scriptState = 'ready'; cb(); };
      s.onerror = function () {
        scriptState = 'idle';
        ensureBox();
        showError('Captcha script yüklenemedi (Cloudflare/Google engeli).');
      };
      document.head.appendChild(s);
    }

    function apiReady() {
      if (provider === 'recaptcha_v2') return window.grecaptcha && grecaptcha.render;
      if (provider === 'hcaptcha') return window.hcaptcha && hcaptcha.render;
      if (provider === 'turnstile') return window.turnstile && turnstile.render;
      return false;
    }

    function renderWidget() {
      if (!onAuth()) return;
      if (!apiReady()) return;
      var box = ensureBox();
      if (!box || box.getAttribute('data-ready') === '1') return;
      if (provider === 'turnstile' && window.turnstile && turnstile.ready && !window.__prmAdvTsReady) {
        window.__prmAdvTsReady = true;
        turnstile.ready(function () { box.removeAttribute('data-ready'); renderWidget(); });
        return;
      }
      box.setAttribute('data-ready', '1');
      token = '';
      var done = function (value) {
        token = value || '';
        showError('');
      };
      try {
        if (provider === 'recaptcha_v2') {
          widgetId = grecaptcha.render(box, { sitekey: siteKey, theme: 'light', callback: done, 'expired-callback': function () { token = ''; } });
        } else if (provider === 'hcaptcha') {
          widgetId = hcaptcha.render(box, { sitekey: siteKey, theme: 'light', callback: done, 'expired-callback': function () { token = ''; } });
        } else if (provider === 'turnstile') {
          widgetId = turnstile.render(box, {
            sitekey: siteKey,
            theme: 'light',
            appearance: 'always',
            callback: done,
            'expired-callback': function () { token = ''; },
            'error-callback': function () {
              box.removeAttribute('data-ready');
              box.innerHTML = '';
              showError('Turnstile bu hostta açılamadı. Gerçek site key kullan.');
            }
          });
        }
      } catch (e) {
        box.removeAttribute('data-ready');
        box.innerHTML = '';
        showError('Captcha çizilemedi.');
      }
    }

    function bootWidget() {
      if (!onAuth()) {
        var host = document.getElementById('prm-adv-captcha-host');
        if (host) host.style.display = 'none';
        return;
      }
      ensureBox();
      var after = function () { renderWidget(); };
      if (provider === 'recaptcha_v2') {
        loadScript('https://www.google.com/recaptcha/api.js?render=explicit', after);
      } else if (provider === 'hcaptcha') {
        loadScript('https://js.hcaptcha.com/1/api.js?render=explicit', after);
      } else if (provider === 'turnstile') {
        loadScript('https://challenges.cloudflare.com/turnstile/v0/api.js', after);
      }
    }

    function authUrl(url) {
      return /\/auth\/(login|password)(\/|$)/.test(String(url || ''));
    }

    function injectToken(body) {
      if (!token) return body;
      try {
        var data = JSON.parse(body);
        data['g-recaptcha-response'] = token;
        data['h-captcha-response'] = token;
        data['cf-turnstile-response'] = token;
        data['prm-adv-captcha'] = token;
        return JSON.stringify(data);
      } catch (e) {
        return body;
      }
    }

    function disableNativeRecaptcha() {
      try {
        if (window.SiteConfiguration && window.SiteConfiguration.recaptcha) {
          window.SiteConfiguration.recaptcha.enabled = false;
        }
      } catch (e) {}
    }
    disableNativeRecaptcha();
    setInterval(disableNativeRecaptcha, 250);
    window.addEventListener('scroll', function () {
      var host = document.getElementById('prm-adv-captcha-host');
      if (host && onAuth()) placeHost(host);
    }, true);
    window.addEventListener('resize', function () {
      var host = document.getElementById('prm-adv-captcha-host');
      if (host && onAuth()) placeHost(host);
    });

    var xhrOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method, url) {
      this.__prmAdvUrl = String(url || '');
      return xhrOpen.apply(this, arguments);
    };
    var xhrSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function (body) {
      if (authUrl(this.__prmAdvUrl) && typeof body === 'string') {
        body = injectToken(body);
      }
      return xhrSend.call(this, body);
    };
    if (window.fetch) {
      var origFetch = window.fetch;
      window.fetch = function (input, init) {
        var url = typeof input === 'string' ? input : (input && input.url) || '';
        init = init || {};
        if (authUrl(url) && typeof init.body === 'string') {
          init = Object.assign({}, init, { body: injectToken(init.body) });
        }
        return origFetch.call(this, input, init);
      };
    }

    document.addEventListener('click', function (e) {
      if (!onAuth()) return;
      var btn = e.target.closest && e.target.closest('form button');
      if (!btn) return;
      if (token) {
        showError('');
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      ensureBox();
      showError(apiReady() ? 'Lütfen captcha doğrulamasını tamamla.' : 'Captcha henüz yüklenmedi, biraz bekle.');
    }, true);

    setInterval(function () {
      if (!onAuth()) return;
      if (location.pathname !== lastPath) {
        lastPath = location.pathname;
        token = '';
        widgetId = null;
        var old = document.getElementById('prm-adv-captcha');
        if (old) {
          old.removeAttribute('data-ready');
          old.innerHTML = '';
        }
      }
      bootWidget();
    }, 400);
    bootWidget();
  })();
  </script>
@endif
<style>
  #prm-adv-sessions { margin: 8px 0 24px; }
  #prm-adv-sessions h2 { color: #d3d6db; font-size: 1.5rem; font-weight: 600; margin: 0 0 1rem; padding: 0 1rem; }
  #prm-adv-sessions-card { background: #334155; border-radius: 4px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.3); padding: 16px; }
  #prm-adv-sessions-card p { margin: 0 0 12px; color: #9aa5b1; font-size: 13px; }
  .prm-adv-session { display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap; padding: 12px 0; border-top: 1px solid rgba(255,255,255,.08); }
  .prm-adv-session:first-of-type { border-top: 0; padding-top: 0; }
  .prm-adv-session strong { display: block; color: #ecf0f5; font-size: 14px; }
  .prm-adv-session span { color: #9aa5b1; font-size: 12px; }
  .prm-adv-session-badge { display: inline-block; margin-left: 8px; padding: 1px 8px; border-radius: 999px; background: #099aa5; color: #fff; font-size: 11px; font-weight: 600; }
  .prm-adv-session button, #prm-adv-session-revoke-others {
    background: #dd4b39; color: #fff; border: 0; border-radius: 4px; padding: 6px 12px; font-size: 12px; font-weight: 600; cursor: pointer;
  }
  .prm-adv-session button[disabled] { opacity: .45; cursor: default; background: #4b5563; }
  #prm-adv-session-revoke-others { margin-top: 12px; background: #1f2933; border: 1px solid #dd4b39; }
  #prm-adv-sessions-msg { margin-top: 10px; font-size: 13px; color: #00a65a; display: none; }
  #prm-adv-sessions-msg.is-fail { display: block; color: #dd4b39; }
  #prm-adv-sessions-msg.is-ok { display: block; }
</style>
<script>
(function () {
  var cache = [];
  function onAccount() {
    return location.pathname.replace(/\/+$/, '') === '/account';
  }
  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"], meta[name="_token"]');
    if (m && m.getAttribute('content')) return m.getAttribute('content');
    var match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
  }
  function fmt(iso) {
    if (!iso) return '';
    try {
      return new Date(iso).toLocaleString();
    } catch (e) {
      return iso;
    }
  }
  function findGrid() {
    var hs = document.querySelectorAll('h2');
    var two = null;
    var pass = null;
    for (var i = 0; i < hs.length; i++) {
      var t = (hs[i].textContent || '').trim();
      if (t === 'Two-Step Verification') two = hs[i];
      if (t === 'Update Password') pass = hs[i];
    }
    if (!two || !pass) return null;
    return two.parentNode && two.parentNode.parentNode ? two.parentNode.parentNode : null;
  }
  function render(items) {
    cache = items || [];
    var list = document.getElementById('prm-adv-session-list');
    if (!list) return;
    if (!cache.length) {
      list.innerHTML = '<div class="prm-adv-session"><div><strong>Kayıtlı oturum yok.</strong></div></div>';
      return;
    }
    list.innerHTML = cache.map(function (s) {
      return '<div class="prm-adv-session" data-id="' + s.id + '">' +
        '<div><strong>' + (s.device || 'Oturum') +
          (s.current ? '<span class="prm-adv-session-badge">Bu cihaz</span>' : '') +
        '</strong><span>' + (s.ip || '') + ' · son görülme ' + fmt(s.lastSeen) + '</span></div>' +
        '<button type="button" data-revoke="' + s.id + '"' + (s.current ? ' disabled' : '') + '>' +
          (s.current ? 'Aktif' : 'Kapat') +
        '</button></div>';
    }).join('');
  }
  function headers(json) {
    var h = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(), 'X-XSRF-TOKEN': csrf() };
    if (json) h['Content-Type'] = 'application/json';
    return h;
  }
  function msg(text, ok) {
    var el = document.getElementById('prm-adv-sessions-msg');
    if (!el) return;
    el.textContent = text || '';
    el.className = text ? ('is-' + (ok ? 'ok' : 'fail')) : '';
    el.id = 'prm-adv-sessions-msg';
  }
  function load() {
    fetch('/extensions/prmadvanched/sessions', { credentials: 'same-origin', headers: headers(false) })
      .then(function (r) { return r.json(); })
      .then(function (data) { render(data.sessions || []); })
      .catch(function () { msg('Oturumlar yüklenemedi.', false); });
  }
  function revoke(id) {
    fetch('/extensions/prmadvanched/sessions/' + id, { method: 'DELETE', credentials: 'same-origin', headers: headers(false) })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
      .then(function (res) {
        if (!res.ok) { msg((res.body && res.body.message) || 'Kapatılamadı.', false); return; }
        msg('Oturum kapatıldı.', true);
        load();
      })
      .catch(function () { msg('Kapatılamadı.', false); });
  }
  function revokeOthers() {
    if (!confirm('Bu cihaz hariç bütün oturumlar kapatılacak. Devam?')) return;
    fetch('/extensions/prmadvanched/sessions/revoke-others', { method: 'POST', credentials: 'same-origin', headers: headers(true), body: '{}' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        render(data.sessions || []);
        msg('Diğer oturumlar kapatıldı.', true);
      })
      .catch(function () { msg('Kapatılamadı.', false); });
  }
  function mount() {
    if (!onAccount()) {
      var stale = document.getElementById('prm-adv-sessions');
      if (stale) stale.remove();
      return;
    }
    if (document.getElementById('prm-adv-sessions')) return;
    var grid = findGrid();
    if (!grid || !grid.parentNode) return;
    var box = document.createElement('div');
    box.id = 'prm-adv-sessions';
    box.innerHTML =
      '<h2>Açık oturumlar</h2>' +
      '<div id="prm-adv-sessions-card">' +
        '<p>Tanımadığın bir oturumu buradan kapatabilirsin. Bu cihazdaki oturum açık kalır.</p>' +
        '<div id="prm-adv-session-list"></div>' +
        '<button type="button" id="prm-adv-session-revoke-others">Diğer oturumları kapat</button>' +
        '<div id="prm-adv-sessions-msg"></div>' +
      '</div>';
    grid.parentNode.insertBefore(box, grid.nextSibling);
    box.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-revoke]');
      if (btn && !btn.disabled) revoke(btn.getAttribute('data-revoke'));
      if (e.target.id === 'prm-adv-session-revoke-others') revokeOthers();
    });
    if (cache.length) render(cache);
    load();
  }
  setInterval(mount, 400);
  mount();
})();
</script>
