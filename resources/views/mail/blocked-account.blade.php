{{--
    `auth0/blocked-account.mjml`, reproduced with the city/country clauses
    dropped (no geo-IP lookup, see App\Mail\BlockedAccountMail's docblock).
--}}
<x-mail.layout :title="'Nusszopf – IP-Adresse blockiert'">
    <p style="margin:0 0 20px 0; color:#37474F; font-size:24px; font-weight:700;">Was in Nusszopfs Namen geht hier vor?</p>
    <p style="margin:0 0 20px 0; color:#37474F; font-size:16px; font-weight:500; line-height:22px;">
        Wir haben verdächtige Aktivitäten bei der Anmeldung zu deinem Account festgestellt: die IP-Adresse
        <strong>{{ $sourceIp }}</strong> hat mehrfach erfolglos versucht, sich bei deinem Nusszopfaccount anzumelden.
    </p>
    <p style="margin:0 0 20px 0; color:#37474F; font-size:16px; font-weight:500; line-height:22px;">
        Sicherheitshalber haben wir daher diese IP-Adresse blockiert, sodass sie nicht mehr versuchen kann,
        sich bei deinem Account anzumelden.
    </p>
    <p style="margin:0 0 20px 0; color:#37474F; font-size:16px; font-weight:500; line-height:22px;">
        Wenn Du versucht hast dich anzumelden, kannst Du deine IP-Adresse hier wieder freischalten, ansonsten
        musst Du dir keine Gedanken machen, denn die fremde IP-Adresse ist und bleibt blockiert.
    </p>
    <x-mail.button :href="$unblockUrl">Das bin ich!</x-mail.button>
</x-mail.layout>
