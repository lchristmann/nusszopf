<?php

// Upgrade test (scripts/upgrade-test.sh): populates an installation of the *old* release with a representative
// dataset through that release's own models, so its observers (search indexing), casts and helpers run.
// Version-aware: it only touches tables, columns and classes the running release has. Deterministic.
// Prints JSON with the links a user would have received by mail (checked again after the upgrade).
//
//   docker compose cp seed.php php-fpm:/tmp/seed.php
//   docker compose exec -T php-fpm php /tmp/seed.php > seed.json

use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectAnalytics;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Support\AvatarUploader;
use App\Support\NewsletterToken;
use App\Support\RichText;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

mt_srand(909);
$out = [];
$pick = fn (array $a) => $a[mt_rand(0, count($a) - 1)];

$firstNames = ['Anna', 'Jörg', 'Zoë', 'Małgorzata', 'Björn', 'Chloé', 'Ümit', 'Sophie', 'Luca', 'Noah', 'Mia', 'Ferdinand'];
$places = [
    ['searchTerm' => 'Freiburg im Breisgau, Baden-Württemberg, Deutschland', 'geo' => ['lat' => 47.9990, 'lng' => 7.8421]],
    ['searchTerm' => 'Berlin, Deutschland', 'geo' => ['lat' => 52.5200, 'lng' => 13.4050]],
    ['searchTerm' => 'Zürich, Schweiz', 'geo' => ['lat' => 47.3769, 'lng' => 8.5417]],
    ['searchTerm' => 'Wien, Österreich', 'geo' => ['lat' => 48.2082, 'lng' => 16.3738]],
];
$categories = ['companions', 'rooms', 'materials', 'financials', 'others'];

$hasVerified = Schema::hasColumn('users', 'email_verified_at');
$hasGoogle = Schema::hasColumn('users', 'google_id');
$hasAvatar = class_exists(AvatarUploader::class);
$hasAnalytics = Schema::hasTable('project_analytics');
$hasLeads = Schema::hasTable('leads');

$users = [];
for ($i = 1; $i <= 40; $i++) {
    $n = sprintf('%02d', $i);
    $attrs = [
        'name' => "p9user{$n}",
        'email' => "p9user{$n}@example.test",
        'password' => Hash::make('P9-Upgrade-Passw0rd!'),
    ];
    $user = User::forceCreate($attrs);
    if ($hasVerified && $i % 3 !== 0) {
        $user->forceFill(['email_verified_at' => now()->subDays($i)])->save();
    }
    $users[] = $user;
}

// A Google-linked account with a Google avatar URL and no password (BUG-004 shape), where the release has it.
if ($hasGoogle) {
    $g = User::forceCreate(['name' => 'p9google', 'email' => 'p9google@example.test', 'password' => null]);
    $g->forceFill(['google_id' => '109876543210987654321', 'picture' => 'https://lh3.googleusercontent.com/a/p9-avatar=s96-c', 'email_verified_at' => now()])->save();
    $users[] = $g;
}

// Real avatar uploads through the release's own uploader (resize, crop, versioned file on the public disk).
$avatars = [];
if ($hasAvatar) {
    foreach ([1, 2, 3, 5, 8, 13, 21, 34] as $idx) {
        $img = imagecreatetruecolor(400 + $idx * 10, 300);
        imagefill($img, 0, 0, imagecolorallocate($img, ($idx * 37) % 255, ($idx * 71) % 255, ($idx * 113) % 255));
        imagestring($img, 5, 20, 20, "P9 {$idx}", imagecolorallocate($img, 255, 255, 255));
        $tmp = tempnam(sys_get_temp_dir(), 'av').'.png';
        imagepng($img, $tmp);
        $file = new UploadedFile($tmp, "avatar{$idx}.png", 'image/png', null, true);
        $u = $users[$idx - 1];
        AvatarUploader::store($u, $file);
        // Replace once for a few users, so the older versioned file is gone and avatar_version > 1.
        if ($idx % 2 === 1) {
            AvatarUploader::store($u->fresh(), new UploadedFile($tmp, "avatar{$idx}b.png", 'image/png', null, true));
        }
        $avatars[$u->name] = $u->fresh()->picture;
    }
}
$out['avatars'] = $avatars;

