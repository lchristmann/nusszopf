import { type Locator, type Page, expect } from '@playwright/test';

/**
 * `pages/user/projects.js` — the grid of `EditProjectCard`s: click-through
 * to edit, and a per-card `MoreHorizontal` menu (Ansehen / Bearbeiten /
 * Verbergen|Veröffentlichen / Löschen), items 0-3 in that order
 * (`app/Livewire/Projects/MyProjects.php`).
 */
export class MyProjectsPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/user/projects');
    }

    async editFirstProject(): Promise<void> {
        await this.page.getByTestId('route_edit-project_projects-page').first().click();
    }

    card(title: string): Locator {
        return this.page.getByTestId('route_edit-project_projects-page').filter({ hasText: title });
    }

    /**
     * The grid renders behind `wire:init="load"` (`ProjectsSkeleton` until
     * then), so the card must be settled — not just present, but past the
     * skeleton-to-grid morph — before its menu is opened; otherwise a click
     * can land mid-morph on a node Alpine hasn't (re)bound yet.
     */
    async openCardMenu(title: string): Promise<void> {
        const card = this.card(title);
        await expect(card).toBeVisible();
        await card.getByTestId('menu_edit-project-card').click();
        await expect(card.getByTestId('menuitem-0')).toBeVisible();
    }

    /** Item 0 ("Ansehen") — the project detail page, distinct from the card's own click-through (which edits). */
    async openProject(title: string): Promise<void> {
        await this.openCardMenu(title);
        await this.card(title).getByTestId('menuitem-0').click();
        // Callers read the project id from the URL: wait for the detail page, not the grid still showing.
        await this.page.waitForURL(/\/projects\/[^/]+$/);
    }

    /** Item 2 ("Verbergen"/"Veröffentlichen") — the throttled toggle. */
    async toggleVisibility(title: string): Promise<void> {
        await this.openCardMenu(title);
        await this.card(title).getByTestId('menuitem-2').click();
    }

    /** Item 3 ("Löschen") — triggers the historical native `confirm()` (BUG-013). */
    async deleteFromGrid(title: string): Promise<void> {
        await this.openCardMenu(title);
        await this.card(title).getByTestId('menuitem-3').click();
    }
}
