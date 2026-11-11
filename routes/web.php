<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Middleware\AdminAuthenticate;

$assetsDir = '/app/.blueprint/extensions/prmadvanched/assets';
$allowed = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'image/svg+xml' => 'svg',
];
$kinds = [
    'logo' => ['file' => 'logo', 'max' => 2 * 1024 * 1024, 'svg' => true],
    'panel' => ['file' => 'bg-panel', 'max' => 8 * 1024 * 1024, 'svg' => false],
    'auth' => ['file' => 'bg-auth', 'max' => 8 * 1024 * 1024, 'svg' => false],
];

$readSetting = static function (string $record) {
    $row = DB::table('settings')->where('key', 'prmadvanched::' . $record)->first();
    if (!$row) {
        return null;
    }
    try {
        return unserialize($row->value);
    } catch (Throwable $e) {
        return $row->value;
    }
};

$writeSetting = static function (string $record, $value): void {
    DB::table('settings')->updateOrInsert(
        ['key' => 'prmadvanched::' . $record],
        ['value' => serialize($value)]
    );
};

$readAsset = static function (string $kind) use ($assetsDir, $kinds, $readSetting): ?array {
    if (!isset($kinds[$kind])) {
        return null;
    }
    $file = $readSetting($kinds[$kind]['file']);
    if (!is_string($file) || $file === '' || !is_file($assetsDir . '/' . $file)) {
        return null;
    }
    $ver = (string) ($readSetting($kinds[$kind]['file'] . '_v') ?: '0');

    return [
        'file' => $file,
        'url' => '/assets/extensions/prmadvanched/' . rawurlencode($file) . '?v=' . rawurlencode($ver),
    ];
};

$payload = static function () use ($readAsset): array {
    return [
        'logo' => ($readAsset('logo')['url'] ?? null),
        'panel' => ($readAsset('panel')['url'] ?? null),
        'auth' => ($readAsset('auth')['url'] ?? null),
    ];
};

$store = static function (Request $request, string $kind) use ($assetsDir, $allowed, $kinds, $readAsset, $writeSetting, $payload) {
    if (!isset($kinds[$kind])) {
        return response()->json(['message' => 'Geçersiz tür.'], 422);
    }
    $file = $request->file('file') ?: $request->file('logo');
    if (!$file || !$file->isValid()) {
        return response()->json(['message' => 'Dosya seçilmedi.'], 422);
    }
    if ($file->getSize() > $kinds[$kind]['max']) {
        $mb = (int) round($kinds[$kind]['max'] / 1024 / 1024);

        return response()->json(['message' => 'Dosya ' . $mb . ' MB sınırını aşıyor.'], 422);
    }

    $mime = (string) $file->getMimeType();
    if (!isset($allowed[$mime])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $file->getRealPath()) : $mime;
        if ($finfo) {
            finfo_close($finfo);
        }
    }
    if (!isset($allowed[$mime]) || ($mime === 'image/svg+xml' && !$kinds[$kind]['svg'])) {
        return response()->json(['message' => 'PNG, JPG, WEBP veya GIF yükle.'], 422);
    }
    if ($mime === 'image/svg+xml') {
        $raw = (string) file_get_contents($file->getRealPath());
        if (preg_match('/<script|javascript:|on\w+\s*=/i', $raw)) {
            return response()->json(['message' => 'Bu SVG kabul edilmedi.'], 422);
        }
    }
    if (!is_dir($assetsDir) && !mkdir($assetsDir, 0755, true) && !is_dir($assetsDir)) {
        return response()->json(['message' => 'Kayıt klasörü oluşturulamadı.'], 500);
    }

    $existing = $readAsset($kind);
    if ($existing && is_file($assetsDir . '/' . $existing['file'])) {
        @unlink($assetsDir . '/' . $existing['file']);
    }

    $name = $kinds[$kind]['file'] . '.' . $allowed[$mime];
    $file->move($assetsDir, $name);
    $writeSetting($kinds[$kind]['file'], $name);
    $writeSetting($kinds[$kind]['file'] . '_v', (string) time());

    $all = $payload();

    return response()->json(array_merge($all, ['url' => $all[$kind]]));
};

$forget = static function (string $kind) use ($assetsDir, $kinds, $readAsset, $writeSetting, $payload) {
    if (!isset($kinds[$kind])) {
        return response()->json(['message' => 'Geçersiz tür.'], 422);
    }
    $existing = $readAsset($kind);
    if ($existing && is_file($assetsDir . '/' . $existing['file'])) {
        @unlink($assetsDir . '/' . $existing['file']);
    }
    $writeSetting($kinds[$kind]['file'], '');
    $writeSetting($kinds[$kind]['file'] . '_v', (string) time());

    return response()->json(array_merge($payload(), ['url' => null]));
};

Route::get('/logo', function () use ($readAsset) {
    return response()->json(['url' => $readAsset('logo')['url'] ?? null]);
});

Route::get('/assets', function () use ($payload) {
    return response()->json($payload());
});

Route::post('/asset', function (Request $request) use ($store) {
    return $store($request, (string) $request->input('type', ''));
})->middleware(['web', 'auth', AdminAuthenticate::class]);

