# Architecture

## Target

Nusszopf 2 is a modular Laravel monolith.

Target stack:

- PHP 8.5
- Laravel 13
- PostgreSQL
- Blade
- Livewire 4
- Tailwind CSS 4
- Redis
- Meilisearch
- Pest
- Playwright
- Laravel Pint
- Larastan
- Docker Compose
- GitHub Actions
- Laravel Boost where useful

## Architectural principle

Preserve historical product behavior while replacing obsolete implementation architecture.

Historical responsibilities such as GraphQL, Hasura, Auth0, or separate frontend services should be translated into Laravel-native equivalents where appropriate.

Do not reproduce obsolete infrastructure merely because it existed historically.

Define domain/application boundaries based on the reconstructed domain, not arbitrary technical layers.
