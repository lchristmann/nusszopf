-- Nusszopf's Feature-test suite runs against real PostgreSQL, not SQLite
-- (docs/development/quality.md, "Database-specific testing"). This creates
-- a dedicated database for that suite alongside the main dev database, so
-- tests never share state with data a developer is looking at locally.
CREATE DATABASE nusszopf_testing;
