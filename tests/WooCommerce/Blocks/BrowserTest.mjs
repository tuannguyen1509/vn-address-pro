import { createRequire } from 'node:module';
const require = createRequire(`${process.cwd()}/package.json`);
const { chromium } = require('playwright');
const base = process.env.VNAP_TEST_URL, product = process.env.VNAP_TEST_PRODUCT;
if (!base || !product) throw new Error('Use a disposable Woo site, Setup.php and the offline MU fixture.');
const checkoutUrl = process.env.VNAP_CHECKOUT_URL || new URL('checkout/', base).href;
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
let count = 0, expectingPaymentFailure = false; const errors = [], upstream = [], apiErrors = [], apiEvents=[];
page.on('response',async response=>{if(response.url().includes('/wc/store/')&&response.request().method()==='POST'){try{const body=await response.json();apiEvents.push({path:new URL(response.url()).pathname,status:response.status(),code:body.code,payment:body.payment_result?.payment_status,redirect:!!body.payment_result?.redirect_url,batch:body.responses?.map(r=>({status:r.status,code:r.body?.code}))});}catch{}}});
page.on('response', async response => { if (response.url().includes('/wc/store/') && response.status() >= 400) { try { const body=await response.json(); apiErrors.push({status:response.status(),code:body.code,message:body.message}); } catch {} } });
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => { if (message.type() === 'error' && !(expectingPaymentFailure && /^Failed to load resource:.*40[02]/.test(message.text()))) errors.push(message.text()); });
page.on('request', request => { if (new URL(request.url()).origin !== new URL(base).origin) upstream.push(request.url()); });
const assert = (ok, message) => { if (!ok) throw new Error(message); count++; console.log('PASS ' + message); };
async function nativeSelect(prefix, field, value, label) {
    const select=page.locator(`#${prefix}-${field}`);
    if (await select.count() && await select.evaluate(node=>node.tagName==='SELECT')) { await select.selectOption(value); return; }
    // Woo 8.2 renders accessible comboboxes; newer Woo renders native selects.
    const scope=page.locator('.wc-block-checkout__'+prefix+'-fields');
    const combo=scope.getByRole('combobox').nth(field==='country'?0:1);
    await combo.fill(label);
    await page.getByRole('option',{name:label,exact:true}).click();
}
async function province(prefix, code) {
    const response=await page.request.get(new URL('?rest_route=/vn-address/v1/provinces',base).href);
    const province=(await response.json()).data.find(row=>'VN-'+row.code===code);
    await nativeSelect(prefix,'state',code,province.name);
    await page.waitForFunction(({prefix,code}) => { const select = document.getElementById('vnap_' + prefix + '_ward'); return select && !select.disabled && select.options.length > 1 && select.dataset.vnapProvinceCode===code.slice(3); }, {prefix,code});
}
async function ward(prefix) {
    const value = await page.locator(`#vnap_${prefix}_ward option`).nth(1).getAttribute('value');
    await page.locator(`#vnap_${prefix}_ward`).selectOption(value);
    return value;
}
async function snapshot() { return page.evaluate(() => ({ customer: wp.data.select('wc/store/cart').getCustomerData(), codes: wp.data.select('wc/store/checkout').getExtensionData()['vn-address-pro'] })); }
async function settled() { await page.waitForFunction(() => !wp.data.select('wc/store/checkout').isCalculating() && !wp.data.select('wc/store/cart').isCustomerDataUpdating()); }
async function fillVN() {
    await page.waitForSelector('#vnap_shipping_ward');
    if (!await page.locator('#shipping-first_name').isVisible()) await page.getByRole('button',{name:'Edit shipping address',exact:true}).click();
    await page.locator('#email').fill(`vnap-account-${Date.now()}@example.invalid`);
    await page.locator('#shipping-first_name').fill('Test'); await page.locator('#shipping-last_name').fill('Customer');
    await page.locator('#shipping-address_1').fill('Synthetic test street'); await page.locator('#shipping-phone').fill('0901234567');
    await province('shipping','VN-79'); return ward('shipping');
}
try {
    await page.goto(new URL('?add-to-cart=' + product, base).href); await page.goto(checkoutUrl);
    await page.waitForSelector('#vnap_shipping_ward');
    assert(await page.locator('[data-vnap-block-address="shipping"]').count() === 1, 'Automatic shipping inner block without editor insertion');
    await page.getByRole('button',{name:'VNAP express presence probe',exact:true}).waitFor({state:'visible'});
    assert(await page.getByRole('button',{name:'VNAP express presence probe',exact:true}).count()===1,'Official express button can coexist with address integration');
    assert(await page.locator('script[src*="woocommerce-classic.js"]').count() === 0, 'Classic adapter absent on Blocks');
    assert(await page.locator('#shipping-city').count() === 0, 'VN native city hidden through locale');
    await page.locator('#email').fill(`vnap-block-${Date.now()}@example.invalid`);
    await page.locator('#shipping-first_name').fill('Test'); await page.locator('#shipping-last_name').fill('Customer');
    await page.locator('#shipping-address_1').fill('Synthetic test street');
    await page.locator('#shipping-phone').fill('0901234567');
    await province('shipping', 'VN-79'); const shippingWard = await ward('shipping');
    assert((await snapshot()).customer.shippingAddress.state==='VN-79', 'Native province state contract reused');
    await page.waitForFunction(() => !!wp.data.select('wc/store/checkout').getExtensionData()['vn-address-pro']?.shippingWardCode);
    let state = await snapshot();
    assert(state.codes.shippingWardCode === shippingWard && state.customer.shippingAddress.city !== shippingWard && !!(await page.locator('#vnap_shipping_ward option:checked').textContent()), 'Ward label displayed and canonical code separate from native city');
    assert(state.codes.billingWardCode === shippingWard, 'Use shipping as billing mirrors canonical code');
    const same = page.getByLabel('Use same address for billing', { exact: true });
    await same.uncheck(); await page.waitForSelector('#vnap_billing_ward');
    assert(await page.locator('[data-vnap-block-address="billing"]').count() === 1, 'Automatic independent billing inner block');
    await province('billing', 'VN-1'); const billingWard = await ward('billing');
    state = await snapshot();
    assert(state.codes.billingWardCode === billingWard && state.codes.shippingWardCode === shippingWard, 'Separate billing/shipping selection state');
    await nativeSelect('billing','country','US','United States (US)'); await page.waitForSelector('#billing-city');
    assert(await page.locator('#vnap_billing_ward').count() === 0, 'Foreign address uses native city');
    await nativeSelect('billing','country','VN','Vietnam'); await page.waitForSelector('#vnap_billing_ward');
    assert((await snapshot()).codes.billingWardCode === '', 'Returning to VN does not retain stale ward');
    await same.check();
    await province('shipping', 'VN-1');
    assert((await snapshot()).codes.shippingWardCode === '', 'Province change invalidates old ward');
    await province('shipping', 'VN-79'); await ward('shipping');
    await settled();
    await page.locator('input[value="bacs"]').check();
    await page.getByRole('button',{name:/Place Order/i}).focus();
    await page.waitForTimeout(500); await settled();
    await page.getByRole('button', { name: /Place Order/i }).click();
    await page.waitForURL(/order-received/, { timeout: 45000 });
    assert(/order-received/.test(page.url()), 'Actual guest Block BACS order succeeds offline');
    const orderId = page.url().match(/order-received\/(\d+)/)?.[1]; const key = new URL(page.url()).searchParams.get('key');
    const reportUrl = new URL(base); reportUrl.searchParams.set('vnap_test_report', orderId); reportUrl.searchParams.set('key', key);
    const report = await (await page.request.get(reportUrl.href)).json();
    assert(String(report.billing_code) === shippingWard && String(report.shipping_code) === shippingWard && report.billing_state === 'VN-79', 'Block order uses Phase 7 canonical metadata and state');
    assert(report.formatted.includes(report.billing_city) && !report.formatted.includes('VN-79'), 'Native formatted Block order address is human readable');
    await page.goto(new URL('?add-to-cart='+product,base).href); await page.goto(checkoutUrl);
    const accountWard=await fillVN();
    await page.getByRole('checkbox',{name:/Create an account/i}).check();
    await page.locator('input[value="cod"]').check(); await settled();
    await page.getByRole('button',{name:/Place Order/i}).focus(); await page.waitForTimeout(500); await settled();
    const paymentRoute = url => url.href.includes('/wc/store/') && /checkout(?:\?|$|\/)/.test(url.href);
    await page.route(paymentRoute, async route => {
        if (route.request().method()!=='POST') return route.continue();
        const data=route.request().postDataJSON();
        data.payment_data=[...(data.payment_data||[]),{key:'vnap_test_payment_fail',value:'1'}];
        await route.continue({postData:JSON.stringify(data)});
    });
    expectingPaymentFailure=true;
    const failedPayment=page.waitForResponse(response=>paymentRoute(new URL(response.url()))&&response.request().method()==='POST'&&response.status()>=400);
    await page.getByRole('button',{name:/Place Order/i}).click();
    const failedResult=await(await failedPayment).json();
    assert(['woocommerce_rest_checkout_process_payment_error','vnap_test_payment_failure'].includes(failedResult.code)&&failedResult.message.includes('Disposable payment failure'), 'Actual Store API payment failure before retry');
    await page.unroute(paymentRoute); await settled();
    assert((await snapshot()).codes.shippingWardCode===accountWard,'Payment failure preserves canonical selection');
    expectingPaymentFailure=false;
    await page.getByRole('button',{name:/Place Order/i}).click(); await page.waitForURL(/order-received/,{timeout:45000});
    assert(/order-received/.test(page.url()),'Create-account Block COD checkout succeeds after retry');
    const accountOrder=page.url().match(/order-received\/(\d+)/)?.[1]; const accountKey=new URL(page.url()).searchParams.get('key');
    const accountReportUrl=new URL(base);accountReportUrl.searchParams.set('vnap_test_report',accountOrder);accountReportUrl.searchParams.set('key',accountKey);
    const accountReport=await(await page.request.get(accountReportUrl.href)).json();
    assert(accountReport.customer_id>0&&String(accountReport.customer_code)===accountWard,'New account canonical metadata persisted through CRUD');
    await page.goto(new URL('?add-to-cart='+product,base).href); await page.goto(checkoutUrl);
    await page.waitForFunction(code=>document.getElementById('vnap_shipping_ward')?.value===code,accountWard);
    assert((await snapshot()).codes.shippingWardCode===accountWard,'Logged-in reload restores saved province and ward');
    await page.reload();
    await page.waitForFunction(code=>document.getElementById('vnap_shipping_ward')?.value===code,accountWard);
    assert((await snapshot()).codes.billingWardCode===accountWard,'Repeated reload retains independent customer address contract');
    if (!await page.locator('#shipping-first_name').isVisible()) await page.getByRole('button',{name:'Edit shipping address',exact:true}).click();
    const provinceRows=await(await page.request.get(new URL('?rest_route=/vn-address/v1/provinces',base).href)).json();
    const [a,b]=provinceRows.data.filter(row=>![1,79].includes(row.code));
    await page.evaluate(a=>{
        window.__vnapOriginalFetch=window.fetch;
        window.fetch=(input,init={})=>{
            const url=new URL(input,location.href);
            if(url.searchParams.get('province_code')===String(a)&&url.href.includes('wards')) return new Promise(resolve=>{
                window.__vnapOriginalFetch(input,{...init,signal:undefined}).then(response=>{window.__vnapDelayedA=()=>resolve(response);});
            });
            return window.__vnapOriginalFetch(input,init);
        };
    },a.code);
    await nativeSelect('shipping','state','VN-'+a.code,a.name);
    await page.waitForFunction(()=>typeof window.__vnapDelayedA==='function');
    await nativeSelect('shipping','state','VN-'+b.code,b.name);
    await page.waitForFunction(code=>{const select=document.getElementById('vnap_shipping_ward');return select?.dataset.vnapProvinceCode===String(code)&&!select.disabled;},b.code);
    const finalWard=await ward('shipping');
    await page.evaluate(()=>{window.__vnapDelayedA();window.fetch=window.__vnapOriginalFetch;});
    await page.waitForTimeout(500); await settled();
    assert(await page.locator('#vnap_shipping_ward').getAttribute('data-vnap-province-code')===String(b.code)&&(await snapshot()).codes.shippingWardCode===finalWard,'Obsolete A response cannot overwrite B React selection');
    const serverCart=new URL('?rest_route=/wc/store/v1/cart',base).href;
    await page.waitForFunction(async({url,state})=>{const response=await fetch(url);const cart=await response.json();return cart.shipping_address.state===state;},{url:serverCart,state:'VN-'+b.code},{polling:250});
    assert((await snapshot()).customer.shippingAddress.state==='VN-'+b.code,'Final Store API customer state matches final UI after rapid changes');
    await page.getByRole('button',{name:/Place Order/i}).focus();await page.waitForTimeout(500);await settled();
    await page.getByRole('button',{name:/Place Order/i}).click();await page.waitForURL(/order-received/,{timeout:45000});
    assert(/order-received/.test(page.url()),'Returning logged-in customer checkout succeeds');
    const historical=await(await page.request.get(accountReportUrl.href)).json();
    assert(String(historical.billing_code)===accountWard&&String(historical.customer_code)===finalWard,'Current customer edit does not rewrite historical Block order');
    const virtual=process.env.VNAP_TEST_VIRTUAL_PRODUCT;
    if (!virtual) throw new Error('Set VNAP_TEST_VIRTUAL_PRODUCT from Blocks/Setup.php.');
    await page.goto(new URL('?add-to-cart='+virtual,base).href);await page.goto(checkoutUrl);
    await page.waitForFunction(code=>document.getElementById('vnap_billing_ward')?.value===code,finalWard);
    assert(await page.locator('#vnap_shipping_ward').count()===0,'Virtual cart renders billing ward only');
    await page.locator('input[value="bacs"]').check();await page.getByRole('button',{name:/Place Order/i}).focus();await page.waitForTimeout(500);await settled();
    await page.getByRole('button',{name:/Place Order/i}).click();await page.waitForURL(/order-received/,{timeout:45000});
    assert(/order-received/.test(page.url()),'Billing-only virtual Block order succeeds without shipping ward');
    assert(upstream.length === 0, 'Browser performs zero external requests');
    assert(errors.length === 0, 'Browser console and exceptions clean: ' + errors.join('; '));
    console.log(`Success: ${count} Block browser checks passed.`);
} catch (error) {
    console.error('Failed check',error.message);
    console.error('Checkout diagnostic', await page.locator('[role="alert"]').allTextContents());
    console.error('Browser errors', errors);
    console.error('Store API errors',apiErrors);
    console.error('Store API events',apiEvents.slice(-8));
    console.error('Validation categories', await page.evaluate(() => Object.keys(window.wp?.data?.select('wc/store/validation')?.getValidationErrors?.() || {})));
    console.error('Restore categories',await page.evaluate(()=>{if(!window.wp?.data)return {};const c=wp.data.select('wc/store/cart');return {ready:c.hasFinishedResolution('getCartData'),states:[c.getCustomerData().shippingAddress.country,c.getCustomerData().shippingAddress.state],saved:c.getCartData().extensions?.['vn-address-pro'],loaded:document.getElementById('vnap_shipping_ward')?.dataset.vnapProvinceCode,selected:document.getElementById('vnap_shipping_ward')?.value};}));
    throw error;
} finally { await browser.close(); }
