{{--
    `auth0/welcome.mjml`, reproduced verbatim (docs/email/README.md item 1).
--}}
<x-mail.layout :title="'Willkommen beim Nusszopf!'">
    <p style="margin:0 0 20px 0; color:#37474F; font-size:16px; font-weight:500; line-height:22px;">
        Wow, Du bist jetzt beim Nusszopf angemeldet! Das heißt, Du bist jetzt ein:e Nusszopfer:in in
        ausgebackenster Form und wir könnten nicht glücklicher sein, dich beim Nusszopf willkommen zu heißen.
    </p>
    <p style="margin:0 0 20px 0; color:#37474F; font-size:16px; font-weight:500; line-height:22px;">
        Gestalte den Nusszopf aktiv mit: Sag uns, was Du für deine Ideenumsetzungen brauchst, was dir fehlt
        und am besten helfen würde. Wir freuen uns über jeden Anstoß, das ist so sicher wie die Nuss im Zopf!
    </p>
    <p style="margin:0 0 20px 0; color:#37474F; font-size:16px; font-weight:700; line-height:22px;">
        Backen wir uns die Welt, wie sie uns gefällt!
    </p>
    <p style="margin:0 0 24px 0; color:#37474F; font-size:16px; font-weight:500; line-height:22px;">
        Dein Nusszopf Team
    </p>
    <p style="margin:0; padding:12px; background-color:#FFF3E0; color:#37474F; font-size:14px; font-weight:500; line-height:20px;">
        Falls Du diese Anfrage nicht gestellt hast, kontaktiere uns bitte via
        <a href="mailto:{{ \App\Models\Project::NUSSZOPF_CONTACT }}" style="color:#37474F; text-decoration:underline;">{{ \App\Models\Project::NUSSZOPF_CONTACT }}</a>.
    </p>
</x-mail.layout>
