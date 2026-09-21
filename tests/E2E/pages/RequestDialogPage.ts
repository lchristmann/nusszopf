import { type Locator, type Page, expect } from '@playwright/test';

export type RequestCategory = 'companions' | 'rooms' | 'materials' | 'financials' | 'others';

/**
 * The request create/edit dialog (`EditRequestDialog.js`) and the cards it
 * feeds — shared by the wizard's step 3 and the edit screen's "Gesuche" view.
 * Selectors are the historical `data-test` attributes.
 */
export class RequestDialogPage {
    constructor(private readonly page: Page) {}

    get dialog(): Locator {
        return this.page.getByTestId('edit-request-dialog');
    }

    get title(): Locator {
        return this.dialog.getByTestId('input_request-title');
    }

    get category(): Locator {
        return this.dialog.getByTestId('select_request-category');
    }

    get description(): Locator {
        return this.dialog.getByTestId('input_request-description').locator('[contenteditable="true"]');
    }

    get submit(): Locator {
        return this.dialog.getByTestId('btn_create-or-save_edit-request-dialog');
    }

    get cancel(): Locator {
        return this.dialog.getByRole('button', { name: 'Abbrechen' });
    }

    get cards(): Locator {
        return this.page.getByTestId('card_request');
    }

    card(title: string): Locator {
        return this.cards.filter({ hasText: title });
    }

    async fill(title: string, category: RequestCategory, description: string): Promise<void> {
        await expect(this.dialog).toBeVisible();
        await this.title.fill(title);
        await this.category.selectOption(category);
        await this.description.click();
        await this.page.keyboard.type(description);
    }

    async create(title: string, category: RequestCategory, description: string): Promise<void> {
        await this.fill(title, category, description);
        await this.submit.click();
        await expect(this.dialog).toBeHidden();
    }

    /** Opens a request card's context menu and picks an item (0 = Bearbeiten, 1 = Löschen). */
    async menuItem(title: string, index: 0 | 1): Promise<void> {
        const card = this.card(title);
        await card.getByTestId('menu_edit-request-card').click();
        await card.getByTestId(`menuitem-${index}`).click();
    }
}
