import {createRequire} from 'node:module';
const require = createRequire(process.cwd() + '/package.json');
const {chromium} = require('playwright');
const url = process.env.VNAP_TEST_URL;
if (!url) throw new Error('VNAP_TEST_URL is required');
let count = 0;
function check(ok, label) {if (!ok) throw new Error(label); console.log('PASS ' + label); count++;}
const browser = await chromium.launch({headless: true});
try {
    const page = await browser.newPage(); const errors = []; let external = 0;
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', route => {if (new URL(route.request().url()).origin !== new URL(url).origin) {external++; return route.abort();} return route.continue();});
    await page.goto(url);
    const forms = page.locator('form.frm-fluent-form');
    check(await forms.count() === 2, 'Two real Fluent Forms render');
    const form = forms.nth(0), other = forms.nth(1);
    const p = form.locator('[name="home_province"]'), w = form.locator('[name="home_ward"]');
    check(await p.locator('option').count() > 30 && await w.isDisabled(), 'Province options ready, ward initially disabled');
    check(await w.locator('option').count() === 1, 'No all-wards initial payload');
    await p.selectOption('79'); await page.waitForFunction(() => document.querySelector('form.frm-fluent-form [name="home_ward"]').options.length > 1);
    const ward = await w.locator('option').nth(1).getAttribute('value'); await w.selectOption(ward);
    check(await other.locator('[name="home_province"]').inputValue() === '', 'Multiple forms isolated');
    const hiddenProvince = other.locator('[name="conditional_province"]');
    check(await hiddenProvince.isHidden(), 'Native conditional group initially hidden');
    await other.locator('[name="contact_name"]').fill('Show');
    await other.locator('[name="contact_name"]').press('Tab');
    await hiddenProvince.waitFor({state:'visible'});
    await hiddenProvince.selectOption('79');
    await page.waitForFunction(() => document.querySelector('[name="conditional_ward"]').options.length > 1);
    check(await other.locator('[name="conditional_ward"]').isEnabled(), 'Native conditional show connects dependent ward');
    await other.locator('[name="contact_name"]').fill('Hide'); await other.locator('[name="contact_name"]').press('Tab');
    await hiddenProvince.waitFor({state:'hidden'});
    const inactiveResponse = page.waitForResponse(r => r.url().includes('admin-ajax.php') && r.request().postData()?.includes('action=fluentform_submit'));
    await other.locator('[type="submit"]').click();
    const inactive = await (await inactiveResponse).json();
    check(!!(inactive.data || inactive).insert_id, 'Native hidden required group is not force-validated');
    await page.waitForFunction(() => !window.ff_sumitting_form);
    const cf7 = page.locator('form.wpcf7-form');
    if (await cf7.count()) {
        await cf7.locator('[name="cf_province"]').selectOption('1');
        await page.waitForFunction(() => document.querySelector('[name="cf_ward"]').options.length > 1);
        check(await p.inputValue() === '79' && await w.inputValue() === ward, 'CF7, Fluent and shortcode share a page independently');
    }
    await form.locator('[name="office_province"]').selectOption('1');
    await page.waitForFunction(() => document.querySelector('form.frm-fluent-form [name="office_ward"]').options.length > 1);
    check(await w.inputValue() === ward, 'Multiple groups isolated');
    await form.locator('[name="office_province"]').selectOption('');
    // Native unrelated required error must preserve the valid address selection.
    await form.locator('[type="submit"]').click();
    await page.waitForFunction(() => document.querySelector('form.frm-fluent-form .ff-el-is-error'));
    check(await p.inputValue() === '79' && await w.inputValue() === ward, 'Unrelated native validation failure preserves selection');
    await form.locator('[name="contact_name"]').fill('Synthetic Test');
    await form.locator('[name="contact_name"]').press('Tab');
    await page.waitForFunction(() => !window.ff_sumitting_form);
    const submitted = page.waitForResponse(r => r.url().includes('admin-ajax.php') && r.request().postData()?.includes('action=fluentform_submit'));
    await form.locator('[type="submit"]').click();
    const response = await submitted; const result = await response.json();
    const payload = result.data || result; // Fluent 5 uses an unwrapped native AJAX response.
    check(result.success !== false && !!payload.insert_id, 'Native Fluent submission succeeds offline');
    await page.waitForFunction(() => document.querySelector('form.frm-fluent-form [name="home_province"]').value === '');
    check(await w.inputValue() === '' && await w.isDisabled(), 'Native success/reset clears ward');
    await p.selectOption('1'); await page.waitForFunction(() => document.querySelector('form.frm-fluent-form [name="home_ward"]').options.length > 1);
    check(await w.inputValue() === '', 'Province change clears old ward selection');
    await form.evaluate(node => node.reset());
    await page.waitForFunction(() => document.querySelector('form.frm-fluent-form [name="home_ward"]').disabled);
    check(await p.inputValue() === '' && await w.inputValue() === '', 'Native manual reset clears both controls');
    check(await page.locator('[data-vnap-address]').count() === 1, 'Generic shortcode coexists');
    check(await page.locator('script[src*="/address-data-client.js"]').count() === 1 && await page.locator('script[src*="/vn-address.js"]').count() === 1, 'Shared assets loaded once');
    // Abort-insensitive transport deliberately resolves obsolete A after B, bypassing cache with fresh page.
    await page.reload();
    await page.evaluate(() => {
        const real = window.fetch.bind(window);
        window.fetch = async (resource, init = {}) => {
            if (!String(resource).includes('/wards')) return real(resource, init);
            const province = new URL(resource).searchParams.get('province_code');
            const reply = await real(resource, {...init, signal: undefined});
            if (province === '1') await new Promise(resolve => setTimeout(resolve, 600));
            return reply;
        };
    });
    await p.selectOption('1'); await p.selectOption('79');
    await page.waitForFunction(() => document.querySelector('form.frm-fluent-form [name="home_ward"]').options.length > 1);
    await page.waitForTimeout(900);
    check(await w.locator(`option[value="${ward}"]`).count() === 1 && await p.inputValue() === '79', 'Obsolete request cannot replace current province wards');
    // Direct native AJAX submissions bypass browser validation.
    const base = await form.evaluate(node => ({id: node.dataset.form_id, data: [...new FormData(node).entries()]}));
    for (const bad of [ {'home_province':'999999'}, {'home_ward':'999999'}, {'home_province[]':'79'}, {'home_ward[]':ward}, {'home_province':'1','home_ward':ward} ]) {
        const entries = base.data.filter(([key]) => !['home_province','home_ward','contact_name'].includes(key));
        entries.push(['contact_name','Synthetic Test']);
        if (!('home_province[]' in bad)) entries.push(['home_province',bad.home_province ?? '79']);
        if (!('home_ward[]' in bad)) entries.push(['home_ward',bad.home_ward ?? ward]);
        for (const [key,value] of Object.entries(bad)) if (key.endsWith('[]')) entries.push([key,value]);
        const ajax = await page.evaluate(() => window.fluentFormVars.ajaxUrl);
        const r = await page.request.post(ajax, {form: {action:'fluentform_submit', form_id:base.id, data:new URLSearchParams(entries).toString()}});
        const body = await r.json(); check(!body.insert_id && !body.data?.insert_id && (r.status() >= 400 || body.success === false || !!body.errors), 'Native server rejects unknown/array/mismatched payload');
    }
    check(errors.length === 0, 'Browser has no JavaScript exceptions');
    check(external === 0, 'No outbound browser request');
    console.log(JSON.stringify({checks:count,entry:payload.insert_id,ward}));
} finally {await browser.close();}
