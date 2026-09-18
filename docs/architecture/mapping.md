# Historical → Nusszopf 2 Mapping

Create the implementation mapping after archaeology.

| Historical responsibility | Nusszopf 2 implementation | Notes |
|---|---|---|
| Frontend | Blade + Livewire | Preserve UI/behavior |
| GraphQL/API | Laravel application/domain layer | Preserve observable behavior |
| Hasura | Eloquent/query/application layer | Preserve data/permission semantics |
| Auth0 | Laravel authentication | Preserve auth UX |
| Meilisearch | Meilisearch integration | Preserve search semantics |
| Email repository | Laravel Mail/Notifications | Preserve templates/behavior |
| Historical hosting | Docker/self-hosted deployment | Replace infrastructure, not product |

Expand with concrete historical components after archaeology.
