<?php

namespace App\Providers;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        AboutCommand::add('Nusszopf', ['Version' => fn () => config('nusszopf.version')]);

        // Every absolute URL is built from APP_URL, never from the request's Host header: the links in the
        // password-reset, verification, unblock and newsletter mails are generated during a visitor's request,
        // and a forged Host would otherwise send another person's token to that host (P-4, SEC-01,
        // docs/release/parity/P-04-security.md). The development stack is exempt so that its browser tests can
        // reach the app under a second name (`http://web`, compose.dev.yaml).
        if (! $this->app->environment('local')) {
            $root = rtrim((string) config('app.url'), '/');

            URL::forceRootUrl($root);
            URL::forceScheme(parse_url($root, PHP_URL_SCHEME) ?: 'https');
        }
    }
}
