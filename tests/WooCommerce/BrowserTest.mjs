import { createRequire } from 'node:module';
const require = createRequire(`${process.cwd()}/package.json`);
const { chromium } = require('playwright');
const base = process.env.VNAP_TEST_URL;
const product = process.env.VNAP_TEST_PRODUCT;
if (!base || !product) throw new Error('Set VNAP_TEST_URL and VNAP_TEST_PRODUCT from disposable Setup.php.');
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext(); const page = await context.newPage();
let count = 0, updates = 0;
const errors = [], checkoutResults = [];
page.on('pageerror', e => errors.push(e.message));
page.on('console', e => { if (e.type() === 'error') errors.push(e.text()); });
page.on('response', async r => { if (r.url().includes('wc-ajax=update_order_review')) updates++; if (r.url().includes('wc-ajax=checkout')) { try { checkoutResults.push(await r.json()); } catch {} } });
const assert = (ok, label) => { if (!ok) throw new Error(label); count++; console.log(`PASS ${label}`); };
const checkoutUrl = process.env.VNAP_CHECKOUT_URL || new URL('checkout/', base).href;
async function setProvince(prefix, state) {
    await page.locator(`#${prefix}_state`).selectOption(state, { force: true });
    await page.waitForFunction(p => { const r = document.querySelector(`#${p}_vnap_ward`); return r && !r.disabled && r.options.length > 1; }, prefix);
}
async function chooseWard(prefix) {
    const value = await page.locator(`#${prefix}_vnap_ward option`).nth(1).getAttribute('value');
    await page.locator(`#${prefix}_vnap_ward`).selectOption(value);
    return value;
}
async function settle() { await page.waitForFunction(() => !jQuery('form.checkout').is('.processing') && !document.querySelector('.blockUI')); }
try {
    await page.goto(new URL(`?add-to-cart=${product}`, base).href);
    await page.goto(checkoutUrl);
    await page.waitForSelector('#billing_vnap_ward'); await settle();
    if (await page.locator('#ship-to-different-address-checkbox').isChecked()) await page.locator('#ship-to-different-address label').click();
    assert(await page.locator('script[src*="woocommerce-classic.js"]').count() === 1, 'Classic adapter enqueued once');
    assert(await page.locator('#billing_state option[value="VN-79"]').count() === 1, 'Province states use stable VN identifiers');
    assert(await page.locator('#billing_city').getAttribute('type') === 'hidden', 'Native city retained as readable snapshot model');
    await page.locator('#billing_first_name').fill('Test'); await page.locator('#billing_last_name').fill('Customer');
    const guestEmail = `guest-${Date.now()}@example.invalid`;
    await page.locator('#billing_email').fill(guestEmail); await page.locator('#billing_phone').fill('0901234567');
    await page.locator('#billing_address_1').fill('Example street 1');
    await setProvince('billing', 'VN-79'); const billingWard = await chooseWard('billing'); await settle();
    assert(await page.locator('#vnap_billing_ward_code').inputValue() === billingWard && (await page.locator('#billing_city').inputValue()).length > 0, 'Ward code and display city synchronized');
    assert((await page.locator('.woocommerce-shipping-methods').innerText()).includes('Province zone'), 'Province-specific shipping zone selected');
    const before = updates;
    await setProvince('billing', 'VN-1'); await chooseWard('billing'); await settle();
    await page.waitForFunction(() => document.querySelector('.woocommerce-shipping-methods')?.textContent.includes('VN zone'));
    assert(updates > before, 'Province/ward changes trigger checkout recalculation');
    assert((await page.locator('.woocommerce-shipping-methods').innerText()).includes('VN zone'), 'Shipping zone changes with province');
    await setProvince('billing', 'VN-79'); await chooseWard('billing'); await settle();
    if (!await page.locator('#ship-to-different-address-checkbox').isChecked()) await page.locator('#ship-to-different-address label').click();
    await page.waitForSelector('.shipping_address', { state: 'visible' });
    await page.locator('#shipping_first_name').fill('Other'); await page.locator('#shipping_last_name').fill('Recipient'); await page.locator('#shipping_address_1').fill('Example shipping 2');
    await setProvince('shipping', 'VN-1'); const shippingWard = await chooseWard('shipping'); await settle();
    assert(await page.locator('#billing_state').inputValue() === 'VN-79' && await page.locator('#vnap_billing_ward_code').inputValue() === billingWard, 'Billing and shipping selections independent');
    assert(await page.locator('#vnap_shipping_ward_code').inputValue() === shippingWard, 'Shipping toggle has independent ward code');
    const requestsBefore = updates;
    await page.evaluate(() => jQuery(document.body).trigger('updated_checkout'));
    await settle();
    assert(await page.locator('#shipping_vnap_ward').inputValue() === shippingWard && updates === requestsBefore, 'Fragment lifecycle re-init preserves state without update loop');
    await page.evaluate(() => {
        const row = document.querySelector('#shipping_city_field');
        const replacement = row.cloneNode(true); replacement.querySelector('.vnap-wc-address')?.remove(); replacement.querySelector('#shipping_city').type = 'text';
        row.replaceWith(replacement);
        const code = document.querySelector('#vnap_shipping_ward_code'); const fresh = code.cloneNode(); fresh.value = ''; code.replaceWith(fresh);
        jQuery(document.body).trigger('updated_checkout');
    });
    await page.waitForFunction(code => document.querySelector('#shipping_vnap_ward')?.value === code, shippingWard);
    assert(await page.locator('#shipping_vnap_ward').count() === 1 && await page.locator('#vnap_shipping_ward_code').inputValue() === shippingWard, 'Replaced address fragment reconnects and restores canonical selection once');
    await page.locator('#billing_country').selectOption('US', { force: true });
    await page.waitForFunction(() => !document.querySelector('#billing_vnap_ward'));
    assert(await page.locator('#billing_city').getAttribute('type') === 'text' && await page.locator('#vnap_billing_ward_code').inputValue() === '', 'VN to US restores native city and clears canonical code');
    await page.locator('#billing_state').selectOption('CA', { force: true }); await page.locator('#billing_city').fill('Los Angeles'); await page.locator('#billing_postcode').fill('90001'); await settle();
    await page.locator('#billing_country').selectOption('VN', { force: true }); await page.waitForSelector('#billing_vnap_ward');
    await setProvince('billing', 'VN-79'); await chooseWard('billing'); await page.locator('#billing_postcode').fill(''); await settle();
    assert(await page.locator('#billing_vnap_ward').count() === 1 && await page.locator('#shipping_vnap_ward').inputValue() === shippingWard, 'US to VN reconnects once, independent shipping preserved');
    const invalid = await page.evaluate(async () => {
        const data = new URLSearchParams(jQuery('form.checkout').serialize()); data.set('vnap_billing_ward_code', '999999');
        const url = wc_checkout_params.checkout_url;
        return (await fetch(url, { method: 'POST', body: data, credentials: 'same-origin' })).json();
    });
    assert(invalid.result === 'failure' && invalid.messages.includes('Phường'), 'Tampered checkout ward rejected server-side');
    const hostile = await page.evaluate(async () => {
        const data = new URLSearchParams(jQuery('form.checkout').serialize()); data.delete('billing_state'); data.append('billing_state[]', 'VN-79');
        return (await fetch(wc_checkout_params.checkout_url, { method: 'POST', body: data, credentials: 'same-origin' })).json();
    });
    assert(hostile.result === 'failure', 'Hostile state array receives controlled checkout validation failure');
    assert(await page.locator('#vnap_shipping_ward_code').inputValue() === shippingWard, 'Valid selections survive validation failure');
    // Test-only gateway failure, toggled by the disposable fixture through a synthetic field.
    await page.evaluate(() => { const marker = document.createElement('input'); marker.type = 'hidden'; marker.name = 'vnap_test_payment_fail'; marker.value = '1'; document.querySelector('form.checkout').append(marker); });
    if (await page.locator('#payment_method_cod').count() && !await page.locator('#payment_method_cod').isChecked()) await page.locator('label[for="payment_method_cod"]').click();
    await settle(); await page.locator('#place_order').click();
    await page.waitForFunction(() => !!document.querySelector('.woocommerce-error,.wc-block-components-notice-banner.is-error'));
    assert(await page.locator('#billing_vnap_ward').inputValue() === billingWard, 'Payment failure keeps valid province/ward selection');
    await page.evaluate(() => document.querySelector('[name="vnap_test_payment_fail"]').remove());
    await page.locator('#place_order').click();
    await page.waitForURL(url => url.href.includes('order-received'), { timeout: 30000 });
    if (await page.locator('form.woocommerce-verify-email').count()) {
        await page.locator('form.woocommerce-verify-email input[name="email"]').fill(guestEmail);
        await Promise.all([page.waitForNavigation(), page.locator('form.woocommerce-verify-email button').click()]);
    }
    let addresses = (await page.locator('address').allTextContents()).join(' ');
    if (!addresses) {
        // Some Woo releases omit guest customer-details HTML. Verify its native formatter through the owned test report.
        const target = new URL(page.url()); const id = target.pathname.match(/order-received\/(\d+)/)?.[1] || target.searchParams.get('order-received');
        const report = new URL(base); report.searchParams.set('vnap_test_report', id); report.searchParams.set('key', target.searchParams.get('key'));
        addresses = (await (await context.request.get(report.href)).json()).formatted;
    }
    assert(addresses.includes('Phường') || addresses.includes('Xã'), 'Successful guest checkout/native formatted address readable');
    assert(!addresses.includes('VN-79'), 'Thank-you address does not expose state identifier');
    assert(errors.length === 0, 'Browser console and exception channels clean');
    console.log(`Success: ${count} Classic checkout browser checks passed.`);
} catch (error) {
    console.log(JSON.stringify({ errors, statuses: checkoutResults.map(r => r.result), headings: await page.locator('h1,h2').allTextContents(), verify: await page.locator('form.woocommerce-verify-email').count() }));
    throw error;
} finally { await browser.close(); }
