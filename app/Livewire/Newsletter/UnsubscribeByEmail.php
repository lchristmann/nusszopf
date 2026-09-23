<?php

namespace App\Livewire\Newsletter;

use App\Livewire\Newsletter\Concerns\ThrottlesNewsletter;
use App\Support\Newsletter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * `pages/newsletter/unsubscribe/lead.js`: unsubscribe by typing the address.
 * Every valid address gets the same success toast; the mail goes out only if
 * the address is subscribed (BUG-033). It is also the stable unsubscribe URL
 * an operator's external newsletter sender must link to (decision A-6).
 */
#[Layout('components.layout', [
    'mainClass' => 'bg-white sm:bg-steel-100',
    'footerBg' => 'bg-white sm:bg-steel-100',
    'noindex' => true,
])]
class UnsubscribeByEmail extends Component
{
    use ThrottlesNewsletter;

    public string $email = '';

    public function unsubscribe(): void
    {
        $this->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Gib eine E-Mail-Adresse ein.',
            'email.email' => 'Keine valide E-Mail-Adresse.',
            'email.max' => 'Keine valide E-Mail-Adresse.',
        ]);

        if (! $this->attemptNewsletterAction()) {
            $this->dispatch('toast', type: 'error', message: SubscribeForm::error());

            return;
        }

        try {
            Newsletter::requestUnsubscribe(trim($this->email));
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: SubscribeForm::error());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'E-Mail verschickt! Bitte bestätige deine Abmeldung.');
    }

    public function render(): View
    {
        return view('livewire.newsletter.unsubscribe-by-email');
    }
}
