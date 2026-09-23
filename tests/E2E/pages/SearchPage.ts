import { type Page, type Locator, expect } from '@playwright/test';

export type FilterOption = 'companions' | 'rooms' | 'materials' | 'financials' | 'others' | 'none';

export class SearchPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/search');
        await this.results.or(this.noHits).first().waitFor();
    }

    /** The first results (or the no-hits section) have replaced the skeleton. */
    get results(): Locator {
        return this.page.getByTestId('search-results');
    }

    get noHits(): Locator {
        return this.page.getByTestId('no-hits');
    }

    get skeleton(): Locator {
        return this.page.getByTestId('skeleton_hits');
    }

    get input(): Locator {
        return this.page.getByTestId('input_search-input');
    }

    get searchButton(): Locator {
        return this.page.getByTestId('btn_search_search-input');
    }

    get loadMore(): Locator {
        return this.page.getByTestId('btn_load-more');
    }

    get scrollTop(): Locator {
        return this.page.getByTestId('btn_scroll-top');
    }

    get cards(): Locator {
        return this.page.getByTestId('route_hitcard');
    }

    async search(query: string): Promise<void> {
        // Explicit-submit search (matches the historical SearchInput.js
        // mechanism: Enter/blur or the search-icon click, never
        // live-as-you-type) — fill, then press Enter to submit the form.
        // Wait for this search's own round trip: until it answers, the page still shows the previous results,
        // which can satisfy a count by coincidence (with a small page size the unfiltered first page can hold
        // exactly the cards a test waits for).
        await this.input.fill(query);
        const answered = this.page.waitForResponse((response) => response.url().includes('/livewire') && response.request().method() === 'POST');
        await this.input.press('Enter');
        await answered;
    }

    resultLink(title: string): Locator {
        return this.cards.filter({ hasText: title });
    }

    requestsOf(title: string): Locator {
        return this.resultLink(title).getByTestId('card_request-hit');
    }

    async openFilter(): Promise<void> {
        await this.page.getByTestId('btn_disclosure_filter-popover').click();
    }

    option(option: FilterOption): Locator {
        return this.page.getByTestId(`checkbox_${option}_filter-popover`);
    }

    /** Picks or unpicks an option (opening the popover if a click elsewhere closed it); nothing is applied yet. */
    async toggle(option: FilterOption): Promise<void> {
        if (!(await this.option(option).locator('xpath=ancestor::label').isVisible())) {
            await this.openFilter();
        }

        // The native checkbox is visually hidden (Checkbox.atom.js); its label is what is clicked.
        await this.option(option).locator('xpath=ancestor::label').click();
    }

    async expectResults(titles: string[]): Promise<void> {
        await expect(this.cards).toHaveCount(titles.length);
        for (const title of titles) {
            await expect(this.resultLink(title)).toHaveCount(1);
        }
    }
}
