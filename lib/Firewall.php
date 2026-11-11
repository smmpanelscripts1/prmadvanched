<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\IpUtils;

if (class_exists('PrmAdvanchedFirewall', false)) {
    return;
}

class PrmAdvanchedFirewall
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

    public static function write(string $record, $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'prmadvanched::' . $record],
            ['value' => serialize($value)]
        );
        self::$cache[$record] = $value;
    }

    public static function clientIp(Request $request): string
    {
        $forwarded = (string) $request->headers->get('X-Forwarded-For', '');
        if ($forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }
        $real = (string) $request->headers->get('X-Real-IP', '');
        if ($real !== '') {
            return $real;
        }

        return (string) $request->ip();
    }

    public static function normalizeRule(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (strpos($raw, '/') !== false) {
            [$net, $bits] = array_pad(explode('/', $raw, 2), 2, '');
            $net = trim($net);
            $bits = (int) $bits;
            if (filter_var($net, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                if ($bits < 8 || $bits > 32) {
                    return null;
                }

                return $net . '/' . $bits;
            }
            if (filter_var($net, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                if ($bits < 32 || $bits > 128) {
                    return null;
                }

                return $net . '/' . $bits;
            }

            return null;
        }
        $ip = filter_var($raw, FILTER_VALIDATE_IP);

        return $ip !== false ? $ip : null;
    }

    public static function listKey(string $kind): string
    {
        if ($kind === 'panel') {
            return 'firewall_panel_allow';
        }
        if ($kind === 'api') {
            return 'firewall_api_allow';
        }

        return 'firewall_bans';
    }

    public static function rules(string $kind = 'ban'): array
    {
        $raw = self::setting(self::listKey($kind), []);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $ip = self::normalizeRule((string) ($row['ip'] ?? ''));
            if ($ip === null) {
                continue;
            }
            $out[] = [
                'ip' => $ip,
                'note' => (string) ($row['note'] ?? ''),
                'source' => (string) ($row['source'] ?? 'manual'),
                'at' => (int) ($row['at'] ?? 0),
            ];
        }

        return $out;
    }

    public static function bans(): array
    {
        return self::rules('ban');
    }

    public static function ipIn(string $ip, array $rows): bool
    {
        $ip = trim($ip);
        if ($ip === '') {
            return false;
        }
        foreach ($rows as $row) {
            $rule = is_array($row) ? (string) ($row['ip'] ?? '') : (string) $row;
            if ($rule === '') {
                continue;
            }
            try {
                if (IpUtils::checkIp($ip, $rule)) {
                    return true;
                }
            } catch (Throwable $e) {
                if (strcasecmp($ip, $rule) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function isBanned(string $ip): bool
    {
        return self::ipIn($ip, self::bans());
    }

    public static function addRule(string $kind, string $ip, string $note = '', string $source = 'manual'): array
    {
        $rule = self::normalizeRule($ip);
        if ($rule === null) {
            throw new InvalidArgumentException('Geçersiz IP veya CIDR.');
        }
        $rows = self::rules($kind);
        foreach ($rows as $row) {
            if (strcasecmp($row['ip'], $rule) === 0) {
                return $rows;
            }
        }
        array_unshift($rows, [
            'ip' => $rule,
            'note' => mb_substr(trim($note), 0, 120),
            'source' => $source === 'auto' ? 'auto' : 'manual',
            'at' => time(),
        ]);
        self::write(self::listKey($kind), array_slice($rows, 0, 500));

        return self::rules($kind);
    }

    public static function addBan(string $ip, string $note = '', string $source = 'manual'): array
    {
        return self::addRule('ban', $ip, $note, $source);
    }

    public static function removeRule(string $kind, string $ip): array
    {
        $rule = self::normalizeRule($ip) ?: trim($ip);
        $rows = array_values(array_filter(self::rules($kind), static function ($row) use ($rule) {
            return strcasecmp((string) $row['ip'], $rule) !== 0;
        }));
        self::write(self::listKey($kind), $rows);

        return $rows;
    }

    public static function removeBan(string $ip): array
    {
        return self::removeRule('ban', $ip);
    }

    public static function isWings(Request $request): bool
    {
        return $request->is('api/remote') || $request->is('api/remote/*');
    }

    public static function isApi(Request $request): bool
    {
        return $request->is('api') || $request->is('api/*');
    }

    public static function enabled(): bool
    {
        return (string) self::setting('firewall_enabled', '1') !== '0';
    }

    public static function isAdminSession(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        if (!empty($user->root_admin) || !empty($user->rootAdmin)) {
            return true;
        }
        try {
            return method_exists($user, 'isRootAdmin') && $user->isRootAdmin();
        } catch (Throwable $e) {
            return false;
        }
    }

    private function denied(Request $request, string $reason = 'ban')
    {
        $detail = $reason === 'allow'
            ? 'Bu IP allow listesinde değil.'
            : 'Bu IP panelden engellenmiş.';
        if ($request->expectsJson() || $request->is('api/*') || $request->is('extensions/prmadvanched/*')) {
            return response()->json([
                'errors' => [[
                    'code' => $reason === 'allow' ? 'IpNotAllowed' : 'IpBanned',
                    'status' => '403',
                    'detail' => $detail,
                ]],
            ], 403);
        }

        $html = '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>403</title>'
            . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f172a;color:#e2e8f0;font-family:system-ui,sans-serif}'
            . '.c{max-width:420px;padding:28px;text-align:center}.c h1{margin:0 0 8px;font-size:22px}.c p{margin:0;color:#94a3b8;line-height:1.5}</style></head>'
            . '<body><div class="c"><h1>Erişim engellendi</h1><p>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</p></div></body></html>';

        return response($html, 403)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function recordFailedLogin(string $ip): void
    {
        if ((string) self::setting('firewall_autoban', '0') !== '1') {
            return;
        }
        $max = (int) self::setting('firewall_strikes_max', 5);
        if ($max < 2) {
            $max = 2;
        }
        if ($max > 50) {
            $max = 50;
        }
        $strikes = self::setting('firewall_strikes', []);
        if (!is_array($strikes)) {
            $strikes = [];
        }
        $row = is_array($strikes[$ip] ?? null) ? $strikes[$ip] : ['n' => 0, 'at' => 0];
        $n = (int) ($row['n'] ?? 0) + 1;
        $strikes[$ip] = ['n' => $n, 'at' => time()];
        if (count($strikes) > 2000) {
            uasort($strikes, static function ($a, $b) {
                return ((int) ($b['at'] ?? 0)) <=> ((int) ($a['at'] ?? 0));
            });
            $strikes = array_slice($strikes, 0, 1500, true);
        }
        self::write('firewall_strikes', $strikes);
        if ($n >= $max) {
            self::addBan($ip, 'Başarısız giriş (' . $n . ')', 'auto');
            unset($strikes[$ip]);
            self::write('firewall_strikes', $strikes);
        }
    }

    private function clearStrikes(string $ip): void
    {
        $strikes = self::setting('firewall_strikes', []);
        if (!is_array($strikes) || !isset($strikes[$ip])) {
            return;
        }
        unset($strikes[$ip]);
        self::write('firewall_strikes', $strikes);
    }

    public function handle(Request $request, $next)
    {
        if (!self::enabled()) {
            return $next($request);
        }
        if (self::isWings($request)) {
            return $next($request);
        }
        $ip = self::clientIp($request);
        $admin = self::isAdminSession($request);
        if (self::isBanned($ip) && !$admin) {
            return $this->denied($request, 'ban');
        }
        $isApi = self::isApi($request);
        if ($isApi && (string) self::setting('firewall_api_whitelist', '0') === '1') {
            if (!self::ipIn($ip, self::rules('api'))) {
                return $this->denied($request, 'allow');
            }
        }
        if (!$isApi && (string) self::setting('firewall_panel_whitelist', '0') === '1') {
            if (!self::ipIn($ip, self::rules('panel')) && !$admin) {
                return $this->denied($request, 'allow');
            }
        }

        $response = $next($request);
        $path = trim($request->path(), '/');
        if ($request->isMethod('POST') && in_array($path, ['auth/login', 'auth/login/checkpoint'], true)) {
            $status = method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : 200;
            if ($status >= 400) {
                $this->recordFailedLogin($ip);
            } elseif ($status < 400) {
                $this->clearStrikes($ip);
            }
        }

        return $response;
    }
}
