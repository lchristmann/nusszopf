<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Models\ProjectAnalytics;
use App\Support\ProjectDate;
use App\Support\RichText;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * docs/design/screen-specs.md, "Project detail". Public, but a private
 * project must 404 for anyone but its owner — never a 403 (which would
 * reveal the project's existence to a non-owner), matching the confirmed
 * historical SSR behavior (docs/rewrite/open-questions.md, "Does
 * /projects/{id}'s SSR enforce visibility, or only existence?").
 */
#[Layout('components.layout', ['mainClass' => 'bg-white text-lilac-800 lg:bg-steel-100', 'footerBg' => 'bg-steel-100'])]
class ProjectDetail extends Component
{
    /**
     * `nusszopf_viewed_projects` in the historical `localStorage`
     * (`pages/projects/[id].js`) — the browser-side half of the dedupe
     * mechanism behind BUG-001's fix (register B-4). A cookie, not
     * `localStorage`, since the increment now happens server-side and there
     * is no client script issuing it anymore. Same "not spoof-proof"
     * property as the historical mechanism: clearing cookies re-counts.
     */
    private const VIEWED_COOKIE = 'nz_viewed_projects';

    public Project $project;

    public function mount(Project $project): void
    {
        if (Gate::denies('view', $project)) {
            abort(404);
        }

        $this->project = $project;
        $this->recordView();
    }

    /**
     * BUG-001's fix (docs/rewrite/bugs.md, docs/rewrite/intentional-changes.md):
     * the historical `views` counter was directly client-writable by any
     * caller, for any project. Nusszopf 2 increments it exclusively from this
     * one server-side call site — never through a client-writable field —
     * once per browser, excluding the project's own owner, matching the
     * historical intent.
     */
    private function recordView(): void
    {
        if (Auth::id() === $this->project->user_id) {
            return;
        }

        $cookie = request()->cookie(self::VIEWED_COOKIE);
        $viewed = is_string($cookie) ? json_decode($cookie, true) : null;
        $viewed = is_array($viewed) ? $viewed : [];

        if (in_array($this->project->id, $viewed, true)) {
            return;
        }

        ProjectAnalytics::query()->firstOrCreate(['project_id' => $this->project->id])->increment('views');

        Cookie::queue(self::VIEWED_COOKIE, json_encode([...$viewed, $this->project->id]), 60 * 24 * 365 * 5);
    }

    /**
     * `pages/projects/[id].js`: the OpenStreetMap link (and the city as its
     * text) exists only for a project with a selected place; anything else
     * reads "Ortsunabhängig".
     *
     * @return array{city: string, link: ?string}
     */
    public function locationLabel(): array
    {
        $data = $this->project->location['data'] ?? [];
        $osm = $data['osm'] ?? null;

        if (is_array($osm) && isset($osm['type'], $osm['id'])) {
            return ['city' => (string) ($data['city'] ?? ''), 'link' => "https://www.openstreetmap.org/{$osm['type']}/{$osm['id']}"];
        }

        if (($data['city'] ?? '') !== '') {
            return ['city' => (string) $data['city'], 'link' => null];
        }

        return ['city' => 'Ortsunabhängig', 'link' => null];
    }

    /**
     * "d.m.yyyy - d.m.yyyy" when both dates are stored, otherwise the flexible label.
     */
    public function periodLabel(): string
    {
        $from = ProjectDate::toDisplay($this->project->period['from'] ?? null);
        $to = ProjectDate::toDisplay($this->project->period['to'] ?? null);

        return $from !== '' && $to !== '' ? "{$from} - {$to}" : 'Flexibler Projektzeitraum';
    }

    public function render(): View
    {
        $project = $this->project;

        return view('livewire.projects.project-detail', [
            // First-slice projects have no structured document, only plain text.
            'descriptionHtml' => RichText::toHtml($project->description_template ?? RichText::fromPlainText($project->description)),
            'teamHtml' => RichText::toHtml($project->team_template ?? RichText::fromPlainText((string) $project->team)),
            'location' => $this->locationLabel(),
            'period' => $this->periodLabel(),
            'shareTitle' => Str::limit($project->title, 60, '...'),
            'mailto' => 'mailto:'.$project->contact.'?subject='.rawurlencode('Nusszopf – Nussige Nachricht'),
            // `project.data.js` `report.href`: the id is appended to the
            // already-built mailto URL exactly as historically, unencoded.
            'reportMailto' => 'mailto:'.Project::NUSSZOPF_CONTACT.'?subject=Projekt melden (ID: '.$project->id.')',
            'views' => $project->analytics()->value('views'),
            // `requests(order_by: { created_at: desc })`, through the BUG-002 scope like every read.
            'requests' => $project->requests()->visible()->orderByDesc('created_at')->orderByDesc('id')->get(),
        ]);
    }
}
