import { test, expect } from '@playwright/test';

const PHONE = { width: 390, height: 844 };

test.describe('mobile navigation', () => {
    test.use({ viewport: PHONE });

    test('the menu button is an icon, not the word Menu', async ({ page }) => {
        await page.goto('/');

        const toggle = page.locator('.nav-toggle');
        await expect(toggle).toBeVisible();

        // The label survives for screen readers; only the visible text goes.
        await expect(toggle).toHaveAttribute('aria-label', 'Menu');
        expect((await toggle.textContent()).trim()).toBe('');
        await expect(toggle.locator('svg circle')).toHaveCount(3);

        const box = await toggle.boundingBox();
        expect(Math.round(box.width)).toBe(44);
        expect(Math.round(box.height)).toBe(44);
    });

    test('the menu slides open rather than snapping', async ({ page }) => {
        await page.goto('/');

        const links = page.locator('.nav-links');
        const closed = await links.evaluate((el) => ({
            height: el.getBoundingClientRect().height,
            visibility: getComputedStyle(el).visibility,
            transition: getComputedStyle(el).transitionProperty,
        }));

        expect(closed.height).toBe(0);
        // Hidden, not merely flat: max-height alone would leave every link in
        // the tab order of a closed menu.
        expect(closed.visibility).toBe('hidden');
        expect(closed.transition).toContain('max-height');

        await page.locator('.nav-toggle').click();
        await page.waitForTimeout(500);

        const open = await links.evaluate((el) => ({
            height: el.getBoundingClientRect().height,
            visibility: getComputedStyle(el).visibility,
        }));

        expect(open.height).toBeGreaterThan(100);
        expect(open.visibility).toBe('visible');
        await expect(page.locator('.nav-toggle')).toHaveAttribute('aria-expanded', 'true');
    });

    test('no script is blocked by the content security policy', async ({ page }) => {
        const blocked = [];
        page.on('console', (m) => {
            if (m.type() === 'error' && m.text().includes('Content Security Policy')) {
                blocked.push(m.text());
            }
        });

        await page.goto('/');
        await page.locator('.nav-toggle').click();
        await page.waitForTimeout(300);

        // The menu script used to be an inline block, which `script-src 'self'`
        // silently refused: the button existed, looked right, and did nothing.
        expect(blocked).toEqual([]);
    });

    test('every item in the open menu is readable', async ({ page }) => {
        await page.goto('/');
        await page.locator('.nav-toggle').click();
        await page.waitForTimeout(400);

        const links = page.locator('.nav-links a');

        for (let i = 0; i < await links.count(); i++) {
            const item = links.nth(i);
            const { text, color, background } = await item.evaluate((el) => {
                const s = getComputedStyle(el);

                return { text: el.textContent.trim(), color: s.color, background: s.backgroundColor };
            });

            // The Join button went white-on-white here: a mobile rule one point
            // more specific than .btn replaced its background while .btn went on
            // setting the text white.
            expect(text, 'a menu item with no text').not.toBe('');
            expect(color, `"${text}" is the same colour as its background`).not.toBe(background);
        }
    });

    test('both hero buttons sit on one line each', async ({ page }) => {
        await page.goto('/');

        for (const name of ['List your profile', 'Browse campaigns']) {
            const button = page.getByRole('link', { name });
            const { height, lineHeight } = await button.evaluate((el) => ({
                height: el.getBoundingClientRect().height,
                lineHeight: parseFloat(getComputedStyle(el).lineHeight),
            }));

            // Two lines of text would push the button past one line-height plus
            // its padding; "Browse campaigns" used to wrap while its partner
            // did not, so the pair looked mismatched.
            expect(height).toBeLessThan(lineHeight * 2);
        }
    });
});
