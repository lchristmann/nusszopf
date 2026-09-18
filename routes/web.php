<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\LoginRegister;
use App\Livewire\Projects\MyProjects;
use App\Livewire\Projects\ProjectDetail;
use App\Livewire\Projects\ProjectForm;
use App\Livewire\Search\Search;
use Illuminate\Support\Facades\Route;

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

Route::get('/search', Search::class)->name('search');
Route::get('/projects/{project}', ProjectDetail::class)->name('projects.show');

Route::get('/privacy', fn () => view('legal.pending', ['title' => 'Datenschutz']))->name('privacy');

Route::middleware('guest')->group(function () {
    Route::get('/login', LoginRegister::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::get('/user/projects', MyProjects::class)->name('projects.mine');
    Route::get('/user/project/create', ProjectForm::class)->name('projects.create');
    Route::get('/user/project/{project}/edit', ProjectForm::class)->name('projects.edit');
});
