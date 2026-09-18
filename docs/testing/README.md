# Testing Strategy

Testing should match the engineering discipline of LCxHolz while being tailored to Nusszopf.

## Unit/domain

Test invariants, state transitions, business rules, and authorization logic where appropriate.

## Feature

Test HTTP/application behavior, Livewire behavior, validation, persistence, permissions, jobs, mail, and search integration.

## Browser

Use Playwright for important user journeys. Historical E2E tests should be translated into modern acceptance coverage.

## Visual parity

Where practical, use screenshots/reference comparisons to verify the historical UI.

## Regression

Every discovered historical bug that is fixed should receive regression coverage.

## CI

Document all required checks and make them executable locally.
