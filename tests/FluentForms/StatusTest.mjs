import {createRequire} from 'node:module';
const require = createRequire(process.cwd() + '/package.json'), {chromium} = require('playwright');
const browser = await chromium.launch({headless:true});
try {
    const page = await browser.newPage(), base = process.env.VNAP_TEST_URL, errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(new URL('wp-login.php',base).href);
    await page.locator('#user_login').fill(process.env.VNAP_TEST_USER); await page.locator('#user_pass').fill(process.env.VNAP_TEST_PASSWORD);
    await Promise.all([page.waitForNavigation(),page.locator('#wp-submit').click()]);
    await page.goto(new URL('wp-admin/options-general.php?page=vn-address-pro&tab=status',base).href);
    const rows = page.locator('.vnap-status tr');
    for (const id of ['contact-form-7','fluent-forms']) {
        const row = rows.filter({has:page.locator('th').filter({hasText:new RegExp('^Form integration: ' + id + '$')})});
        if (await row.locator('td').innerText() !== 'Active') throw new Error(id + ' status is not Active');
        console.log('PASS System Status ' + id + ' Active');
    }
    const version = await rows.filter({has:page.locator('th').filter({hasText:/^fluent-forms Version$/})}).locator('td').innerText();
    if (!/^6\./.test(version)) throw new Error('Detected version missing');
    if (await page.locator('script[src*="assets/js/fluent-forms.js"]').count() || errors.length) throw new Error('Admin asset/error regression');
    console.log('PASS Fluent version displayed; no frontend asset or browser exception');
} finally {await browser.close();}
