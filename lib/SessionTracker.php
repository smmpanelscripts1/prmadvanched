<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (class_exists('PrmAdvanchedSessionTracker', false)) {
    return;
}

class PrmAdvanchedSessionTracker
{
    public static function ensureTable(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        if (Schema::hasTable('prmadvanched_sessions')) {
            $ready = true;

            return;
        }
        Schema::create('prmadvanched_sessions', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('session_id', 128);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique('session_id');
            $table->index('user_id');
        });
        $ready = true;
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

    public static function device(?string $ua): string
    {
        $ua = (string) $ua;
        $browser = 'Tarayıcı';
        if (stripos($ua, 'Edg/') !== false || stripos($ua, 'Edge/') !== false) {
            $browser = 'Edge';
        } elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) {
            $browser = 'Opera';
        } elseif (stripos($ua, 'Chrome/') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($ua, 'Firefox/') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($ua, 'Safari/') !== false) {
            $browser = 'Safari';
        }
        $os = 'bilinmeyen cihaz';
        if (stripos($ua, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($ua, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            $os = 'iOS';
        } elseif (stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false) {
            $os = 'macOS';
        } elseif (stripos($ua, 'Linux') !== false) {
            $os = 'Linux';
        }

        return $browser . ' · ' . $os;
    }

    public static function destroySession(string $sessionId): void
    {
        try {
            app('session')->getHandler()->destroy($sessionId);
        } catch (Throwable $e) {
            //
        }
    }

    public static function isAlive(string $sessionId): bool
    {
        try {
            $payload = app('session')->getHandler()->read($sessionId);

            return is_string($payload) && $payload !== '';
        } catch (Throwable $e) {
            return true;
        }
    }

    public static function serializeRow($row, string $currentId): array
    {
        return [
            'id' => (int) $row->id,
            'current' => hash_equals($currentId, (string) $row->session_id),
            'ip' => (string) $row->ip,
            'device' => self::device($row->user_agent),
            'lastSeen' => $row->last_seen_at ? gmdate('c', strtotime($row->last_seen_at)) : null,
            'createdAt' => $row->created_at ? gmdate('c', strtotime($row->created_at)) : null,
        ];
    }

    public static function listFor(int $userId, string $currentId): array
    {
        self::ensureTable();
        $lifetime = (int) config('session.lifetime', 720);
        $cutoff = date('Y-m-d H:i:s', time() - ($lifetime * 60));
        $rows = DB::table('prmadvanched_sessions')
            ->where('user_id', $userId)
            ->orderByDesc('last_seen_at')
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $sid = (string) $row->session_id;
            $stale = $row->last_seen_at && $row->last_seen_at < $cutoff;
            $alive = $sid === $currentId || (!$stale && self::isAlive($sid));
            if (!$alive) {
                DB::table('prmadvanched_sessions')->where('id', $row->id)->delete();
                continue;
            }
            $out[] = self::serializeRow($row, $currentId);
        }

        return $out;
    }

    public function handle(Request $request, $next)
    {
        $response = $next($request);
        try {
            if (!$request->hasSession() || !Auth::check()) {
                return $response;
            }
            if ($request->is('auth') || $request->is('auth/*')) {
                return $response;
            }
            self::ensureTable();
            $sessionId = (string) $request->session()->getId();
            if ($sessionId === '') {
                return $response;
            }
            $now = date('Y-m-d H:i:s');
            $existing = DB::table('prmadvanched_sessions')->where('session_id', $sessionId)->first();
            $ip = self::clientIp($request);
            $ua = substr((string) $request->userAgent(), 0, 512);
            if ($existing) {
                $last = $existing->last_seen_at ? strtotime($existing->last_seen_at) : 0;
                if (time() - $last < 30 && (string) $existing->ip === $ip) {
                    return $response;
                }
                DB::table('prmadvanched_sessions')->where('id', $existing->id)->update([
                    'user_id' => (int) Auth::id(),
                    'ip' => $ip,
                    'user_agent' => $ua,
                    'last_seen_at' => $now,
                ]);
            } else {
                DB::table('prmadvanched_sessions')->insert([
                    'user_id' => (int) Auth::id(),
                    'session_id' => $sessionId,
                    'ip' => $ip,
                    'user_agent' => $ua,
                    'last_seen_at' => $now,
                    'created_at' => $now,
                ]);
            }
        } catch (Throwable $e) {
            //
        }

        return $response;
    }
}
