// Disposable sites only; credentials and generated fixture path supplied through environment.
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
const { chromium } = createRequire(`${process.cwd()}/package.json`)('playwright');
const base = process.env.VNAP_TEST_URL, fixture = JSON.parse(readFileSync(process.env.VNAP_TEST_FIXTURE, 'utf8').replace(/^\uFEFF/, ''));
if (!base || !process.env.VNAP_TEST_USER || !process.env.VNAP_TEST_PASSWORD) throw new Error('Supply disposable URL/login environment.');
const browser = await chromium.launch(); const page = await browser.newPage();
const errors = [], external = []; let count = 0;
page.on('pageerror', e => errors.push(e.message)); page.on('console', m => { if(m.type()==='error') errors.push(m.text()); });
page.on('request', r => { if(new URL(r.url()).origin!==new URL(base).origin) external.push(r.url()); });
page.on('dialog', d => d.accept());
function check(ok, label) { if (!ok) throw new Error(label); count++; console.log('PASS '+label); }
const successNotice='.woocommerce-message,.wc-block-components-notice-banner.is-success';
const hasError=html=>html.includes('woocommerce-error')||html.includes('wc-block-components-notice-banner is-error');
function edit(prefix) { return fixture.account.replace(/\/?$/, '/')+'edit-address/'+prefix+'/'; }
async function ready(prefix, mode, province) {
    await page.waitForFunction(({prefix,mode,province})=>{const e=document.getElementById('vnap_'+mode+'_'+prefix+'_ward');return e&&!e.disabled&&(!province||e.dataset.vnapProvinceCode===province);},{prefix,mode,province});
}
async function report(order) { const url=new URL(base);url.searchParams.set('vnap_test_report',order.id);url.searchParams.set('key',order.key);return (await page.request.get(url.href)).json(); }
function historical(data) { const {customer_code,customer_id,...order}=data;return JSON.stringify(order); }
async function admin(order) { await page.goto(order.url); await page.locator('#order_data a.edit_address').first().click(); await page.locator('#order_data a.edit_address').nth(1).click(); await ready('billing','admin');await ready('shipping','admin'); }
async function saveAdmin() { await page.locator('#publish,button[name="save"]').first().click();await page.waitForLoadState('load'); }
async function rawAccount(prefix, override) {
    const data=await page.locator('form').filter({has:page.locator('input[name="action"][value="edit_address"]')}).evaluate(form=>Array.from(new FormData(form).entries()));
    const body=new URLSearchParams(data);for(const key of Object.keys(override)){body.delete(key);body.delete(key.replace(/\[\]$/,''));body.set(key,override[key]);}
    return page.request.post(page.url(),{data:body.toString(),headers:{'Content-Type':'application/x-www-form-urlencoded'}});
}
try {
    await page.goto(new URL('wp-login.php',base).href);await page.locator('#user_login').fill(process.env.VNAP_TEST_USER);await page.locator('#user_pass').fill(process.env.VNAP_TEST_PASSWORD);await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);
    const original=await report(fixture.orders.classic);
    await page.goto(base);check(await page.locator('script[src*="woocommerce-address-management.js"]').count()===0,'Management assets absent from ordinary frontend');
    for(const prefix of ['billing','shipping']) {
        await page.goto(edit(prefix));await ready(prefix,'account','79');
        check(await page.locator('#vnap_account_'+prefix+'_ward').inputValue()===String(fixture.a[0].code),'Account saved ward preselected: '+prefix);
        check(await page.locator('#'+prefix+'_state').count()===1,'Exactly one native province control: '+prefix);
        await page.locator('#'+prefix+'_state').selectOption('VN-1');await ready(prefix,'account','1');
        check(await page.locator('#vnap_account_'+prefix+'_ward').inputValue()==='','Province change invalidates ward: '+prefix);
        await page.locator('#vnap_account_'+prefix+'_ward').selectOption(String(fixture.b[0].code));
        await page.locator('#'+prefix+'_city').evaluate(e=>{e.value='FORGED';});
        await page.getByRole('button',{name:'Save address',exact:true}).click();await page.waitForURL(url=>!url.pathname.includes('edit-address/'+prefix));
        await page.locator(successNotice).waitFor();
        check(await page.locator(successNotice).count()>0,'Native account save notice: '+prefix);
        await page.goto(edit(prefix));await ready(prefix,'account','1');
        check(await page.locator('#vnap_'+prefix+'_ward_code').inputValue()===String(fixture.b[0].code)&&await page.locator('#'+prefix+'_city').inputValue()===fixture.b[0].name,'Account reload has canonical code and name: '+prefix);
        const invalid=await rawAccount(prefix,{['vnap_'+prefix+'_ward_code']:String(fixture.a[0].code)});
        check(hasError(await invalid.text()),'Native POST rejects cross-province ward: '+prefix);
        const array=await rawAccount(prefix,{[prefix+'_state[]']:'VN-1'});
        check(hasError(await array.text()),'Native POST rejects state array without fatal: '+prefix);
        await page.goto(edit(prefix));await ready(prefix,'account','1');
        check(await page.locator('#vnap_'+prefix+'_ward_code').inputValue()===String(fixture.b[0].code),'Rejected account POST leaves stored address intact: '+prefix);
        await page.locator('#'+prefix+'_country').selectOption('US');await page.waitForFunction(p=>!document.getElementById(p+'_city_field').hidden,prefix);
        check(await page.locator('#'+prefix+'_city').isVisible(),'Foreign native city usable: '+prefix);
        await page.locator('#'+prefix+'_state').selectOption('CA');await page.locator('#'+prefix+'_city').fill('Los Angeles');await page.locator('#'+prefix+'_postcode').fill('90001');
        await page.getByRole('button',{name:'Save address',exact:true}).click();await page.waitForURL(url=>!url.pathname.includes('edit-address/'+prefix));
        await page.goto(edit(prefix));check(await page.locator('#'+prefix+'_city').inputValue()==='Los Angeles'&&await page.locator('#vnap_'+prefix+'_ward_code').inputValue()==='','Foreign save clears canonical VN code: '+prefix);
        await page.locator('#'+prefix+'_country').selectOption('VN');await page.locator('#'+prefix+'_state').selectOption('VN-79');await ready(prefix,'account','79');
        await page.locator('#vnap_account_'+prefix+'_ward').selectOption(String(fixture.a[1].code));await page.locator('#'+prefix+'_postcode').fill('700000');await page.getByRole('button',{name:'Save address',exact:true}).click();await page.waitForURL(url=>!url.pathname.includes('edit-address/'+prefix));
        await page.locator(successNotice).waitFor();check(await page.locator(successNotice).count()>0,'Foreign to VN saves a new valid selection: '+prefix);
    }
    check(historical(await report(fixture.orders.classic))===historical(original),'My Account edits never rewrite historical order snapshot');
    await page.goto(fixture.account+'edit-address/');const text=await page.locator('.woocommerce-Addresses').innerText();
    check(text.includes(fixture.a[1].name)&&!text.includes('VN-79'),'Account address overview uses readable ward/province names');
    for(const kind of ['classic','block']) {
        check(fixture.orders[kind].actualCheckout===true,'Admin fixture comes from an actual native checkout with order items: '+kind);
        const order=fixture.orders[kind];await admin(order);
        check(await page.locator('#_billing_vnap_ward_code').inputValue()===String(fixture.a[0].code),'Order canonical code loaded: '+kind);
        for(const prefix of ['billing','shipping']) {await page.locator('#_'+prefix+'_state').selectOption('VN-1',{force:true});await ready(prefix,'admin','1');await page.locator('#vnap_admin_'+prefix+'_ward').selectOption(String(fixture.b[1].code));}
        await saveAdmin();const saved=await report(order);
        check(String(saved.billing_code)===String(fixture.b[1].code)&&String(saved.shipping_code)===String(fixture.b[1].code)&&saved.billing_city===fixture.b[1].name,'Admin billing/shipping save and reload canonical: '+kind);
        await admin(order);
        await page.locator('#_billing_vnap_ward_code').evaluate((e,code)=>{e.value=code;},String(fixture.a[0].code));
        await page.locator('#post,form#order').evaluate(form=>form.submit());await page.waitForLoadState('load');
        check(String((await report(order)).billing_code)===String(fixture.b[1].code),'Invalid admin combination cannot replace canonical address: '+kind);
    }
    await admin(fixture.orders.classic);
    await page.locator('.load_customer_billing').click();await page.waitForFunction(code=>document.getElementById('_billing_vnap_ward_code').value===code,String(fixture.a[1].code));await ready('billing','admin','79');
    check(await page.locator('#vnap_admin_billing_ward').inputValue()===String(fixture.a[1].code),'Native load customer billing restores matching ward');
    await page.locator('.load_customer_shipping').click();await page.waitForFunction(code=>document.getElementById('_shipping_vnap_ward_code').value===code,String(fixture.a[1].code));await ready('shipping','admin','79');
    check(await page.locator('#vnap_admin_shipping_ward').inputValue()===String(fixture.a[1].code),'Native load customer shipping restores matching ward');
    await page.locator('#vnap_admin_billing_ward').selectOption(String(fixture.a[0].code));await page.locator('.billing-same-as-shipping').click();await ready('shipping','admin','79');
    check(await page.locator('#_shipping_vnap_ward_code').inputValue()===String(fixture.a[0].code)&&await page.locator('#vnap_admin_shipping_ward').inputValue()===String(fixture.a[0].code),'Native copy billing to shipping copies ward coherently');
    await saveAdmin();check(String((await report(fixture.orders.classic)).shipping_code)===String(fixture.a[0].code),'Copied address persists coherently');
    await admin(fixture.orders.classic);await page.locator('#_billing_country').selectOption('US',{force:true});await page.locator('#_billing_state').selectOption('CA',{force:true});await page.locator('#_billing_city').fill('Los Angeles');await saveAdmin();const foreign=await report(fixture.orders.classic);
    check(!foreign.billing_code&&String(foreign.shipping_code)===String(fixture.a[0].code)&&foreign.billing_city==='Los Angeles','Admin foreign switch clears only edited metadata');
    const obsolete=fixture.orders.obsolete,before=await report(obsolete);await page.goto(obsolete.url);
    check((await page.locator('#order_data').innerText()).includes('Historical obsolete ward'),'Obsolete order snapshot still displayed');
    await admin(obsolete);
    check(!(await page.locator('#vnap_admin_billing_ward').evaluate(e=>e.required)),'Obsolete unchanged address does not block unrelated save');
    check(JSON.stringify(await report(obsolete))===JSON.stringify(before),'Opening obsolete order causes no address mutation');await saveAdmin();check(JSON.stringify(await report(obsolete))===JSON.stringify(before),'No-change save preserves obsolete snapshot');
    await admin(fixture.orders.legacy);check(await page.locator('#vnap_admin_billing_ward').inputValue()==='','Legacy order requires explicit ward rather than guessing');await saveAdmin();check(!(await report(fixture.orders.legacy)).billing_code,'No-change legacy save does not migrate metadata');
    check(errors.length===0,'Browser console and uncaught exceptions clean: '+errors.join(';'));
    check(external.length===0,'Account and admin browsers make zero external requests');
    console.log('Success: '+count+' management browser checks passed.');
} catch(error) {
    console.error('Management failure path',new URL(page.url()).pathname,'notices',await page.locator('.woocommerce-error,.woocommerce-message,.wc-block-components-notice-banner').allTextContents());
    throw error;
} finally { await browser.close(); }