$projects = [];
$p = 0;
foreach ($users as $ui => $user) {
    $count = $ui % 5; // 0..4 projects per user
    for ($k = 0; $k < $count; $k++) {
        $p++;
        $token = sprintf('P9TOKEN%03d', $p);
        $remote = $p % 3 === 0;
        $place = $places[$p % count($places)];
        $flexible = $p % 4 !== 0;
        $desc = "Beschreibung {$token}: Wir bauen gemeinsam einen Gemeinschaftsgarten mit Äpfeln, Nüssen & Zöpfen. <b>kein html</b> \"Anführungszeichen\"";
        $team = $p % 2 ? "Team {$token}: ".$pick($firstNames).' und '.$pick($firstNames) : null;
        $project = new Project;
        $project->forceFill([
            'user_id' => $user->id,
            'title' => "Projekt {$token} ".$pick(['Garten', 'Werkstatt', 'Café', 'Repair-Café', 'Bühne']),
            'goal' => "Ziel {$token}: Nachbarschaft stärken",
            'description' => $desc,
            'description_template' => RichText::fromPlainText($desc),
            'location' => $remote
                ? ['remote' => true, 'searchTerm' => '', 'data' => (object) []]
                : ['remote' => false, 'searchTerm' => $place['searchTerm'], 'data' => ['geo' => $place['geo']]],
            'period' => $flexible
                ? ['flexible' => true, 'from' => '', 'to' => '']
                : ['flexible' => false, 'from' => '01.10.2026', 'to' => '31.12.2027'],
            'team' => $team,
            'team_template' => $team ? RichText::fromPlainText($team) : null,
            'motto' => $p % 5 === 0 ? "Motto {$token}" : null,
            'visibility' => $p % 4 === 1 ? 'private' : 'public',
            'contact' => $p % 2 === 0 ? $user->email : Project::NUSSZOPF_CONTACT,
        ]);
        $project->save();
        $reqs = $p % 6;
        for ($r = 0; $r < $reqs; $r++) {
            $rd = "Anfrage {$token}-{$r}: wir suchen ".$pick(['Werkzeug', 'Räume', 'Mitmacher:innen', 'Geld', 'Ideen']);
            $req = new ProjectRequest;
            $req->forceFill([
                'project_id' => $project->id,
                'title' => mb_substr("Suche {$token}-{$r}", 0, 40),
                'category' => $categories[($p + $r) % 5],
                'description' => $rd,
                'description_template' => RichText::fromPlainText($rd),
            ]);
            $req->save();
        }
        if ($hasAnalytics && $p % 2 === 1) {
            ProjectAnalytics::forceCreate(['project_id' => $project->id, 'views' => $p * 7]);
        }
        $projects[] = $project->id;
    }
}
$out['projects'] = count($projects);

// Newsletter leads: confirmed and pending, every source.
$pendingLeadLinks = [];
if ($hasLeads) {
    $sources = ['form', 'registration', 'profile'];
    for ($i = 1; $i <= 15; $i++) {
        $lead = Lead::forceCreate([
            'email' => $i <= 5 ? "p9user0{$i}@example.test" : "p9lead{$i}@example.test",
            'name' => "Lead {$i}",
            'source' => $sources[$i % 3],
            'consent_version' => '1',
            'requested_at' => now()->subDays($i % 10),
            'confirmed_at' => $i % 4 === 0 ? null : now()->subDays($i % 10),
        ]);
        if ($i % 4 === 0 && ! isset($pendingLeadLinks['subscribe'])) {
            $pendingLeadLinks['subscribe'] = route('newsletter.subscribe.confirm', NewsletterToken::make(NewsletterToken::SUBSCRIBE, $lead->id));
            $pendingLeadLinks['subscribe_email'] = $lead->email;
        }
        if ($i === 7) {
            $pendingLeadLinks['unsubscribe'] = route('newsletter.unsubscribe.confirm', NewsletterToken::make(NewsletterToken::UNSUBSCRIBE, $lead->id, $lead->email));
            $pendingLeadLinks['unsubscribe_email'] = $lead->email;
        }
    }
}
$out['newsletter_links'] = $pendingLeadLinks;

// Links a user received by mail before the upgrade and opens after it.
if (Schema::hasTable('password_reset_tokens') && Route::has('password.reset')) {
    $u = $users[9];
    $out['password_reset'] = ['email' => $u->email, 'url' => route('password.reset', ['token' => Password::broker()->createToken($u), 'email' => $u->email])];
}
if ($hasVerified && Route::has('verification.verify')) {
    $u = $users[2]; // i=3: unverified
    $out['verify'] = ['email' => $u->email, 'url' => URL::temporarySignedRoute('verification.verify', now()->addHours(24), ['id' => $u->getKey(), 'hash' => sha1($u->email)])];
}

$out['counts'] = collect(['users', 'projects', 'project_requests', 'project_analytics', 'leads', 'password_reset_tokens'])
    ->filter(fn ($t) => Schema::hasTable($t))->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
