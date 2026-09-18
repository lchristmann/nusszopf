import { type Page } from '@playwright/test';

/**
 * docs/rewrite/first-slice.md's temporary single-form create/edit screen
 * (title, goal, description, visibility) — not the historical wizard.
 */
export class ProjectFormPage {
    constructor(private readonly page: Page) {}

    async gotoCreate(): Promise<void> {
        await this.page.goto('/user/project/create');
    }

    async fill(title: string, goal: string, description: string): Promise<void> {
        await this.page.getByTestId('input_project-title').fill(title);
        await this.page.getByTestId('input_project-goal').fill(goal);
        await this.page.getByTestId('input_project-description').fill(description);
    }

    async choosePublic(): Promise<void> {
        await this.page.getByTestId('radio_visibility-public').check();
    }

    async choosePrivate(): Promise<void> {
        await this.page.getByTestId('radio_visibility-private').check();
    }

    async save(): Promise<void> {
        await this.page.getByTestId('btn_save_project-form').click();
    }
}
