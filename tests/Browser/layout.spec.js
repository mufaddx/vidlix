import { test, expect } from '@playwright/test';

/**
 * Layout facts the feature suite cannot see, because they only exist once CSS
 * has been applied.
 */
test.describe('auth layout', () => {
    test('the wordmark clears the step indicator', async ({ page }) => {
        await page.goto('/register');

        const brand = await page.locator('.auth-brand').boundingBox();
        const steps = await page.locator('.steps').boundingBox();

        // The gap is declared in CSS as 26px. On a plain inline <a> a vertical
        // margin is ignored, and the first step bar sat flush under the
        // wordmark as though the wordmark were step one.
        expect(steps.y - (brand.y + brand.height)).toBeGreaterThan(10);
    });

    test('the terms checkbox is a checkbox, not a stretched field', async ({ page }) => {
        await page.goto('/register');

        const box = page.locator('input[type="checkbox"]').first();
        const width = await box.evaluate((el) => el.getBoundingClientRect().width);

        expect(width).toBeLessThan(40);
    });
});
