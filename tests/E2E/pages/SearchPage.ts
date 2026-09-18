import { type Page, type Locator } from '@playwright/test';

export class SearchPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/search');
    }

    async search(query: string): Promise<void> {
        await this.page.getByTestId('input_search').fill(query);
        // wire:model.live.debounce.500ms
        await this.page.waitForTimeout(600);
    }

    resultLink(title: string): Locator {
        return this.page.getByTestId('card_search-hit').filter({ hasText: title });
    }
}
