import { type Page, expect } from '@playwright/test';
import { ProjectWizardPage } from '../pages/ProjectWizardPage';
import { RequestDialogPage, type RequestCategory } from '../pages/RequestDialogPage';

export interface ProjectSeed {
    title: string;
    goal?: string;
    description?: string;
    requests?: { title: string; category: RequestCategory; description: string }[];
}

/**
 * Creates a public project (with requests) through the real wizard, as the signed-in user, and
 * returns to My Projects. The wizard's default visibility is public.
 */
export async function createPublicProject(page: Page, seed: ProjectSeed): Promise<void> {
    const wizard = new ProjectWizardPage(page);
    const requests = new RequestDialogPage(page);

    await wizard.goto();
    await wizard.fillStepOne(seed.title, seed.goal ?? 'Ein Ziel.', seed.description ?? 'Eine Beschreibung.');
    await wizard.advanceTo(1);
    await wizard.advanceTo(2);
    for (const request of seed.requests ?? []) {
        await page.getByTestId('btn_create_requests-step').click();
        await requests.create(request.title, request.category, request.description);
    }
    await wizard.advanceTo(3);
    await wizard.next.click();
    await expect(page).toHaveURL(/\/user\/projects$/);
}
