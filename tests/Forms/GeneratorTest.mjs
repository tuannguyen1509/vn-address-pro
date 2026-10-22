import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
const require=createRequire(`${process.cwd()}/package.json`),{chromium}=require('playwright');
const fixture=JSON.parse(readFileSync(process.env.VNAP_TEST_FIXTURE,'utf8')),base=process.env.VNAP_TEST_URL;
const browser=await chromium.launch({headless:true}),page=await browser.newPage();let checks=0;const errors=[];
page.on('pageerror',e=>errors.push(e.message));
const check=(ok,label)=>{if(!ok)throw new Error(label);checks++;console.log('PASS '+label);};
try{
    await page.goto(new URL('wp-login.php',base).href);await page.locator('#user_login').fill(process.env.VNAP_TEST_USER);await page.locator('#user_pass').fill(process.env.VNAP_TEST_PASSWORD);
    await Promise.all([page.waitForNavigation(),page.locator('#wp-submit').click()]);
    await page.goto(new URL(`wp-admin/admin.php?page=wpcf7&post=${fixture.forms[0]}&action=edit`,base).href);
    const modern=await page.locator('[data-target="tag-generator-panel-vnap_ward"]').count()>0;
    for(const kind of ['province','ward','address']){
        let panel;
        if(modern){await page.locator(`[data-target="tag-generator-panel-vnap_${kind}"]`).click();panel=page.locator(`#tag-generator-panel-vnap_${kind}`);await panel.locator('[data-tag-part="name"]').fill(`synthetic_${kind}`);}
        else{await page.locator(`a[href*="inlineId=tag-generator-panel-vnap_${kind}"]`).click();panel=page.locator(`#TB_ajaxContent form[data-id="vnap_${kind}"]`);await panel.locator('[name="name"]').fill(`synthetic_${kind}`);}
        if(kind==='ward'){await panel.locator(modern?'[data-tag-option="province:"]':'[name="province"]').fill('synthetic_province');}
        const generated=panel.locator(modern?'[data-tag-part="tag"]':'.tag');
        await generated.click();const text=await generated.inputValue();check(text.includes(`vnap_${kind}`)&&text.includes(`synthetic_${kind}`),`Native generator produces ${kind} tag`);
        if(kind==='ward')check(text.includes('province:synthetic_province'),'Native generator includes explicit province link');
        await panel.locator(modern?'[data-taggen="insert-tag"]':'.insert-tag').click();
        // CF7 v2 inserts in the native dialog's queued `close` event.
        await page.waitForFunction(tag=>document.querySelector('#wpcf7-form')?.value.includes(tag),text);
        check((await page.locator('#wpcf7-form').inputValue()).includes(text),`Native editor inserts generated ${kind} tag`);
        if(modern){if(await panel.isVisible())await page.keyboard.press('Escape');}else{if(await page.locator('#TB_closeWindowButton').isVisible())await page.locator('#TB_closeWindowButton').click();}
    }
    check(await page.locator('script[src*="assets/js/contact-form-7.js"]').count()===0,'No frontend adapter in CF7 admin');
    check(errors.length===0,'Tag generator editor has no uncaught exceptions');console.log(`Success: ${checks} CF7 generator checks passed.`);
}finally{await browser.close();}
