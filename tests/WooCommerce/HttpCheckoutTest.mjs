import { createRequire } from 'node:module';
const require = createRequire(`${process.cwd()}/package.json`);
const { request } = require('playwright');
const base = process.env.VNAP_TEST_URL, product = process.env.VNAP_TEST_PRODUCT;
const checkoutUrl = process.env.VNAP_CHECKOUT_URL || new URL('checkout/', base).href;
if (!base || !product) throw new Error('Set VNAP_TEST_URL and VNAP_TEST_PRODUCT.');
let checks = 0;
const assert = (ok, message) => { if (!ok) throw new Error(message); checks++; console.log(`PASS ${message}`); };
const context = await request.newContext();
async function checkout({ signup = false, country = 'VN', payment = 'bacs' } = {}) {
    await context.get(new URL(`?add-to-cart=${product}`, base).href);
    const page = await context.get(checkoutUrl); const html = await page.text();
    const nonce = html.match(/name="woocommerce-process-checkout-nonce" value="([^"]+)"/)?.[1];
    const config = JSON.parse(html.match(/var wc_checkout_params = (\{[^\n]+\});/)?.[1] || '{}');
    assert(!!nonce && !!config.checkout_url, 'Classic form and nonce available');
    const list = await (await context.get(new URL('?rest_route=/vn-address/v1/wards&province_code=79', base).href)).json();
    const ward = list.data[0];
    const email = `vnap-http-${Date.now()}@example.invalid`;
    const data = {
        billing_first_name: 'Test', billing_last_name: 'Customer', billing_country: country,
        billing_state: country === 'VN' ? 'VN-79' : 'CA', billing_city: country === 'VN' ? 'UNTRUSTED NAME' : 'Los Angeles',
        billing_address_1: 'Synthetic test street', billing_postcode: country === 'VN' ? '' : '90001', billing_phone: '0901234567', billing_email: email,
        vnap_billing_ward_code: country === 'VN' ? String(ward.code) : '', payment_method: payment,
        'woocommerce-process-checkout-nonce': nonce, _wp_http_referer: '/checkout/', terms: 'on', ...(signup ? { createaccount: '1' } : {}),
    };
    const reviewUrl = config.wc_ajax_url ? config.wc_ajax_url.replace('%%endpoint%%', 'update_order_review') : config.checkout_url.replace('wc-ajax=checkout', 'wc-ajax=update_order_review');
    const review = await (await context.post(new URL(reviewUrl, checkoutUrl).href, { form: {
        security: config.update_order_review_nonce, post_data: new URLSearchParams(data).toString(),
        country, state: data.billing_state, city: data.billing_city, postcode: data.billing_postcode, address: data.billing_address_1,
        s_country: country, s_state: data.billing_state, s_city: data.billing_city, s_postcode: data.billing_postcode, s_address: data.billing_address_1,
        has_full_address: 'true', payment_method: payment,
    } })).json();
    const fragment = Object.values(review.fragments || {}).join('');
    const method = fragment.match(/value="(flat_rate:\d+)"/)?.[1];
    assert(!!method, 'Country/state produces shipping method');
    data['shipping_method[0]'] = method;
    const result = await (await context.post(new URL(config.checkout_url, checkoutUrl).href, { form: data })).json();
    if (result.result !== 'success') console.log(String(result.messages || '').replace(/<[^>]+>/g, '').trim());
    assert(result.result === 'success' && !!result.redirect, `${signup ? 'Create-account' : country === 'VN' ? 'Logged-in' : 'Foreign'} ${payment} checkout succeeds`);
    const target = new URL(result.redirect); const key = target.searchParams.get('key');
    const id = target.pathname.match(/order-received\/(\d+)/)?.[1] || target.searchParams.get('order-received');
    const reportUrl = new URL(base); reportUrl.searchParams.set('vnap_test_report', id); reportUrl.searchParams.set('key', key);
    const report = await (await context.get(reportUrl.href)).json();
    assert(report.payment === payment, 'Payment method stored by Woo core');
    if (country === 'VN') {
        assert(String(report.billing_code) === String(ward.code) && String(report.shipping_code) === String(ward.code), 'Actual order stores billing and same-as-billing shipping canonical codes');
        assert(report.billing_city === ward.name && !report.formatted.includes('VN-79'), 'Forged city ignored; formatted order address readable');
        assert(Number(report.customer_id) > 0 && String(report.customer_code) === String(ward.code), 'Registered/logged customer receives canonical meta');
    } else {
        assert(!report.billing_code && report.billing_state === 'CA' && report.billing_city === 'Los Angeles', 'Foreign checkout preserves native fields without VN metadata');
    }
    return { report, html };
}
try {
    await checkout({ signup: true, payment: 'bacs' });
    const second = await checkout({ payment: 'cod' });
    assert(second.html.includes('name="vnap_billing_ward_code"') && second.html.includes(`value="${second.report.billing_code}"`), 'Logged-in checkout prepopulates canonical ward metadata');
    await checkout({ country: 'US', payment: 'bacs' });
    console.log(`Success: ${checks} checkout HTTP/account checks passed.`);
} finally { await context.dispose(); }
