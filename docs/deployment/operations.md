# Betrieb — verschoben

Das Betriebshandbuch ist jetzt Teil des deutschsprachigen **[Handbuchs](../handbuch/README.md)**. Diese Seite bleibt bestehen,
damit ältere Verweise ankommen.

| Früherer Abschnitt | Jetzt |
|---|---|
| Health checks, Logs | [Betrieb: Gesundheit prüfen, Logs](../handbuch/betrieb.md#gesundheit-prüfen) |
| Queue worker and scheduler | [Warteschlange und Scheduler](../handbuch/betrieb.md#warteschlange-und-scheduler) |
| What happens when a dependency is down | [Wenn ein Dienst ausfällt](../handbuch/betrieb.md#wenn-ein-dienst-ausfällt) |
| Search index recovery | [Suchindex](../handbuch/betrieb.md#suchindex) |
| Mail, Newsletter subscribers | [Mail im Betrieb](../handbuch/betrieb.md#mail-im-betrieb), [Newsletter-Anmeldungen](../handbuch/betrieb.md#newsletter-anmeldungen) |
| Backups, Restore | [Backup und Wiederherstellung](../handbuch/backup.md) |
| Upgrades, Rollback | [Deployment](../handbuch/deployment.md), [Zurückgehen](../handbuch/deployment.md#zurückgehen-rollback) |
| Running one-off Artisan commands | [Einmalige Artisan-Befehle](../handbuch/betrieb.md#einmalige-artisan-befehle) |
| Troubleshooting | [Fehlerbehebung](../handbuch/fehlerbehebung.md) |

Die getesteten Codeblöcke (Backup-Skript, Restore, Suchindex-Wiederherstellung) stehen jetzt in
[`backup.md`](../handbuch/backup.md) und [`betrieb.md`](../handbuch/betrieb.md); `scripts/restore-test.sh` und
`scripts/search-recovery-test.sh` lesen sie von dort.

Die frühere englische Fassung mit den Messwerten und Prüfverweisen (P-9 bis P-13) steht in der Git-Historie:
`git show 9c8fc8b:docs/deployment/operations.md`.
