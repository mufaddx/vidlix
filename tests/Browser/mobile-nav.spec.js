import { test, expect } from '@playwright/test';

test.describe('mobile navigation', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('the menu button is three lines, not a word', async ({ page }) => {
        await page.goto('/');

        const toggle = page.locator('.nav-toggle');
        await expect(toggle).toBeVisible();
        await expect(toggle).toHaveAttribute('aria-label', 'Menu');
        expect((await toggle.textContent()).trim()).toBe('');

        // Three lines in one path, drawn as three horizontal strokes.
        const d = await toggle.locator('svg path').getAttribute('d');
        expect((d.match(/M/g) ?? []).length + (d.match(/h/gi) ?? []).length).toBeGreaterThanOrEqual(6);
    });

    test('the light and dark switch sits beside the menu button', async ({ page }) => {
        await page.goto('/');

        // Beside it, not inside the drawer: changing theme must not mean
        // opening the menu first.
        const theme = page.locator('.nav-actions .theme-toggle');
        await expect(theme).toBeVisible();

        const themeBox = await theme.boundingBox();
        const toggleBox = await page.locator('.nav-toggle').boundingBox();

        // Same row, side by side.
        expect(Math.abs(themeBox.y - toggleBox.y)).toBeLessThan(6);
        expect(themeBox.x).toBeLessThan(toggleBox.x);
    });

    test('the drawer comes in from the side and leaves the page where it was', async ({ page }) => {
        await page.goto('/');

        const hero = page.locator('.hero-stage, .hero').first();
        const before = await hero.boundingBox();

        await page.locator('.nav-toggle').click();
        await page.waitForTimeout(600);

        const after = await hero.boundingBox();

        // The old menu expanded inline and pushed the hero down the screen,
        // which lost the reader's place every time it was opened.
        expect(Math.abs(after.y - before.y)).toBeLessThan(2);

        const drawer = await page.locator('.nav-links').boundingBox();
        expect(drawer.x).toBeGreaterThan(0);
        expect(drawer.x + drawer.width).toBeLessThanOrEqual(391);
        expect(drawer.height).toBeGreaterThan(500);
    });

    test('the page behind the drawer does not scroll', async ({ page }) => {
        await page.goto('/');
        await page.locator('.nav-toggle').click();
        await page.waitForTimeout(400);

        await expect(page.locator('body')).toHaveClass(/nav-locked/);
        expect(await page.evaluate(() => getComputedStyle(document.body).overflow)).toBe('hidden');

        const scrolled = await page.evaluate(() => {
            const before = window.scrollY;
            window.scrollBy(0, 400);

            return { before, after: window.scrollY };
        });
        expect(scrolled.after).toBe(scrolled.before);
    });

    test('tapping outside closes it and gives the page back', async ({ page }) => {
        await page.goto('/');
        await page.locator('.nav-toggle').click();
        await page.waitForTimeout(400);

        await page.locator('.nav-scrim').click({ position: { x: 20, y: 400 } });
        await page.waitForTimeout(500);

        await expect(page.locator('.nav-toggle')).toHaveAttribute('aria-expanded', 'false');
        await expect(page.locator('body')).not.toHaveClass(/nav-locked/);
        // The scrim must not stay over the page swallowing taps.
        await expect(page.locator('.nav-scrim')).toHaveAttribute('hidden', '');
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

    test('every item in the open drawer is readable', async ({ page }) => {
        await page.goto('/');
        await page.locator('.nav-toggle').click();
        await page.waitForTimeout(500);

        const links = page.locator('.nav-links a');

        for (let i = 0; i < await links.count(); i++) {
            const { text, color, background } = await links.nth(i).evaluate((el) => {
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
            const { height, lineHeight } = await page.getByRole('link', { name }).evaluate((el) => ({
                height: el.getBoundingClientRect().height,
                lineHeight: parseFloat(getComputedStyle(el).lineHeight),
            }));

            expect(height).toBeLessThan(lineHeight * 2);
        }
    });
});

test.describe('desktop navigation', () => {
    test.use({ viewport: { width: 1280, height: 900 } });

    test('the links show inline and the drawer furniture stays out of the way', async ({ page }) => {
        await page.goto('/');

        await expect(page.locator('.nav-toggle')).toBeHidden();
        await expect(page.locator('.nav-links')).toBeVisible();
        await expect(page.locator('.nav-actions .theme-toggle')).toBeVisible();

        // The links sit before the theme switch, as they always have.
        const links = await page.locator('.nav-links').boundingBox();
        const actions = await page.locator('.nav-actions').boundingBox();
        expect(links.x).toBeLessThan(actions.x);
    });
});
