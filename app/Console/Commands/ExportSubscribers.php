<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The operator's subscriber export (decision A-6): Nusszopf collects and
 * confirms subscribers but sends no newsletter issues; an operator sends them
 * with a tool of their own, from this list. Only confirmed subscribers, with
 * their consent record. The external sender must link to this instance's
 * `/newsletter/unsubscribe/lead` page (docs/handbuch/betrieb.md,
 * "Newsletter subscribers") so the `leads` table stays the source of truth —
 * export again before every send.
 */
#[Signature('newsletter:export {--output= : Write the CSV to this file instead of standard output}')]
#[Description('Export the confirmed newsletter subscribers as CSV (email, name, confirmed_at, requested_at, source, consent_version)')]
class ExportSubscribers extends Command
{
    public function handle(): int
    {
        $path = $this->option('output');
        $handle = fopen(is_string($path) && $path !== '' ? $path : 'php://output', 'w');

        if ($handle === false) {
            $this->error("Cannot write to {$path}.");

            return self::FAILURE;
        }

        fputcsv($handle, ['email', 'name', 'confirmed_at', 'requested_at', 'source', 'consent_version'], escape: '');

        Lead::whereNotNull('confirmed_at')->orderBy('confirmed_at')->lazy()->each(function (Lead $lead) use ($handle): void {
            fputcsv($handle, [
                $lead->email,
                $lead->name,
                $lead->confirmed_at?->toIso8601String(),
                $lead->requested_at->toIso8601String(),
                $lead->source,
                $lead->consent_version,
            ], escape: '');
        });

        fclose($handle);

        return self::SUCCESS;
    }
}
