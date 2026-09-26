<?php

// Queue/scheduler drill (scripts/queue-scheduler-test.sh): puts work on the production queue the way the application
// does, through its own mailables and models, and reports what it queued. Never sends or indexes anything itself.
//
//   docker compose exec -T php-fpm php /tmp/work.php mails <n> <tag>   n welcome mails, each to its own new account
//   docker compose exec -T php-fpm php /tmp/work.php gone <tag>        a welcome mail, then the account is deleted
//   docker compose exec -T php-fpm php /tmp/work.php edits <n>         n edits of public projects (index jobs)
//   docker compose exec -T php-fpm php /tmp/work.php motto <text>      the first public project gets this motto (index jobs)
//   docker compose exec -T php-fpm php /tmp/work.php leads <n> <tag>   n unconfirmed newsletter subscriptions, 15 days old

use App\Mail\WelcomeMail;
use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use App\Support\AccountDeleter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $what, $arg, $tag] = $argv + [null, 'mails', '1', 'x'];

$account = function (string $name): User {
    $user = new User;
    $user->forceFill(['name' => $name, 'email' => "$name@example.test", 'password' => 'x'])->save();

    return $user;
};

switch ($what) {
    case 'mails':
        for ($i = 1; $i <= (int) $arg; $i++) {
            Mail::send(new WelcomeMail($account(sprintf('%s%03d', $tag, $i))));
        }
        break;
    case 'gone':
        $user = $account("gone-$arg");
        Mail::send(new WelcomeMail($user));
        AccountDeleter::delete($user);
        break;
    case 'edits':
        Project::where('visibility', 'public')->orderBy('id')->limit((int) $arg)->get()
            ->each(fn (Project $p) => $p->forceFill(['motto' => 'Drill '.uniqid()])->save());
        break;
    case 'motto':
        Project::where('visibility', 'public')->orderBy('id')->firstOrFail()->forceFill(['motto' => $arg])->save();
        break;
    case 'leads':
        for ($i = 1; $i <= (int) $arg; $i++) {
            (new Lead)->forceFill([
                'email' => sprintf('%s-%d@example.test', $tag, $i), 'name' => 'Drill', 'source' => Lead::SOURCE_FORM,
                'consent_version' => '1', 'requested_at' => now()->subDays(15),
            ])->save();
        }
        break;
}

echo 'waiting in Redis: ', Redis::connection()->llen('queues:default'), "\n";
