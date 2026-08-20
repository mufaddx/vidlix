import { test, expect } from '@playwright/test';

/**
 * A password you cannot see is how people get locked out of an account they
 * just created, and an agreement nobody scrolled is not one they read.
 */
test.describe('showing a password', () => {
    const screens = [
        ['sign in', '/login', '#password'],
        ['staff sign in', '/admin/login', '#password'],
    ];

    for (const [name, path, field] of screens) {
        test(`${name} can reveal what was typed`, async ({ page }) => {
            await page.goto(path);

            const input = page.locator(field);
            await input.fill('a-secret-value');
            await expect(input).toHaveAttribute('type', 'password');

            const button = page.locator(`[data-reveal="${field.slice(1)}"]`);
            await expect(button).toBeVisible();

            await button.click();
            await expect(input).toHaveAttribute('type', 'text');

            await button.click();
            await expect(input).toHaveAttribute('type', 'password');
        });
    }

    test('the reveal button sits inside the field, not over the page', async ({ page }) => {
        await page.goto('/login');

        const field = await page.locator('#password').boundingBox();
        const button = await page.locator('[data-reveal="password"]').boundingBox();

        // Absolute positioning needs a positioned wrapper; without one the
        // button lands wherever the nearest ancestor happens to be.
        expect(button.x).toBeGreaterThan(field.x);
        expect(button.x + button.width).toBeLessThanOrEqual(field.x + field.width + 2);
    });
});

test.describe('accepting the terms', () => {
    test('accept is closed until the agreement has been read', async ({ page }) => {
        await page.goto('/register');
        await page.locator('label.role', { hasText: 'Influencer' }).first().click();

        await page.locator('[data-terms-open]').first().click();

        const accept = page.locator('[data-terms-accept]');
        await expect(accept).toBeVisible();
        await expect(accept).toBeDisabled();
        await expect(page.locator('[data-terms-gate]')).toBeVisible();

        // Reaching the end is what enables it.
        await page.locator('.modal-body').evaluate((el) => {
            el.scrollTop = el.scrollHeight;
            el.dispatchEvent(new Event('scroll'));
        });

        await expect(accept).toBeEnabled();
        await expect(page.locator('[data-terms-gate]')).toBeHidden();
    });

    test('the money terms are actually in the agreement', async ({ page }) => {
        await page.goto('/register');
        await page.locator('label.role', { hasText: 'Influencer' }).first().click();
        await page.locator('[data-terms-open]').first().click();

        const body = page.locator('.modal-body');
        await expect(body).toContainText('platform fee');
        await expect(body).toContainText('Cancellation and refunds');
        await expect(body).toContainText('Taxes');
    });
});
