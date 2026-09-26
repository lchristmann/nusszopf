# Legal text examples — not defaults

Nusszopf ships **no** legal text (decision A-4, `docs/rewrite/decisions-register.md`). The Impressum
(`/legalNotice`), Rechtliches (`/legalPolicy`) and Datenschutz (`/privacy`) pages render the operator's own
Markdown files — see `docs/deployment/README.md`, "Legal pages".

The three files here are the texts of the **original** Nusszopf operators (2021), transcribed from the
historical source (`assets/data/{legal-notice,legal-policy,privacy}.data.js`) so an operator can see what
the pages looked like and how Markdown maps onto them. They are unreviewed and labelled as examples in their
first line. The original operators' names, street address and telephone number are **not** reproduced: this
repository is public, so each is a bracketed placeholder (`[Name der verantwortlichen Person oder Organisation]`,
`[Straße und Hausnummer]`, `[Postleitzahl und Ort]`, `[Telefonnummer]`) that the operator fills in
(decision C3, `docs/rewrite/decisions-register.md`). The Datenschutz example has its Visitor Analytics section and its
Auth0 and SendGrid sentences removed (marked in place), because Nusszopf 2 uses none of those services.

Markdown mapping: `##`/`###` are the section headings, paragraphs and lists as usual, a line ending in two
spaces is a line break, `*…*` is the italic source line. Raw HTML is shown as text.