Route::post('/logo', function (Request $request) use ($store) {
    return $store($request, 'logo');
})->middleware(['web', 'auth', AdminAuthenticate::class]);

Route::delete('/asset', function (Request $request) use ($forget) {
    return $forget((string) $request->query('type', $request->input('type', '')));
})->middleware(['web', 'auth', AdminAuthenticate::class]);

Route::delete('/logo', function () use ($forget) {
    return $forget('logo');
})->middleware(['web', 'auth', AdminAuthenticate::class]);

$loadPrmAdvLib = static function (string $file): void {
    foreach ([
        __DIR__ . '/' . $file,
        __DIR__ . '/../lib/' . $file,
        '/blueprint_extensions/prmadvanched/lib/' . $file,
    ] as $path) {
        if (is_file($path)) {
            require_once $path;

            return;
        }
    }
};
$loadPrmAdvLib('CaptchaMiddleware.php');
$loadPrmAdvLib('SessionTracker.php');

$pushWebMw = static function (string $class): void {
    try {
        $webGroup = app('router')->getMiddlewareGroups()['web'] ?? [];
        if (!in_array($class, $webGroup, true)) {
            app('router')->pushMiddlewareToGroup('web', $class);
        }
    } catch (Throwable $e) {
        //
    }
};
if (class_exists('PrmAdvanchedCaptchaMiddleware', false)) {
    $pushWebMw(PrmAdvanchedCaptchaMiddleware::class);
}
if (class_exists('PrmAdvanchedSessionTracker', false)) {
    $pushWebMw(PrmAdvanchedSessionTracker::class);
}

Route::get('/captcha', function () use ($readSetting) {
    $provider = (string) ($readSetting('captcha_provider') ?: 'off');
    $siteKey = (string) ($readSetting('captcha_site_key') ?: '');
    $enabled = in_array($provider, ['hcaptcha', 'recaptcha_v2', 'turnstile'], true) && $siteKey !== '';

    return response()->json([
        'enabled' => $enabled,
        'provider' => $enabled ? $provider : 'off',
        'siteKey' => $enabled ? $siteKey : '',
    ]);
});

Route::get('/captcha-settings', function () use ($readSetting) {
    $provider = (string) ($readSetting('captcha_provider') ?: 'off');
    $siteKey = (string) ($readSetting('captcha_site_key') ?: '');
    $secret = (string) ($readSetting('captcha_secret') ?: '');

    return response()->json([
        'provider' => $provider ?: 'off',
        'siteKey' => $siteKey,
        'hasSecret' => $secret !== '',
    ]);
})->middleware(['web', 'auth', AdminAuthenticate::class]);

Route::post('/captcha-settings', function (Request $request) use ($writeSetting) {
    $data = $request->validate([
        'provider' => 'required|string|in:off,hcaptcha,recaptcha_v2,turnstile',
        'siteKey' => 'nullable|string|max:200',
        'secret' => 'nullable|string|max:400',
    ]);
    $writeSetting('captcha_provider', $data['provider']);
    $writeSetting('captcha_site_key', trim((string) ($data['siteKey'] ?? '')));
    $secret = trim((string) ($data['secret'] ?? ''));
    if ($secret !== '') {
        $writeSetting('captcha_secret', $secret);
    }
    if ($data['provider'] === 'off') {
        // keep keys so they can switch back
    }

    return response()->json(['ok' => true]);
})->middleware(['web', 'auth', AdminAuthenticate::class]);

Route::get('/sessions', function (Request $request) {
    $userId = (int) Auth::id();
    $current = (string) $request->session()->getId();

    return response()->json([
        'sessions' => PrmAdvanchedSessionTracker::listFor($userId, $current),
    ]);
})->middleware(['web', 'auth']);

Route::delete('/sessions/{id}', function (Request $request, $id) {
    PrmAdvanchedSessionTracker::ensureTable();
    $row = DB::table('prmadvanched_sessions')
        ->where('id', (int) $id)
        ->where('user_id', (int) Auth::id())
        ->first();
    if (!$row) {
        return response()->json(['message' => 'Oturum bulunamadı.'], 404);
    }
    $current = (string) $request->session()->getId();
    if (hash_equals($current, (string) $row->session_id)) {
        return response()->json(['message' => 'Bu cihazdaki oturum kapatılamaz.'], 422);
    }
    PrmAdvanchedSessionTracker::destroySession((string) $row->session_id);
    DB::table('prmadvanched_sessions')->where('id', $row->id)->delete();

    return response()->json(['ok' => true]);
})->middleware(['web', 'auth']);

Route::post('/sessions/revoke-others', function (Request $request) {
    PrmAdvanchedSessionTracker::ensureTable();
    $userId = (int) Auth::id();
    $current = (string) $request->session()->getId();
    $rows = DB::table('prmadvanched_sessions')->where('user_id', $userId)->get();
    foreach ($rows as $row) {
        if (hash_equals($current, (string) $row->session_id)) {
            continue;
        }
        PrmAdvanchedSessionTracker::destroySession((string) $row->session_id);
        DB::table('prmadvanched_sessions')->where('id', $row->id)->delete();
    }

    return response()->json([
        'ok' => true,
        'sessions' => PrmAdvanchedSessionTracker::listFor($userId, $current),
    ]);
})->middleware(['web', 'auth']);
