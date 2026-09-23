<?php

namespace App\Support;

/**
 * Who runs this instance (decision A-5: the operator mailbox and identity
 * are configuration, not the original nusszopf.org's literals).
 */
final class Operator
{
    /** The "write to us" mailbox, `config('nusszopf.contact_email')`. */
    public static function contactEmail(): string
    {
        return (string) config('nusszopf.contact_email');
    }

    /**
     * `public/contact/nusszopf-vcard.vcf` with this instance's own addresses
     * and URL in place of nusszopf.org's; name, organisation and note are the
     * historical card's.
     */
    public static function vcard(): string
    {
        $lines = [
            'BEGIN:VCARD',
            'VERSION:4.0',
            'N:Team;Nusszopf;;;',
            'FN:Team Nusszopf',
            'ORG:Nusszopf',
            ...array_map(
                fn (string $email) => 'EMAIL:'.$email,
                array_values(array_unique(array_filter([(string) config('mail.from.address'), self::contactEmail()]))),
            ),
            'URL:'.rtrim((string) config('app.url'), '/'),
            'NOTE;CHARSET=UTF-8:Netzwerk für gemeinsame Ideen und Projekte',
            'END:VCARD',
        ];

        return implode("\r\n", $lines)."\r\n";
    }
}
