// Run from a temporary directory containing the Playwright package. No production build step.
import { createRequire } from 'node:module';
const require = createRequire(`${process.cwd()}/package.json`);
const { chromium } = require('playwright');
const base = process.env.VNAP_TEST_URL;
if (!base) throw new Error('Set VNAP_TEST_URL to a disposable site with the TestPage MU fixture.');
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext();
const page = await context.newPage();
let checks = 0;
const errors = [], requests = [], external = [];
page.on('pageerror', e => errors.push(e.message));
page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
page.on('request', r => { requests.push(r.url()); if (new URL(r.url()).origin !== new URL(base).origin) external.push(r.url()); });
function assert(condition, message) { if (!condition) throw new Error(message); checks++; console.log(`PASS ${message}`); }
const componentUrl = new URL('?vnap_test_page=component', base).href;
const plainUrl = new URL('?vnap_test_page=plain', base).href;
try {
    await page.goto(plainUrl);
    assert(await page.locator('script[src*="vn-address.js"],link[href*="vn-address.css"]').count() === 0, 'Plain page has no component assets');
    await page.addInitScript(() => {
        window.vnapTestEvents = [];
        for (const name of ['ready','province-changed','wards-loading','wards-loaded','ward-changed','error']) document.addEventListener(`vnap:${name}`, event => window.vnapTestEvents.push({ name, id: event.target.id, detail: event.detail }));
        window.addEventListener('unhandledrejection', event => { window.vnapTestUnhandled = String(event.reason); });
    });
    await page.goto(componentUrl);
    await page.waitForFunction(() => window.VNAddressPro && document.querySelectorAll('[data-vnap-address]').length === 3);
    await page.evaluate(() => Promise.all(VNAddressPro.initAll().map(i => i.ready)));
    assert(await page.locator('[data-vnap-address]').count() === 3, 'Three independent instances initialize');
    assert(await page.evaluate(() => {
        const root = document.createElement('div'); root.setAttribute('data-vnap-address', '');
        root.innerHTML = '<input data-vnap-province><select data-vnap-ward><option value=""></option></select><input data-vnap-province-name><input data-vnap-ward-name>';
        return VNAddressPro.init(root) === null && VNAddressPro.init(document.createElement('div')) === null;
    }), 'Incomplete or non-native markup is rejected without a promise error');
    assert(await page.locator('script[src*="vn-address.js"]').count() === 1 && await page.locator('link[href*="vn-address.css"]').count() === 1, 'Assets print once even when rendered after head');
    assert(await page.locator('[data-vnap-province]').first().locator('option').count() > 1, 'Provinces are server rendered');
    assert(await page.locator('[data-vnap-ward]').first().isDisabled(), 'Ward disabled before province selection');
    assert(await page.locator('[data-vnap-ward]').first().locator('option').count() === 1, 'No full ward payload in initial empty field');
    assert(await page.evaluate(() => { const ids = [...document.querySelectorAll('[id]')].map(n => n.id); return ids.length === new Set(ids).size; }), 'No duplicate HTML IDs');
    assert(await page.evaluate(() => [...document.querySelectorAll('.vnap-label')].every(l => document.getElementById(l.htmlFor))), 'Every label resolves a field');
    assert(await page.evaluate(() => { const i = VNAddressPro.getInstance(document.querySelectorAll('[data-vnap-address]')[2]); return !!i.getProvince().code && !!i.getWard().code && i.getAddress().address === 'Example address'; }), 'Preselected province, ward and address restore');
    assert(await page.evaluate(() => window.vnapTestEvents.filter(e => e.name === 'ready').length === 3), 'Ready event fires once per instance and bubbles');
    const real = await page.evaluate(async () => {
        const [a,b] = VNAddressPro.initAll();
        await a.setProvince('79'); const initial = b.getAddress();
        const selected = a.wards[0]; a.setWard(selected.code);
        return { value: a.getAddress(), selected, unchanged: JSON.stringify(initial) === JSON.stringify(b.getAddress()), disabled: a.ward.disabled };
    });
    assert(real.value.province_code === '79' && real.value.ward_code === real.selected.code && !real.disabled, 'Real same-site REST populates ward code values');
    assert(real.value.province_name && real.value.ward_name && real.unchanged, 'Names available; another instance remains unchanged');
    assert(await page.evaluate(() => { const c = document.querySelector('[data-vnap-address]'); const a = VNAddressPro.getInstance(c); return c.querySelector('[data-vnap-ward-name]').value === a.getWard().name; }), 'Hidden names match canonical selection');
    const beforeCache = requests.length;
    await page.evaluate(async () => { const [a,b] = VNAddressPro.initAll(); await a.setProvince(''); await a.setProvince('79'); await b.setProvince('79'); });
    assert(requests.length === beforeCache, 'Fulfilled ward cache reused across instances on the page');
    assert(await page.evaluate(() => !VNAddressPro.initAll()[0].validate().valid), 'Required fields fail validation while empty');
    const eventsBefore = await page.evaluate(() => vnapTestEvents.filter(e => e.name === 'province-changed').length);
    await page.evaluate(() => { VNAddressPro.initAll(); VNAddressPro.initAll(); const p = document.querySelector('[data-vnap-province]'); p.value = ''; p.dispatchEvent(new Event('change', { bubbles: true })); });
    assert(await page.evaluate(() => vnapTestEvents.filter(e => e.name === 'province-changed').length) === eventsBefore + 1, 'Re-init does not duplicate listeners');
    assert(await page.evaluate(() => document.querySelector('[data-vnap-ward-name]').value === ''), 'Changing province immediately clears old ward name');

    // Deterministic fetch mock ignores abort deliberately: sequence protection must still win.
    const race = await page.evaluate(async () => {
        const a = VNAddressPro.initAll()[0]; window.vnapOriginalFetch = window.fetch;
        const available = [...a.province.options].map(o => o.value).filter(v => v && !['1','79'].includes(v));
        const [p,q] = available; const pending = []; let aborted = 0;
        window.fetch = (url, options) => new Promise(resolve => { options.signal.addEventListener('abort', () => aborted++); pending.push({ url, resolve }); });
        const first = a.setProvince(p); const second = a.setProvince(q);
        const response = (province, name, id) => new Response(JSON.stringify({ success: true, province: { code: Number(province) }, count: 1, data: [{ code: id, name, province_code: Number(province) }] }), { status: 200 });
        pending[1].resolve(response(q, 'Ward B', 900002)); await second;
        pending[0].resolve(response(p, 'Ward A', 900001)); await first;
        const outcome = { province: a.getProvince().code, q, names: [...a.ward.options].map(o => o.textContent), aborted, hidden: a.wardName.value };
        window.fetch = window.vnapOriginalFetch; return outcome;
    });
    assert(race.province === race.q && race.names.includes('Ward B') && !race.names.includes('Ward A'), 'Slow obsolete response cannot overwrite newer province');
    assert(race.aborted === 1 && race.hidden === '', 'Previous request aborted and stale hidden selection cleared');
    const states = await page.evaluate(async () => {
        const root = document.querySelector('[data-vnap-address]'); const a = VNAddressPro.getInstance(root);
        const values = [...a.province.options].map(o => o.value).filter(Boolean);
        let cursor = 10;
        const next = () => values[cursor++];
        window.fetch = async url => {
            const p = new URL(url).searchParams.get('province_code');
            return new Response(JSON.stringify({ success: true, province: { code: Number(p) }, count: 0, data: [] }));
        };
        await a.setProvince(next());
        const empty = a.ward.disabled && a.ward.options.length === 1 && !!a.status.textContent;
        window.fetch = async () => new Response(JSON.stringify({ code: 'failure' }), { status: 503 });
        await a.setProvince(next());
        const error = a.ward.disabled && a.wardName.value === '' && !!a.error.textContent && !a.retry.hidden;
        window.fetch = async () => new Response('{broken'); await a.setProvince(next());
        const badJson = a.ward.disabled && !!a.error.textContent;
        window.fetch = async url => new Response(JSON.stringify({ success: true, province: { code: Number(new URL(url).searchParams.get('province_code')) }, count: 1, data: [{ code: 900003, province_code: 999999, name: 'Bad parent' }] }));
        await a.setProvince(next()); const mismatch = a.ward.disabled && !!a.error.textContent;
        window.fetch = async () => { throw new TypeError('simulated network outage'); };
        await a.setProvince(next()); const network = a.ward.disabled && !!a.error.textContent;
        window.fetch = async () => new Response(JSON.stringify({ success: true, data: {} }));
        await a.setProvince(next()); const schema = a.ward.disabled && !!a.error.textContent;
        window.fetch = window.vnapOriginalFetch;
        await a.setProvince('79', '99999999');
        const invalid = a.ward.value === '' && a.wardName.value === '' && !!a.error.textContent;
        a.setWard(a.wards[0].code); a.address.value = 'Example';
        const valid = a.validate().valid;
        const textRow = { code: '900009', name: '<img src=x onerror=alert(1)>' };
        window.fetch = async url => new Response(JSON.stringify({ success: true, province: { code: Number(new URL(url).searchParams.get('province_code')) }, count: 1, data: [{ ...textRow, province_code: Number(new URL(url).searchParams.get('province_code')) }] }));
        await a.setProvince(next()); const escaped = a.ward.options[1].textContent === textRow.name && !root.querySelector('img');
        window.fetch = window.vnapOriginalFetch;
        await a.reset();
        return { empty, error, badJson, mismatch, network, schema, invalid, valid, escaped, reset: !a.province.value && a.ward.disabled && !a.address.value };
    });
    for (const [name, passed] of Object.entries(states)) assert(passed, `Controlled state: ${name}`);
    const lifecycle = await page.evaluate(async () => {
        const root = document.querySelector('[data-vnap-address]'); const a = VNAddressPro.getInstance(root);
        let aborted = false; window.fetch = (url, options) => new Promise((resolve, reject) => { options.signal.addEventListener('abort', () => { aborted = true; reject(new DOMException('Aborted', 'AbortError')); }); });
        const pending = a.setProvince([...a.province.options].map(o => o.value).filter(Boolean)[30]);
        a.destroy(); await pending;
        const removed = VNAddressPro.getInstance(root) === null && a.container === null;
        window.fetch = window.vnapOriginalFetch;
        const replacement = VNAddressPro.init({ container: root }); await replacement.ready;
        return { aborted, removed, reinit: replacement !== a && !!VNAddressPro.getInstance(root) };
    });
    for (const [name, passed] of Object.entries(lifecycle)) assert(passed, `Lifecycle: ${name}`);
    await page.evaluate(() => document.querySelector('form').reset());
    await page.waitForFunction(() => { const i = VNAddressPro.initAll()[2]; return !!i.getWard().code && i.address.value === 'Example address'; });
    assert(await page.evaluate(() => !!VNAddressPro.initAll()[2].getWard().code), 'Native form reset restores valid preselection');
    await page.setViewportSize({ width: 320, height: 800 });
    assert(await page.evaluate(() => [...document.querySelectorAll('.vnap-select,.vnap-input')].every(el => el.getBoundingClientRect().right <= window.innerWidth)), 'Controls fit mobile viewport');
    await page.locator('[data-vnap-province]').first().focus(); await page.keyboard.press('ArrowDown'); await page.keyboard.press('Tab');
    assert(await page.evaluate(() => document.activeElement !== document.body), 'Native keyboard navigation retains focusable controls');
    assert(await page.evaluate(() => !window.vnapTestUnhandled) && errors.length === 0, 'No uncaught exceptions, unhandled rejection or console error');
    assert(external.length === 0, 'Browser never calls external APIs');
    assert(await page.evaluate(() => ['ready','province-changed','wards-loading','wards-loaded','ward-changed','error'].every(name => vnapTestEvents.some(e => e.name === name))), 'All documented events observed');
    await page.reload(); await page.evaluate(() => Promise.all(VNAddressPro.initAll().map(i => i.ready)));
    const beforeReloadFetch = requests.length;
    await page.evaluate(() => VNAddressPro.initAll()[0].setProvince('79'));
    assert(requests.length > beforeReloadFetch, 'Ward cache is discarded on page reload');
    assert(errors.length === 0, 'Console remains clean after reload');
    console.log(`Success: ${checks} frontend browser checks passed.`);
} finally { await browser.close(); }
