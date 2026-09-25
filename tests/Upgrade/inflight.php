<?php

// Upgrade test (scripts/upgrade-test.sh): work that is still queued when the upgrade starts. The queue worker
// is stopped first, so these jobs are serialized by the old release and run by the new one. Version-aware,
// like seed.php: it queues only what the old release can.

use App\Mail\ContactMail;
use App\Models\Project;
use App\Support\Newsletter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$queued = ['index'];

// A search-index update (MakeSearchable for the project and its requests).
$project = Project::where('title', 'like', 'Projekt P9TOKEN002%')->firstOrFail();
$project->title = 'Projekt P9TOKEN002 INFLIGHT umbenannt';
$project->save();

// A password-reset mail.
if (Route::has('password.reset')) {
    Password::sendResetLink(['email' => 'p9user11@example.test']);
    $queued[] = 'reset';
}

// A newsletter confirmation mail.
if (class_exists(Newsletter::class)) {
    Newsletter::subscribe('p9inflight@example.test', 'Inflight', 'form');
    $queued[] = 'newsletter';
}

// A contact mail.
if (class_exists(ContactMail::class)) {
    Mail::to('p9user06@example.test')->queue(new ContactMail(
        'p9user06@example.test', 'besuch@example.test', 'Projekt P9TOKEN011', null, 'INFLIGHT Kontaktnachricht über das Upgrade',
    ));
    $queued[] = 'contact';
}

echo 'queued: ', implode(' ', $queued), ' (', Redis::connection()->llen('queues:default'), " jobs waiting)\n";
