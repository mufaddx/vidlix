import { test, expect } from '@playwright/test';

/**
 * What a visitor who has never signed in actually sees.
 */
test.describe('public pages', () => {
    test('the homepage states how money is handled', async ({ page }) => {
        await page.goto('/');

        await expect(page.getByRole('heading', { name: 'How the money works' })).toBeVisible();
        await expect(page.getByText('Nobody at Vidlix can mark money as received.')).toBeVisible();
    });

    test('the proof row shows counted figures rather than claims', async ({ page }) => {
        await page.goto('/');

        const figures = page.locator('.stat-figure');
        await expect(figures).toHaveCount(4);

        // A fresh database has nothing in it, and the page is expected to say
        // so rather than invent a number.
        for (const text of await figures.allTextContents()) {
            expect(text.trim()).toMatch(/^[\d,]+$/);
        }
    });

    test('the page does not scroll sideways on a phone', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/');

        const overflows = await page.evaluate(
            () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        );
        expect(overflows).toBe(false);
    });

    test('a button stays readable when hovered', async ({ page }) => {
        await page.goto('/');

        const button = page.locator('.btn').first();
        await button.hover();

        // This regressed once: a secondary button went transparent on hover
        // and its text disappeared.
        const { color, background } = await button.evaluate((el) => {
            const style = getComputedStyle(el);

            return { color: style.color, background: style.backgroundColor };
        });
        expect(color).not.toBe(background);
    });
});
