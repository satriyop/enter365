import fs from 'node:fs';
import path from 'node:path';

const WRITE_RPC_METHODS = new Set([
    'create',
    'write',
    'unlink',
    'copy',
    'web_save',
    'web_update',
    'create_or_replace',
    'name_create',
    'copy_data',
    'action_confirm',
    'action_post',
    'action_cancel',
    'action_draft',
    'action_validate',
    'action_done',
    'action_invoice_open',
    'action_invoice_sent',
    'button_validate',
    'button_confirm',
]);

const LOGIN_URL_RE = /\/web\/login\b|\/web\/session\/authenticate\b|\/web\/session\/get_session_info\b/i;
const CALL_BUTTON_RE = /\/web\/dataset\/call_button\b/i;
const ACTION_RUN_RE = /\/web\/action\/run\b/i;
const CALL_KW_WRITE_RE = /\/call_kw\/[^/?#]+\/(create|write|unlink|copy|web_save|web_update|name_create|create_or_replace)(?:\/|\?|#|$)/i;

/**
 * Classify an Odoo HTTP request as a write. JSON-RPC POSTs that only read
 * (search_read, get_views, …) are allowed so the UI can render. Login POSTs
 * are allowed. Everything else that mutates is blocked.
 *
 * @param {{ method: string, url: string, postData?: string|null }} request
 */
export function isOdooWriteRequest(request) {
    const method = String(request?.method ?? 'GET').toUpperCase();
    const url = String(request?.url ?? '');

    if (method === 'GET' || method === 'HEAD' || method === 'OPTIONS') {
        return false;
    }

    if (LOGIN_URL_RE.test(url)) {
        return false;
    }

    if (method === 'DELETE' || method === 'PUT' || method === 'PATCH') {
        return true;
    }

    if (method !== 'POST') {
        return true;
    }

    if (CALL_BUTTON_RE.test(url) || ACTION_RUN_RE.test(url) || CALL_KW_WRITE_RE.test(url)) {
        return true;
    }

    const postData = request?.postData;
    if (postData) {
        try {
            const body = JSON.parse(postData);
            const rpcMethod = body?.params?.method ?? body?.method;
            if (typeof rpcMethod === 'string') {
                const normalized = rpcMethod.toLowerCase();
                if (WRITE_RPC_METHODS.has(normalized) || normalized.startsWith('button_')) {
                    return true;
                }
            }
        } catch {
            // Non-JSON bodies (login form) are not treated as writes here.
        }
    }

    return false;
}

export async function installOdooReadOnlyGuard(context, blocked) {
    await context.route('**/*', async (route) => {
        const request = route.request();
        const descriptor = {
            method: request.method(),
            url: request.url(),
            postData: request.postData(),
        };

        if (isOdooWriteRequest(descriptor)) {
            blocked.push({
                method: descriptor.method,
                url: descriptor.url,
                at: new Date().toISOString(),
            });
            await route.abort('blockedbyclient');

            return;
        }

        await route.continue();
    });
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

export async function waitForSettle(page, { timeout = 15000 } = {}) {
    await page.waitForLoadState('domcontentloaded', { timeout }).catch(() => {});
    await page.waitForLoadState('networkidle', { timeout: Math.min(timeout, 8000) }).catch(() => {});
    await sleep(1200);
    await page.waitForFunction(() => {
        const loaders = document.querySelectorAll('.o_loading, .o_spinner, [class*="loading"], [class*="spinner"]');

        return [...loaders].every((el) => !el.offsetParent);
    }, { timeout: 8000 }).catch(() => {});
}

export async function extractSignals(page) {
    return page.evaluate(() => {
        const textOf = (el) => (el?.innerText || el?.textContent || '').replace(/\s+/g, ' ').trim();
        const unique = (values, limit) => {
            const seen = new Set();
            const out = [];
            for (const value of values) {
                if (!value || value.length > 80) {
                    continue;
                }
                const key = value.toLowerCase();
                if (seen.has(key)) {
                    continue;
                }
                seen.add(key);
                out.push(value);
                if (out.length >= limit) {
                    break;
                }
            }

            return out;
        };

        const chrome = unique(
            [...document.querySelectorAll('nav a, [role="navigation"] a, aside a, header a, .o_menu_apps, .o_menu_sections a, [data-menu-xmlid]')]
                .map(textOf),
            40,
        );
        const filters = unique(
            [...document.querySelectorAll('input, select, [role="search"], [placeholder]')]
                .map((el) => el.getAttribute('placeholder') || el.getAttribute('aria-label') || el.getAttribute('name') || el.id)
                .filter(Boolean),
            30,
        );
        const columns = unique(
            [...document.querySelectorAll('th, [role="columnheader"]')].map(textOf),
            40,
        );
        const badges = unique(
            [...document.querySelectorAll('[class*="badge"], [class*="chip"], [class*="pill"], [data-status]')]
                .map(textOf),
            30,
        );
        const actions = unique(
            [...document.querySelectorAll('button, [role="button"], a.btn, [class*="btn"]')].map(textOf),
            40,
        );
        const labels = unique(
            [...document.querySelectorAll('h1, h2, label, [class*="page-title"], .o_last_breadcrumb_item')].map(textOf),
            40,
        );
        const status_workflow = unique(
            [...document.querySelectorAll('[class*="status"], [data-state], .o_arrow_button, .o_statusbar_status, [class*="badge"]')]
                .map(textOf),
            30,
        );

        return {
            title: document.title,
            heading: textOf(document.querySelector('h1')),
            chrome,
            filters,
            columns,
            badges,
            actions,
            labels,
            status_workflow,
        };
    });
}

async function fillFirst(page, selectors, value) {
    for (const selector of selectors) {
        const locator = page.locator(selector).first();
        if (await locator.count()) {
            await locator.fill(value);

            return true;
        }
    }

    return false;
}

async function clickFirst(page, selectors) {
    for (const selector of selectors) {
        const locator = page.locator(selector).first();
        if (await locator.count()) {
            await locator.click();

            return true;
        }
    }

    return false;
}

export async function loginOdoo(page, { baseUrl, email, password }) {
    await page.goto(`${baseUrl}/web/login`, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await waitForSettle(page);

    if (!/login/i.test(page.url())) {
        return;
    }

    const filledLogin = await fillFirst(page, ['input[name="login"]', '#login', 'input[type="email"]'], email);
    const filledPassword = await fillFirst(page, ['input[name="password"]', '#password', 'input[type="password"]'], password);
    if (!filledLogin || !filledPassword) {
        throw new Error('Odoo login form fields were not found');
    }

    const submitted = await clickFirst(page, ['button[type="submit"]', 'button:has-text("Log in")', 'button:has-text("Login")']);
    if (!submitted) {
        await page.locator('input[name="password"]').press('Enter');
    }

    await page.waitForURL((url) => !/\/web\/login\b/i.test(url.pathname), { timeout: 45000 });
    await waitForSettle(page);
}

export async function loginEnter365(page, { baseUrl, email, password }) {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await waitForSettle(page);

    if (!/login/i.test(page.url())) {
        return;
    }

    const filledEmail = await fillFirst(page, ['[data-testid="login-email"]', 'input[type="email"]', 'input[name="email"]'], email);
    const filledPassword = await fillFirst(page, ['[data-testid="login-password"]', 'input[type="password"]', 'input[name="password"]'], password);
    if (!filledEmail || !filledPassword) {
        throw new Error('enter365 login form fields were not found');
    }

    const submitted = await clickFirst(page, ['[data-testid="login-submit"]', 'button[type="submit"]', 'button:has-text("Sign in")']);
    if (!submitted) {
        await page.locator('[data-testid="login-password"]').press('Enter');
    }

    await page.waitForURL((url) => !/\/login\b/i.test(url.pathname), { timeout: 45000 });
    await waitForSettle(page);
}

export function joinUrl(baseUrl, urlPath) {
    if (/^https?:\/\//i.test(urlPath)) {
        return urlPath;
    }

    const base = String(baseUrl).replace(/\/$/, '');
    const suffix = urlPath.startsWith('/') ? urlPath : `/${urlPath}`;

    return `${base}${suffix}`;
}

export async function capturePage(page, { url, screenshotPath }) {
    const timestamp = new Date().toISOString();
    let navigationError = null;

    try {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
        await waitForSettle(page);
    } catch (error) {
        navigationError = error;
    }

    fs.mkdirSync(path.dirname(screenshotPath), { recursive: true });
    await page.screenshot({ path: screenshotPath, fullPage: true });

    const signals = await extractSignals(page).catch(() => ({
        title: '',
        heading: '',
        chrome: [],
        filters: [],
        columns: [],
        badges: [],
        actions: [],
        labels: [],
        status_workflow: [],
    }));

    return {
        meta: {
            url: page.url(),
            title: await page.title().catch(() => signals.title || ''),
            timestamp,
            screenshot: screenshotPath,
        },
        signals,
        navigationError,
    };
}

export async function launchDualBrowser({ headed = false } = {}) {
    let playwright;
    try {
        playwright = await import('playwright');
    } catch (error) {
        const wrapped = new Error('Playwright is not installed. Run: npm install && npx playwright install chromium');
        wrapped.cause = error;
        throw wrapped;
    }

    try {
        const browser = await playwright.chromium.launch({ headless: !headed });
        const viewport = { width: 1440, height: 900 };
        const odooContext = await browser.newContext({ viewport, ignoreHTTPSErrors: true });
        const enter365Context = await browser.newContext({ viewport, ignoreHTTPSErrors: true });
        const blockedWrites = [];
        await installOdooReadOnlyGuard(odooContext, blockedWrites);

        return {
            browser,
            odooPage: await odooContext.newPage(),
            enter365Page: await enter365Context.newPage(),
            blockedWrites,
            async close() {
                await browser.close();
            },
        };
    } catch (error) {
        const message = String(error?.message ?? error);
        if (/Executable doesn't exist|browserType\.launch/i.test(message)) {
            const wrapped = new Error('Playwright Chromium is not installed. Run: npx playwright install chromium');
            wrapped.cause = error;
            throw wrapped;
        }
        throw error;
    }
}
