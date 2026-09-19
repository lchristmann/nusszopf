import { type Page, type Locator } from '@playwright/test';

export class SearchPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/search');
    }

    async search(query: string): Promise<void> {
        // Explicit-submit search (matches the historical SearchInput.js
        // mechanism: Enter/blur or the search-icon click, never
        // live-as-you-type) — fill, then press Enter to submit the form.
        await this.page.getByTestId('input_search').fill(query);
        await this.page.getByTestId('input_search').press('Enter');
    }

    resultLink(title: string): Locator {
        return this.page.getByTestId('card_search-hit').filter({ hasText: title });
    }
}
