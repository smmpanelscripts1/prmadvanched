<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

if (class_exists('PrmAdvanchedCaptchaMiddleware', false)) {
    return;
}

class PrmAdvanchedCaptchaMiddleware
{
    private static array $cache = [];

    public static function setting(string $record, $default = null)
    {
        if (array_key_exists($record, self::$cache)) {
            return self::$cache[$record] === null ? $default : self::$cache[$record];
        }
        $row = DB::table('settings')->where('key', 'prmadvanched::' . $record)->first();
        if (!$row) {
            self::$cache[$record] = null;

            return $default;
        }
        try {
            $value = unserialize($row->value);
        } catch (Throwable $e) {
            $value = $row->value;
        }
        self::$cache[$record] = ($value === null || $value === '') ? null : $value;

        return self::$cache[$record] === null ? $default : self::$cache[$record];
    }

    public function handle(Request $request, $next)
    {
        $provider = (string) self::setting('captcha_provider', 'off');
        if (!in_array($provider, ['hcaptcha', 'recaptcha_v2', 'turnstile'], true)) {
            return $next($request);
        }

        config(['recaptcha.enabled' => false]);

        if (!$request->isMethod('POST')) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        $guarded = [
            'auth/login',
            'auth/password',
            'auth/password/reset',
            'auth/login/checkpoint',
        ];
        if (!in_array($path, $guarded, true)) {
            return $next($request);
        }

        $token = $request->input('prm-adv-captcha')
            ?: $request->input('g-recaptcha-response')
            ?: $request->input('h-captcha-response')
            ?: $request->input('cf-turnstile-response');

        if (!is_string($token) || trim($token) === '') {
            throw new HttpException(400, 'Captcha doğrulaması gerekli.');
        }

        $secret = (string) self::setting('captcha_secret', '');
        if ($secret === '') {
            throw new HttpException(400, 'Captcha gizli anahtarı tanımlı değil.');
        }

        $urls = [
            'hcaptcha' => 'https://hcaptcha.com/siteverify',
            'recaptcha_v2' => 'https://www.google.com/recaptcha/api/siteverify',
            'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        ];

        try {
            $res = Http::asForm()->timeout(8)->post($urls[$provider], [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);
            $ok = $res->successful() && ($res->json('success') === true);
        } catch (Throwable $e) {
            $ok = false;
        }

        if (!$ok) {
            throw new HttpException(400, 'Captcha doğrulanamadı.');
        }

        return $next($request);
    }
}
