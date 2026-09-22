<?php

use App\Health\HealthChecker;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ResendVerificationController;
use App\Http\Controllers\Auth\UnblockLoginController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\LoginRegister;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Projects\MyProjects;
use App\Livewire\Projects\ProjectDetail;
use App\Livewire\Projects\ProjectEdit;
use App\Livewire\Projects\ProjectWizard;
use App\Livewire\Search\Search;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
|--------------------------------------------------------------------------
| Home
|--------------------------------------------------------------------------
|
| The historical landing page (docs/design/screens.md, "Home") is out of
| scope for this first vertical slice (docs/rewrite/first-slice.md) — it is
| a large CMS-driven marketing page, not part of the acceptance criteria.
| Redirecting to /search keeps the app navigable without inventing interim
| marketing-page UI. This is temporary scaffolding, not a product decision
| that Home is unnecessary.
|
*/
Route::redirect('/', '/search')->name('home');

/*
| Dependency health for operators and monitoring (docs/deployment/operations.md). `/up` is the
| container liveness probe; this one is 503 while a dependency, the scheduler or the queue worker
| is down. Details only for a caller holding HEALTH_TOKEN. It starts no session: with Redis down
| (where sessions live) it must still answer, and say so.
*/
Route::get('/health', function (Request $request, HealthChecker $health) {
    $checks = $health->run();
    $healthy = $health->healthy($checks);
    $token = config('nusszopf.health_token');
    $body = ['status' => $healthy ? 'ok' : 'degraded'];

    if ($token && hash_equals((string) $token, (string) $request->bearerToken())) {
        $body += ['version' => config('nusszopf.version'), 'checks' => $checks];
    }

    return response()->json($body, $healthy ? 200 : 503);
})->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])->name('health');

Route::get('/search', Search::class)->name('search');
Route::get('/projects/{project}', ProjectDetail::class)->name('projects.show');

Route::get('/privacy', fn () => view('legal.pending', ['title' => 'Datenschutz']))->name('privacy');

Route::middleware('guest')->group(function () {
    Route::get('/login', LoginRegister::class)->name('login');

    // "Passwort vergessen" / "Neues Passwort erstellen" (docs/authentication/README.md
    // §4) — the historical two-app Auth0 split becomes two ordinary routes.
    Route::get('/password/forgot', ForgotPassword::class)->name('password.request');
    Route::get('/password/reset/{token}', ResetPassword::class)->name('password.reset');

    // Google login (docs/authentication/README.md §3; register B-6) — both
    // routes 404 while GOOGLE_CLIENT_ID/SECRET are unset
    // (App\Http\Controllers\Auth\GoogleController::configured()).
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

// The "Das bin ich!" unblock link (App\Mail\BlockedAccountMail) and the
// e-mail-verification link (App\Mail\VerifyEmailMail, decision A-3) both
// identify their target entirely through their own signed URL — neither
// needs (or should require) an active session to work.
Route::get('/auth/unblock', UnblockLoginController::class)->middleware('signed')->name('login.unblock');
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)->middleware('signed')->name('verification.verify');

Route::middleware('auth')->group(function () {
    Route::post('/logout', LogoutController::class)->name('logout');
    Route::post('/email/verification-notification', ResendVerificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('/user/projects', MyProjects::class)->name('projects.mine');
    Route::get('/user/project/create', ProjectWizard::class)->name('projects.create');
    Route::get('/user/project/{project}/edit', ProjectEdit::class)->name('projects.edit');
});
