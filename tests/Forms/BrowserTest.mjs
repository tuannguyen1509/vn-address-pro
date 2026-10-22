import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
const require = createRequire(`${process.cwd()}/package.json`); const { chromium } = require('playwright');
const fixture = JSON.parse(readFileSync(process.env.VNAP_TEST_FIXTURE, 'utf8'));
const browser = await chromium.launch({headless:true}); const page = await browser.newPage();
let checks = 0; const errors = [], external = [];
page.on('pageerror', e => errors.push(e.message)); page.on('console', m => { if(m.type()==='error') errors.push(m.text()); });
page.on('request', r => { if(new URL(r.url()).origin !== new URL(fixture.page).origin) external.push(r.url()); });
const check = (ok, label) => { if(!ok) throw new Error(label); checks++; console.log('PASS ' + label); };
const form = i => page.locator('form.wpcf7-form').nth(i);
const field = (i,n) => form(i).locator(`[name="${n}"]`);
async function choose(i, name, province) {
    await field(i,name).selectOption(province);
    const ward = name === 'home' ? 'home_ward' : 'office_ward';
    await page.waitForFunction(({i,ward}) => {const w=document.querySelectorAll('form.wpcf7-form')[i].elements.namedItem(ward); return !w.disabled && w.options.length>1;},{i,ward});
    const value = await field(i,ward).locator('option').nth(1).getAttribute('value'); await field(i,ward).selectOption(value); return value;
}
async function submit(i) {
    const response = page.waitForResponse(r => r.request().method()==='POST' && /contact-form-7.*feedback/.test(r.url()));
    await form(i).locator('[type="submit"]').click(); return (await response).json();
}
try {
    await page.goto(fixture.plain); check(await page.locator('script[src*="contact-form-7.js"],script[src*="address-data-client.js"],script[src*="vn-address.js"]').count()===0, 'Plain CF7 form has no VNAP assets');
    await page.goto(fixture.page); await page.waitForFunction(() => window.VNAddressPro?.initContactForm7);
    await page.waitForFunction(() => !document.querySelectorAll('form.wpcf7-form')[1].elements.namedItem('home_ward').disabled);
    check(await page.locator('form.wpcf7-form').count()===2, 'Two actual CF7 forms render');
    check(await page.locator('script[src*="assets/js/vn-address.js"]').count()===1 && await page.locator('script[src*="address-data-client.js"]').count()===1 && await page.locator('script[src*="assets/js/contact-form-7.js"]').count()===1,'Shared assets deduplicate across CF7 forms and shortcode');
    check(await field(1,'home_ward').inputValue()===String(fixture.ward.code), 'Second form restores canonical defaults');
    check(await field(0,'home_ward').isDisabled(), 'Empty province keeps only its ward disabled');
    const chosen=await choose(0,'home','79'); check(!!chosen,'Province loads wards through same-origin REST');
    const office=await choose(0,'office','1'); check(await field(0,'home_ward').inputValue()===chosen,'Same-form groups independent');
    check(await page.locator('[data-vnap-address] [data-vnap-province]').inputValue()==='' && await page.locator('[data-vnap-address] [data-vnap-ward]').isDisabled(),'CF7 groups leave generic shortcode component unchanged');
    check(await field(1,'home_ward').inputValue()===String(fixture.ward.code),'Separate forms do not interfere');
    check(await field(0,'home').inputValue()==='79' && /^\d+$/.test(chosen),'Posted select values are canonical codes');
    await field(0,'contact_name').fill('Synthetic Contact'); await field(0,'contact_email').fill('invalid');
    let result=await submit(0); check(result.status==='validation_failed','Actual CF7 AJAX validation failure');
    check(await field(0,'home_ward').inputValue()===chosen && await field(0,'office_ward').inputValue()===office,'Validation failure preserves both valid address groups');
    await field(0,'contact_email').fill('synthetic@example.invalid'); await field(0,'street').fill('Synthetic street');
    result=await submit(0); check(result.status==='mail_sent','Actual offline CF7 AJAX success');
    check(result.vnap_test_mail.length===2 && result.vnap_test_mail.every(body=>body.includes(fixture.province.name) && body.includes(fixture.ward.name) && body.includes('|79|')),'Mail and Mail (2) contain canonical names and explicit codes');
    check(result.vnap_test_codes.home==='79' && result.vnap_test_codes.home_ward===chosen,'Consumers receive unchanged canonical posted codes');
    await page.waitForFunction(() => !document.querySelectorAll('form.wpcf7-form')[0].elements.namedItem('home').value);
    check(await field(0,'home_ward').inputValue()==='' && await field(0,'home_ward').isDisabled(),'Successful CF7 reset clears stale ward');
    await choose(0,'home','1'); await form(0).evaluate(f=>f.reset()); await page.waitForFunction(()=>!document.querySelectorAll('form.wpcf7-form')[0].elements.namedItem('home_ward').value);
    check(await field(0,'home').inputValue()==='' && await field(0,'home_ward').isDisabled(),'Native reset restores initial empty group');
    await choose(1,'home','1'); await form(1).evaluate(f=>f.reset()); await page.waitForFunction(code=>document.querySelectorAll('form.wpcf7-form')[1].elements.namedItem('home_ward').value===String(code),fixture.ward.code);
    check(await field(1,'home').inputValue()==='79','Reset restores valid default province/ward');
    const ids=await page.locator('.vnap-cf7-field').evaluateAll(nodes=>nodes.map(n=>n.id)); check(ids.every(Boolean) && new Set(ids).size===ids.length,'Unique native control IDs across forms/groups');
    const count=await form(0).evaluate(f=>window.VNAddressPro.initContactForm7(f).length); check(count===2,'Explicit scoped init is idempotent');
    // Deliberately ignore cancellation: old A completion must still lose to B.
    await page.reload(); await page.waitForFunction(()=>window.VNAddressPro?.initContactForm7);
    await page.evaluate(()=>{
        const original=window.VNAddressProData; let release;
        window.vnapOldResult=new Promise(resolve=>{release=resolve;}); window.vnapReleaseOld=release;
        window.VNAddressProData={...original,getWards:(p,options)=>p==='79'?window.vnapOldResult:original.getWards(p,options)};
    });
    await field(0,'home').selectOption('79'); const final=await choose(0,'home','1');
    await page.evaluate(()=>window.vnapReleaseOld([{code:'999999',name:'Obsolete response'}]));
    await page.waitForFunction(()=>!document.querySelectorAll('form.wpcf7-form')[0].elements.namedItem('home_ward').querySelector('[value="999999"]'));
    check(await field(0,'home_ward').inputValue()===final && await field(0,'home').inputValue()==='1','Abort-insensitive obsolete response cannot replace final group');
    check(external.length===0,'Browser sends zero external requests'); check(errors.length===0,'No VNAP/CF7 browser exceptions or console errors');
    console.log(`Success: ${checks} CF7 browser checks passed.`);
} finally { await browser.close(); }
