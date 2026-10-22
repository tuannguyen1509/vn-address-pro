import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
const require = createRequire(`${process.cwd()}/package.json`);
const { chromium } = require('playwright');
const base = process.env.VNAP_TEST_URL, user = process.env.VNAP_TEST_USER, password = process.env.VNAP_TEST_PASSWORD;
const fixture = JSON.parse(readFileSync(process.env.VNAP_TEST_FIXTURE, 'utf8'));
const browser = await chromium.launch({ headless: true }); const page = await browser.newPage();
const errors = []; page.on('pageerror', error => errors.push(error.message));
let count = 0; const check = (ok, text) => { if (!ok) throw new Error(text); count++; console.log('PASS ' + text); };
const url = new URL('wp-admin/options-general.php?page=vn-address-pro&tab=migration', base).href;
const scanForm = () => page.locator('form').filter({has: page.locator('input[name="vnap_migration_command"][value="scan"]')}).last();
const applyForm = () => page.locator('form').filter({has: page.locator('input[name="vnap_migration_command"][value="apply"]')});
async function scan(type) {
    await page.goto(url); const form = scanForm(); await form.locator('[name="mode"]').selectOption(type === 'customer' ? 'customers' : 'orders');
    await form.locator('[name="addresses"]').selectOption('billing'); await form.locator('[name="batch"]').fill('50');
    await Promise.all([page.waitForNavigation(), form.locator('[type="submit"]').click()]);
    check(await applyForm().count() === 1, type + ' scan produces preview');
    check(await page.locator('[name="selected[]"]:checked').count() === 0, 'Nothing preselected');
    check(!await applyForm().locator('[name="medium"]').isChecked(), 'HIGH-only default');
    check(!await page.content().then(t => t.includes('Do not expose synthetic')), 'Preview excludes unrelated PII');
    return page.locator('tr[data-vnap-migration-index]').filter({hasText: `${type} / ${fixture[type]} / billing`});
}
try {
    await page.goto(new URL('wp-login.php', base).href); await page.locator('#user_login').fill(user); await page.locator('#user_pass').fill(password);
    await Promise.all([page.waitForNavigation(), page.locator('#wp-submit').click()]);
    await page.goto(url); check(await page.locator('.vnap-migration').count() === 1, 'Migration tab available to admin');
    check(await scanForm().locator('[name="mode"]').inputValue() === 'customers', 'Default source is Customers');
    check(!await scanForm().locator('[name="order_display"]').isChecked(), 'Historical display conversion defaults off');
    check(await page.locator('script[src*="vn-address-pro"][src*="migration"],link[rel="stylesheet"][href*="vn-address-pro"][href*="migration"]').count() === 0, 'No migration asset bundle');
    let row = await scan('customer'); check(await row.count() === 1 && (await row.innerText()).includes('MIGRATABLE_HIGH'), 'Customer exact candidate reviewed in preview');
    await row.locator('details').click(); check((await row.locator('pre').innerText()).includes('ward_meta'), 'Exact before/after changes visible');
    await row.locator('[name="selected[]"]').check(); await applyForm().locator('[name="confirm"]').check();
    await Promise.all([page.waitForNavigation(), applyForm().locator('[type="submit"]').click()]);
    check((await page.locator('.vnap-migration-run').first().innerText()).includes(`customer / ${fixture.customer} / billing: APPLIED`), 'Real customer POST applies selected record');
    row = await scan('order'); await row.locator('[name="selected[]"]').check(); await applyForm().locator('[name="confirm"]').check();
    await Promise.all([page.waitForNavigation(), applyForm().locator('[type="submit"]').click()]);
    check((await page.locator('.notice-error').innerText()).includes('order_confirmation'), 'Order POST blocked without stronger confirmation');
    row = await scan('order'); await row.locator('[name="selected[]"]').check(); await applyForm().locator('[name="confirm"]').check(); await applyForm().locator('[name="confirm_orders"]').check();
    await Promise.all([page.waitForNavigation(), applyForm().locator('[type="submit"]').click()]);
    check((await page.locator('.vnap-migration-run').first().innerText()).includes(`order / ${fixture.order} / billing: APPLIED`), 'Real historical order POST applies codes after review');
    const denied = await page.request.post(url, {form:{vnap_migration_command:'scan',_wpnonce:'invalid'}}); check(denied.status() === 403, 'Invalid nonce rejected over HTTP');
    check(errors.length === 0, 'No browser exceptions'); console.log(`Success: ${count} migration browser checks passed.`);
} finally { await browser.close(); }
