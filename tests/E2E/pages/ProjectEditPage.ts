import { type Locator, type Page, expect } from '@playwright/test';
import { ProjectWizardPage } from './ProjectWizardPage';

/**
 * The historical edit screen (`/user/project/{id}/edit`): a view selector
 * (Beschreibung / Gesuche / Einstellungen) over independently saved forms.
 */
export class ProjectEditPage {
    readonly fields: ProjectWizardPage;

    constructor(private readonly page: Page) {
        // The description view reuses the wizard's field components and selectors.
        this.fields = new ProjectWizardPage(page);
    }

    get viewSelect(): Locator {
        return this.page.getByTestId('select_view_edit-project-page');
    }

    /** Waits until the server has rendered the view, not just until the select shows it. */
    async expectView(view: 'Beschreibung' | 'Gesuche' | 'Einstellungen'): Promise<void> {
        const marker = {
            Beschreibung: 'btn_save_project-view',
            Gesuche: 'btn_create_requests-view',
            Einstellungen: 'btn_save_settings-view',
        }[view];
        await expect(this.viewSelect).toHaveValue(view);
        await expect(this.page.getByTestId(marker)).toBeVisible();
    }

    async selectView(view: 'Beschreibung' | 'Gesuche' | 'Einstellungen'): Promise<void> {
        // The screen opens on `SkeletonView` and loads in a second round trip; switch views only once it has.
        await expect(this.page.getByTestId('skeleton_edit-project')).toHaveCount(0);
        await this.viewSelect.selectOption(view);
        await this.expectView(view);
    }

    async saveDescription(): Promise<void> {
        await this.page.getByTestId('btn_save_project-view').click();
    }

    /**
     * Waits for the save's own Livewire round trip: the success toast of an earlier save may still be
     * showing, and navigating away before the response would drop this one.
     */
    async saveSettings(): Promise<void> {
        const saved = this.page.waitForResponse((response) => response.url().includes('/livewire') && response.request().method() === 'POST');
        await this.page.getByTestId('btn_save_settings-view').click();
        await saved;
    }

    async deleteProject(): Promise<void> {
        await this.page.getByTestId('btn_delete_settings-view').click();
    }
}
