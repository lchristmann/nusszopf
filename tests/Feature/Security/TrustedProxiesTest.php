<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

/**
 * P-4, SEC-02 (docs/release/parity/P-04-security.md): the production template trusted every sender's
 * X-Forwarded-For (`TRUSTED_PROXIES=*`) while publishing the port on all interfaces, so a client reaching the
 * port directly could pick its own address and slip past every per-IP limit (login, registration, contact,
 * newsletter, forgotten password). The shipped default now trusts loopback and the private ranges only.
 */
function shippedTrustedProxies(): array
{
    preg_match('/^TRUSTED_PROXIES=(.*)$/m', (string) file_get_contents(base_path('.env.production.example')), $match);

    // The same parsing as bootstrap/app.php.
    return array_map('trim', explode(',', $match[1] ?? ''));
}

function clientIpThrough(array $proxies, string $remoteAddress, string $forwardedFor): string
{
    $request = Request::create('/', server: ['REMOTE_ADDR' => $remoteAddress, 'HTTP_X_FORWARDED_FOR' => $forwardedFor]);
    TrustProxies::at($proxies);

    $ip = '';
    (new TrustProxies)->handle($request, function (Request $request) use (&$ip) {
        $ip = $request->ip();

        return response('');
    });

    TrustProxies::flushState();

    return $ip;
}

it('does not ship a wildcard proxy trust in the production template', function () {
    expect(shippedTrustedProxies())->not->toContain('*')->and(shippedTrustedProxies())->not->toBe(['']);
});

it('ignores X-Forwarded-For from a client that reaches the port directly', function () {
    expect(clientIpThrough(shippedTrustedProxies(), '203.0.113.7', '198.51.100.1'))->toBe('203.0.113.7');
});

it('believes a proxy on this host or the local network', function (string $proxy) {
    expect(clientIpThrough(shippedTrustedProxies(), $proxy, '198.51.100.1'))->toBe('198.51.100.1');
})->with(['docker bridge gateway' => '172.18.0.1', 'loopback' => '127.0.0.1', 'LAN' => '192.168.1.10']);
