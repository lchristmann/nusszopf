import { type Page } from '@playwright/test';

export class MyProjectsPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/user/projects');
    }

    async editFirstProject(): Promise<void> {
        await this.page.getByTestId('link_edit-project').first().click();
    }
}
