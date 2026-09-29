<?php

namespace App\Livewire\Newsletter;

use App\Livewire\Newsletter\Concerns\ThrottlesNewsletter;
use App\Models\Lead;
use App\Support\Demo;
use App\Support\Newsletter;
use App\Support\Operator;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

/**
 * `containers/home/NewsletterSection/NewsletterForm.js` — the public sign-up
 * form. Built in slice 9 for Home's `NewsletterSection`, which places it in
 * slice 10 (docs/rewrite/master-roadmap.md). The answer never depends on
 * whether the address is already known (BUG-032).
 */
class SubscribeForm extends Component
{
    use ThrottlesNewsletter;

    /** `newsletterData.subscribe.notify.error`, with this instance's mailbox (decision A-5). */
    public static function error(): string
    {
        return 'Sorry, es ist ein Fehler aufgetreten. Bitte versuche es erneut oder melde dich bei '.Operator::contactEmail().'.';
    }

    public string $name = '';

    public string $email = '';

    public bool $privacy = false;

    public function subscribe(): void
    {
        // Defence in depth: Home shows no form on the public demo, and the demo must not collect addresses anyway.
        if (Demo::enabled()) {
            $this->dispatch('toast', type: 'error', message: 'In der Demo ist die Newsletter-Anmeldung deaktiviert.');

            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'privacy' => ['accepted'],
        ], [
            'name.max' => 'Maximal 50 Zeichen',
            'name.required' => 'Gib einen Namen ein',
            'email.required' => 'Gib eine E-Mail-Adresse ein',
            'email.email' => 'Keine valide E-Mail-Adresse',
            'email.max' => 'Keine valide E-Mail-Adresse',
            'privacy.accepted' => 'Stimme den Datenschutzbestimmungen zu',
        ]);

        if (! $this->attemptNewsletterAction()) {
            $this->dispatch('toast', type: 'error', message: self::error());

            return;
        }

        try {
            Newsletter::subscribe(trim($this->email), trim($this->name), Lead::SOURCE_FORM);
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: self::error());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'E-Mail verschickt! Bitte bestätige deine Anmeldung.');
    }

    public function render(): View
    {
        return view('livewire.newsletter.subscribe-form');
    }
}
