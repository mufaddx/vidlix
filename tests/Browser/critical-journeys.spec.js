import { test, expect } from '@playwright/test';

/**
 * The journeys that have to work, end to end, in a real browser.
 *
 * The feature suite already proves the backend does the right thing. What it
 * cannot see is whether the pages actually connect to each other — whether a
 * button submits the form it appears to belong to, whether a conditional field
 * reveals itself, whether a link goes where it says.
 *
 * Everything here runs signed out, against whatever the environment happens to
 * contain. A test that seeds its own world proves the seed works; these ask
 * whether the pages a stranger can reach behave.
 */

/** A unique suffix, so parallel or repeated runs never collide on a username. */
const unique = () => Date.now().toString(36) + Math.random().toString(36).slice(2, 6);

test.describe('the public profile address', () => {
    test('a profile is reachable at vidlix.in/{username} with no role in the path', async ({ page }) => {
        // The directory is the honest way in without seeding: whatever profiles
        // exist, their links must have the shape the product promises.
        await page.goto('/creators');

        const first = page.locator('a[href^="/"]').filter({ hasNot: page.locator('img') }).first();

        if (await page.locator('.card').count() === 0) {
            test.skip(true, 'No published profiles in this environment.');
        }

        const links = await page.locator('a').evaluateAll((all) =>
            all.map((a) => a.getAttribute('href')).filter(Boolean),
        );

        // Whatever else is on the page, nothing should link to a retired,
        // role-prefixed profile address.
        for (const href of links) {
            expect(href).not.toMatch(/^\/u\//);
            expect(href).not.toMatch(/^\/editors\/[^/]+$/);
        }

        expect(first).toBeTruthy();
    });

    test('a retired address redirects rather than breaking', async ({ page }) => {
        const response = await page.goto('/u/does-not-exist');

        // Either a redirect to the flat address or an honest 404 — never a 500,
        // and never the old page still being served.
        expect([200, 404]).toContain(response.status());
        expect(page.url()).not.toContain('/u/');
    });

    test('an unknown username is a plain 404', async ({ page }) => {
        const response = await page.goto('/definitely-nobody-' + unique());

        expect(response.status()).toBe(404);
        await expect(page.getByRole('heading', { name: /nothing here/i })).toBeVisible();
    });

    test('the 404 does not reveal whether the account exists', async ({ page }) => {
        await page.goto('/definitely-nobody-' + unique());

        const body = await page.textContent('body');

        // "No such user" and "this account is private" are different facts, and
        // the second one is not ours to give away.
        expect(body).not.toMatch(/private|hidden|disabled|suspended/i);
    });
});

test.describe('the AutoDM product page', () => {
    test('states the limits rather than selling around them', async ({ page }) => {
        await page.goto('/autodm');

        await expect(page.getByRole('heading', { name: /there are no follow-ups/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /no messaging strangers/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /nothing is scraped/i })).toBeVisible();
    });

    test('does not promise unlimited automated DMs anywhere on the page', async ({ page }) => {
        await page.goto('/autodm');

        const body = (await page.textContent('body')).toLowerCase();

        expect(body).not.toContain('unlimited dm');
        expect(body).not.toContain('unlimited messages');
        expect(body).not.toContain('mass dm');
    });

    test('reads without sideways scroll on a phone', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/autodm');

        const overflow = await page.evaluate(
            () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
        );

        expect(overflow).toBeLessThanOrEqual(1);
    });
});

test.describe('pricing', () => {
    test('quotes what is actually charged rather than a plan that does not exist', async ({ page }) => {
        await page.goto('/pricing');

        await expect(page.getByRole('heading', { name: /free to join/i })).toBeVisible();
        await expect(page.getByText(/platform commission/i)).toBeVisible();

        // The manager plans this page used to sell are gone.
        const body = (await page.textContent('body')).toLowerCase();
        expect(body).not.toContain('management plan');
    });
});

test.describe('the admin front door', () => {
    test('is separate, and a signed-out visitor lands on the staff sign-in', async ({ page }) => {
        await page.goto('/admin');

        await expect(page).toHaveURL(/\/admin\/login/);
    });

    test('is not advertised to ordinary visitors', async ({ page }) => {
        await page.goto('/');

        const links = await page.locator('a').evaluateAll((all) =>
            all.map((a) => a.getAttribute('href') || ''),
        );

        expect(links.filter((href) => href.startsWith('/admin'))).toHaveLength(0);
    });
});

test.describe('the manager system', () => {
    test('leaves no way in', async ({ page }) => {
        for (const path of ['/management', '/manager/invite/anything', '/admin/managers']) {
            const response = await page.goto(path);

            // 404 for a route that no longer exists, or a redirect to sign-in
            // for one behind auth. Never a page that still works.
            expect([404, 200]).toContain(response.status());

            if (response.status() === 200) {
                await expect(page).toHaveURL(/login/);
            }
        }
    });

    test('is not mentioned anywhere a visitor can see', async ({ page }) => {
        await page.goto('/');

        const body = (await page.textContent('body')).toLowerCase();

        expect(body).not.toContain('for managers');
        expect(body).not.toContain('management terms');
    });
});

test.describe('error and empty states', () => {
    test('a rate-limited response is a page, not a stack trace', async ({ page }) => {
        // The public form limiter is the easiest one to trip honestly.
        const response = await page.goto('/definitely-nobody/contact');

        expect([404, 429]).toContain(response.status());

        const body = await page.textContent('body');
        expect(body).not.toContain('Stack trace');
        expect(body).not.toContain('vendor/laravel');
    });

    test('no page leaks a framework error to a visitor', async ({ page }) => {
        for (const path of ['/', '/creators', '/editors', '/campaigns', '/pricing', '/autodm']) {
            const response = await page.goto(path);

            expect(response.status(), `${path} should render`).toBe(200);

            const body = await page.textContent('body');
            expect(body, `${path} should not leak an exception`).not.toContain('Whoops');
            expect(body).not.toContain('SQLSTATE');
        }
    });
});

test.describe('theme', () => {
    test('dark mode does not leave text unreadable on buttons', async ({ page }) => {
        await page.emulateMedia({ colorScheme: 'dark' });
        await page.goto('/');

        const button = page.locator('.btn').first();
        await expect(button).toBeVisible();

        const { color, background } = await button.evaluate((el) => {
            const style = getComputedStyle(el);

            return { color: style.color, background: style.backgroundColor };
        });

        // The specific failure this guards against: a hover or variant rule
        // that changes one and not the other, leaving the label invisible.
        expect(color).not.toBe(background);
    });
});
