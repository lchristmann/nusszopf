import { type Locator, type Page, expect } from '@playwright/test';

/**
 * The historical four-step creation wizard (`/user/project/create?step=N`) —
 * docs/rewrite/second-slice.md. Selectors are the historical `data-test`
 * attributes wherever one existed.
 */
export class ProjectWizardPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/user/project/create');
        await expect(this.page.getByTestId('btn_create-or-next_navigation')).toBeVisible();
    }

    stepLabel(label: string): Locator {
        return this.page.getByText(label, { exact: true });
    }

    get next(): Locator {
        return this.page.getByTestId('btn_create-or-next_navigation');
    }

    get back(): Locator {
        return this.page.getByTestId('btn_go-back_navigation');
    }

    /** The wizard's step as the URL currently states it. */
    urlStep(): string | null {
        return new URL(this.page.url()).searchParams.get('step');
    }

    async clickNext(): Promise<void> {
        await this.next.click();
    }

    /** Weiter, then wait until the wizard really is on `step` (the URL follows the server). */
    async advanceTo(step: number): Promise<void> {
        await this.next.click();
        await expect(this.page).toHaveURL(new RegExp(`/user/project/create\\?step=${step}$`));
    }

    /** From step 1 on, with valid state: pass steps 2-4 and create the project. */
    async finishFromStepOne(): Promise<void> {
        await this.advanceTo(1);
        await this.advanceTo(2);
        await this.advanceTo(3);
        await this.next.click();
        await expect(this.page).toHaveURL(/\/user\/projects$/);
    }

    async fillTitle(title: string): Promise<void> {
        await this.page.getByTestId('input_project-title').fill(title);
    }

    async fillGoal(goal: string): Promise<void> {
        await this.page.getByTestId('input_project-goal').fill(goal);
    }

    editor(testId: 'input_project-description' | 'input_project-team'): Locator {
        return this.page.getByTestId(testId).locator('[contenteditable="true"]');
    }

    async fillEditor(testId: 'input_project-description' | 'input_project-team', text: string): Promise<void> {
        const editor = this.editor(testId);
        await editor.click();
        await this.page.keyboard.type(text);
    }

    /**
     * The historical Radiobox keeps a visually-hidden native input inside a
     * <label>; users click the visible label, so that is what is clicked.
     */
    async pick(testId: string): Promise<void> {
        await this.page.getByTestId(testId).locator('xpath=ancestor::label[1]/span').click();
        await expect(this.page.getByTestId(testId)).toBeChecked();
    }

    async chooseRemote(): Promise<void> {
        await this.pick('radio_remote_project-location');
    }

    /** Ortsgebunden: type a search term and pick the first suggestion. */
    async chooseLocation(term: string): Promise<void> {
        await this.pick('radio_fixed_project-location');
        const combobox = this.page.getByTestId('combobox_project-location');
        await combobox.fill(term);
        const option = this.page.getByTestId('option_project-location').first();
        await expect(option).toBeVisible({ timeout: 10_000 });
        await option.click();
    }

    async chooseFlexiblePeriod(): Promise<void> {
        await this.pick('radio_flexible_project-period');
    }

    async fillPeriod(from: string, to: string): Promise<void> {
        await this.pick('radio_fixed_project-period');
        await this.page.getByTestId('input_from_project-period').fill(from);
        await this.page.getByTestId('input_to_project-period').fill(to);
    }

    async fillMotto(motto: string): Promise<void> {
        await this.page.getByTestId('input_project-motto').fill(motto);
    }

    async choosePublic(): Promise<void> {
        await this.pick('radio_public_project-visibility');
    }

    async choosePrivate(): Promise<void> {
        await this.pick('radio_private_project-visibility');
    }

    async choosePersonalContact(): Promise<void> {
        await this.pick('radio_direct_project-contact');
    }

    /** The shortest valid path through step 1: remote + flexible. */
    async fillStepOne(title: string, goal: string, description: string): Promise<void> {
        await this.fillTitle(title);
        await this.fillGoal(goal);
        await this.fillEditor('input_project-description', description);
        await this.chooseRemote();
        await this.chooseFlexiblePeriod();
    }
}
