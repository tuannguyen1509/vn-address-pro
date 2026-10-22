// Reversed, abort-insensitive responses on actual Account and Order Admin fields.
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
const {chromium}=createRequire(`${process.cwd()}/package.json`)('playwright');
const fixture=JSON.parse(readFileSync(process.env.VNAP_TEST_FIXTURE,'utf8').replace(/^\uFEFF/,'')),base=process.env.VNAP_TEST_URL;
const browser=await chromium.launch(),page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
try{
    await page.goto(new URL('wp-login.php',base).href);await page.locator('#user_login').fill(process.env.VNAP_TEST_USER);await page.locator('#user_pass').fill(process.env.VNAP_TEST_PASSWORD);await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);
    const provinces=(await(await page.request.get(new URL('?rest_route=/vn-address/v1/provinces',base).href)).json()).data.filter(p=>![1,79].includes(p.code));const [a,b]=provinces;
    for(const mode of ['account','admin']){
        await page.goto(mode==='account'?fixture.account.replace(/\/?$/,'/')+'edit-address/billing/':fixture.orders.block.url);
        if(mode==='admin')await page.locator('#order_data a.edit_address').first().click();
        await page.waitForFunction(mode=>{const e=document.getElementById('vnap_'+mode+'_billing_ward');return e&&!e.disabled;},mode);
        await page.evaluate(a=>{window.__originalFetch=window.fetch;window.fetch=(input,init={})=>{const url=new URL(input,location.href);if(url.searchParams.get('province_code')===String(a)&&url.href.includes('wards'))return new Promise(resolve=>{window.__originalFetch(input,{...init,signal:undefined}).then(response=>{window.__lateA=()=>resolve(response);});});return window.__originalFetch(input,init);};},a.code);
        const state=page.locator(mode==='admin'?'#_billing_state':'#billing_state');await state.selectOption('VN-'+a.code,{force:true});await page.waitForFunction(()=>typeof window.__lateA==='function');await state.selectOption('VN-'+b.code,{force:true});
        const ward=page.locator('#vnap_'+mode+'_billing_ward');await page.waitForFunction(({mode,code})=>{const e=document.getElementById('vnap_'+mode+'_billing_ward');return e&&!e.disabled&&e.dataset.vnapProvinceCode===String(code);},{mode,code:b.code});
        const value=await ward.locator('option').nth(1).getAttribute('value');await ward.selectOption(value);await page.evaluate(()=>window.__lateA());await page.waitForTimeout(250);
        const code=page.locator(mode==='admin'?'#_billing_vnap_ward_code':'#vnap_billing_ward_code');
        if(await ward.inputValue()!==value||await code.inputValue()!==value||await ward.getAttribute('data-vnap-province-code')!==String(b.code))throw new Error('Late A replaced newer '+mode+' selection');
        console.log('PASS '+mode+' ignores obsolete response and retains coherent code/name selection');
    }
    if(errors.length)throw new Error(errors.join(';'));console.log('Success: Account/Admin reversed-response races passed; clean exceptions.');
}finally{await browser.close();}
