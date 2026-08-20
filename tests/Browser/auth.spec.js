import { test, expect } from '@playwright/test';

/**
 * The sign-in screen, which the feature suite can render but not operate:
 * the reveal button, the checkbox and the loading state are all JavaScript.
 */
test.describe('sign in', () => {
    test('the page loads with its own dark styling', async ({ page }) => {
        await page.goto('/login');

        await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();

        // The auth screens are deliberately dark-only and load their own
        // stylesheet rather than the marketplace one.
        const background = await page.evaluate(
            () => getComputedStyle(document.body).backgroundColor,
        );
        expect(background).not.toBe('rgba(0, 0, 0, 0)');
    });

    test('the remember checkbox is a checkbox, not a full-width field', async ({ page }) => {
        await page.goto('/login');

        const box = page.locator('input[name="remember"]');
        await expect(box).toBeVisible();

        // This regressed once: a rule sizing every input caught checkboxes and
        // rendered a giant empty square.
        const width = await box.evaluate((el) => el.getBoundingClientRect().width);
        expect(width).toBeLessThan(40);
    });

    test('the password reveal button shows and hides the password', async ({ page }) => {
        await page.goto('/login');

        const password = page.locator('#password');
        await password.fill('a-secret');
        await expect(password).toHaveAttribute('type', 'password');

        await page.locator('[data-reveal="password"]').click();
        await expect(password).toHaveAttribute('type', 'text');

        await page.locator('[data-reveal="password"]').click();
        await expect(password).toHaveAttribute('type', 'password');
    });

    test('wrong credentials are refused without saying which half was wrong', async ({ page }) => {
        await page.goto('/login');

        await page.locator('#login').fill('nobody@example.test');
        await page.locator('#password').fill('not-the-password');
        await page.getByRole('button', { name: 'Log in' }).click();

        // Naming the wrong field would tell an attacker which addresses exist.
        await expect(page.locator('.notice.bad')).toContainText('do not match our records');
    });

    test('sign-up is reachable from sign-in', async ({ page }) => {
        await page.goto('/login');
        await page.getByRole('link', { name: 'Create an account' }).click();

        await expect(page).toHaveURL(/\/register/);
    });
});
