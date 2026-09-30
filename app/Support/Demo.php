<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The public demo (docs/handbuch/demo.md): one shared, password-less account
 * owning fictional sample projects, rebuilt from scratch by {@see self::reset()}.
 *
 * The account has no password, so the login form can never sign in as it; the
 * only way in is the "Demo" button (`DemoLoginController`), and only while
 * `nusszopf.demo` is on. Everything shown is invented — nothing here is, or
 * derives from, personal data — and every contact address is the reserved
 * `example.org`, so no message can reach a real mailbox.
 */
final class Demo
{
    public const EMAIL = 'demo@demo.example.org';

    public const NAME = 'demo';

    public static function enabled(): bool
    {
        return (bool) config('nusszopf.demo');
    }

    public static function isDemoUser(?User $user): bool
    {
        return $user !== null && $user->email === self::EMAIL;
    }

    public static function user(): ?User
    {
        return User::where('email', self::EMAIL)->first();
    }

    /**
     * The public project the guided tour sends visitors to.
     */
    public static function featuredProject(): ?Project
    {
        return self::user()?->projects()->where('visibility', 'public')->orderBy('created_at')->first();
    }

    /**
     * Deletes the demo account with everything it owns (through Eloquent, so the search index follows) and
     * creates it again with the sample data. Touches nothing that belongs to any other account.
     */
    public static function reset(): User
    {
        return DB::transaction(function (): User {
            if ($existing = self::user()) {
                AccountDeleter::delete($existing);
            }

            $user = new User;
            $user->forceFill([
                'name' => self::NAME,
                'email' => self::EMAIL,
                'email_verified_at' => now(),
                'password' => null,
            ])->save();

            foreach (self::projects() as $project) {
                self::create($user, $project);
            }

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function create(User $user, array $data): void
    {
        $team = $data['team'] ?? null;

        $project = $user->projects()->make([
            'title' => $data['title'],
            'goal' => $data['goal'],
            'description' => $data['description'],
            'description_template' => RichText::fromPlainText($data['description']),
            'location' => $data['location'] ?? ['remote' => true, 'searchTerm' => '', 'data' => (object) []],
            'period' => $data['period'] ?? ['flexible' => true, 'from' => '', 'to' => ''],
            'team' => $team,
            'team_template' => $team === null ? null : RichText::fromPlainText($team),
            'motto' => $data['motto'] ?? null,
            'visibility' => $data['visibility'],
            'contact' => 'kontakt@example.org',
        ]);
        $project->save();

        foreach ($data['requests'] as [$category, $title, $description]) {
            $project->requests()->make([
                'title' => $title,
                'category' => $category,
                'description' => $description,
                'description_template' => RichText::fromPlainText($description),
            ])->save();
        }

        // A little history on the view counter, so the demo does not look freshly emptied.
        $project->analytics()->create(['views' => $data['views'] ?? 0]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function place(string $city, string $state, string $postcode, string $lat, string $lon): array
    {
        return [
            'remote' => false,
            'searchTerm' => "{$city}, {$state}, Deutschland",
            'data' => [
                'key' => 'demo-'.strtolower($city),
                'postcode' => $postcode,
                'city' => $city,
                'countryCode' => 'de',
                'geo' => ['lat' => $lat, 'lon' => $lon],
                'osm' => ['id' => '0', 'type' => 'relation'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function period(int $fromMonths, int $toMonths): array
    {
        $from = Carbon::now()->addMonths($fromMonths);
        $to = Carbon::now()->addMonths($toMonths);

        return [
            'flexible' => false,
            'from' => ProjectDate::toStored($from->format('j.n.Y')),
            'to' => ProjectDate::toStored($to->format('j.n.Y')),
        ];
    }

    /**
     * The fictional sample data: invented projects in invented places of activity, nothing about real people.
     *
     * @return list<array<string, mixed>>
     */
    private static function projects(): array
    {
        return [
            [
                'visibility' => 'public',
                'title' => 'Gemeinschaftsgarten am Hinterhof',
                'goal' => 'Aus einem ungenutzten Hinterhof einen Garten machen, in dem die ganze Nachbarschaft gemeinsam anbaut und feiert.',
                'description' => "Der Hinterhof hinter dem Nachbarschaftstreff liegt seit Jahren brach. Wir wollen Hochbeete bauen, Gemüse und Kräuter anbauen und einen Platz zum Zusammensitzen schaffen.\nJede und jeder kann mitmachen – Vorkenntnisse sind nicht nötig, wir lernen gemeinsam.",
                'team' => "Ein Kernteam aus fünf Nachbar:innen, das sich alle zwei Wochen trifft.\nDie Gartengruppe der Grundschule berät uns.",
                'motto' => 'Gemeinsam wächst mehr.',
                'location' => self::place('Beispielstadt', 'Musterland', '12345', '48.0000000', '10.0000000'),
                'period' => self::period(1, 7),
                'views' => 42,
                'requests' => [
                    ['companions', 'Hobbygärtner:innen gesucht', 'Wir suchen Menschen mit grünem Daumen, die uns beim Anlegen der Beete anleiten.'],
                    ['materials', 'Holz für Hochbeete', 'Für sechs Hochbeete brauchen wir unbehandeltes Holz, gerne Reste oder Paletten.'],
                    ['financials', 'Startbudget für Saatgut', 'Für Saatgut, Erde und Werkzeug kalkulieren wir mit einem kleinen Startbudget.'],
                ],
            ],
            [
                'visibility' => 'public',
                'title' => 'Repair-Café für die Nachbarschaft',
                'goal' => 'Einmal im Monat gemeinsam reparieren statt wegwerfen – mit Kaffee, Kuchen und Werkzeug.',
                'description' => "Kaputte Lampen, wackelnde Stühle, streikende Toaster: In unserem Repair-Café bringen Menschen ihre Dinge mit und reparieren sie gemeinsam mit Freiwilligen.\nWir starten mit einem Termin pro Monat und schauen, wie es angenommen wird.",
                'team' => 'Drei Elektriker:innen im Ruhestand und zwei Studierende der Beispiel-Hochschule.',
                'motto' => 'Reparieren ist das neue Neu.',
                'location' => self::place('Musterhausen', 'Musterland', '54321', '49.0000000', '11.0000000'),
                'period' => ['flexible' => true, 'from' => '', 'to' => ''],
                'views' => 27,
                'requests' => [
                    ['rooms', 'Raum für den monatlichen Termin', 'Ein heller Raum mit Steckdosen und Tischen für etwa zwanzig Personen, einmal im Monat für einen Nachmittag.'],
                    ['companions', 'Reparaturprofis und Näh-Fans', 'Wir suchen Menschen, die Elektrogeräte, Fahrräder oder Textilien reparieren können.'],
                    ['materials', 'Werkzeugkisten', 'Lötkolben, Schraubendreher-Sets und Multimeter – gerne gebraucht.'],
                ],
            ],
            [
                'visibility' => 'public',
                'title' => 'Schul-Podcast „Frag doch mal!“',
                'goal' => 'Schüler:innen recherchieren, interviewen und produzieren gemeinsam einen Podcast über Themen ihrer Stadt.',
                'description' => "Eine Arbeitsgemeinschaft der Beispiel-Gesamtschule möchte einen Podcast aufbauen. Die Jugendlichen wählen die Themen selbst, führen Interviews und schneiden die Folgen.\nWir suchen Unterstützung bei Technik und Medienkompetenz.",
                'motto' => 'Wer fragt, gewinnt.',
                'location' => ['remote' => true, 'searchTerm' => '', 'data' => (object) []],
                'period' => self::period(2, 10),
                'views' => 15,
                'requests' => [
                    ['materials', 'Mikrofone und Aufnahmegerät', 'Zwei Ansteckmikrofone und ein Aufnahmegerät für Interviews unterwegs.'],
                    ['companions', 'Medienprofis für einen Workshop', 'Ein Nachmittag zu Interviewführung und Schnitt würde uns enorm helfen.'],
                ],
            ],
            [
                'visibility' => 'public',
                'title' => 'Open-Source-Werkstatt für Vereine',
                'goal' => 'Ehrenamtliche Vereine unterstützen, freie Software für ihre Vereinsarbeit einzurichten und zu betreiben.',
                'description' => "Viele Vereine zahlen für Werkzeuge, die es auch frei und selbst betreibbar gibt. Unsere Werkstatt hilft beim Einrichten von Kalendern, Dokumentenablagen und Mitgliederverwaltung.\nDie Treffen finden online statt, jede Frage ist willkommen.",
                'team' => 'Ein loses Team aus Entwickler:innen und Vereinsvorständen.',
                'location' => ['remote' => true, 'searchTerm' => '', 'data' => (object) []],
                'period' => ['flexible' => true, 'from' => '', 'to' => ''],
                'views' => 8,
                'requests' => [
                    ['companions', 'Admins mit Erfahrung im Selbsthosting', 'Wir suchen Menschen, die ihr Wissen an Vereine weitergeben möchten.'],
                    ['others', 'Ein Name und ein Logo', 'Unsere Werkstatt hat noch keinen richtigen Namen – Ideen sind willkommen!'],
                ],
            ],
            [
                'visibility' => 'private',
                'title' => 'Entwurf: Sommerfest im Innenhof',
                'goal' => 'Ein kleines Sommerfest für alle, die im Haus wohnen. Dieser Entwurf ist privat und für andere nicht sichtbar.',
                'description' => "Dieses Projekt ist ein privater Entwurf: Nur Du siehst es in „Meine Projekte“. Über „Veröffentlichen“ machst Du es für alle sichtbar, über „Verbergen“ wieder unsichtbar.\nSo kannst Du in Ruhe an Deiner Idee feilen.",
                'period' => ['flexible' => true, 'from' => '', 'to' => ''],
                'requests' => [
                    ['materials', 'Bierzeltgarnituren', 'Für etwa dreißig Personen.'],
                ],
            ],
        ];
    }
}
