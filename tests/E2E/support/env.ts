/**
 * docs/testing/README.md: one fixture/env-constants file every spec
 * imports from, so seed-data drift/magic strings are caught in one place.
 *
 * This journey doesn't depend on seeded fixture accounts — it registers
 * its own account as step 1, which is more correct for a spec whose first
 * assertion *is* registration. `uniqueSuffix()` keeps concurrent/repeated
 * runs from colliding on the unique `name`/`email` columns.
 */
export function uniqueSuffix(): string {
    return `${Date.now()}${Math.floor(Math.random() * 1000)}`;
}

export function testUsername(suffix: string): string {
    // Historical rule: no whitespace, <= 15 chars (docs/authentication/README.md §2).
    return `nz${suffix}`.slice(0, 15);
}

export function testEmail(suffix: string): string {
    return `nz${suffix}@example.test`;
}

export const TEST_PASSWORD = 'Str0ng!Passw0rd';
