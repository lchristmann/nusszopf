<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * The browser-side security headers of every application response (P-4, SEC-06,
 * docs/release/parity/P-04-security.md). The historical Next.js app sent none; the web server
 * (docker/nginx/default.conf) adds the non-CSP ones to the static files it serves itself.
 *
 * The Content-Security-Policy allows scripts only from this origin. `'unsafe-eval'` stays because Livewire's
 * bundled Alpine evaluates the `x-on`/`x-data` expressions in the views with `new Function`; inline `<script>`
 * blocks and `on…` attributes are blocked, so an injected script cannot run. Styles allow `'unsafe-inline'`
 * for the `style` attributes Livewire, Alpine and the views set. Images from other hosts are limited to
 * Google's avatar host (BUG-004), besides APP_URL's own origin ({@see self::directives()}). The policy is left out while the Vite dev server runs (`npm run dev`),
 * whose scripts come from another origin.
 */
final class SecurityHeaders
{
    /**
     * The policy's directives. Uploaded avatars are addressed through the public disk's URL, which is
     * APP_URL's (config/filesystems.php): its origin is named next to `'self'` so they still load where the
     * application is reached under another name (the development stack's browser tests use `http://web`).
     *
     * @return list<string>
     */
    public static function directives(): array
    {
        $appUrl = parse_url((string) config('app.url'));
        $appOrigin = isset($appUrl['scheme'], $appUrl['host'])
            ? $appUrl['scheme'].'://'.$appUrl['host'].(isset($appUrl['port']) ? ':'.$appUrl['port'] : '')
            : '';

        return [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            trim("img-src 'self' {$appOrigin}").' data: https://*.googleusercontent.com',
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ];

        if (! Vite::isRunningHot()) {
            $headers['Content-Security-Policy'] = implode('; ', self::directives());
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
