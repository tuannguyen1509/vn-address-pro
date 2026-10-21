// Disposable test site only. Node 20+; credentials supplied through environment, never committed.
const base = process.env.VNAP_TEST_URL;
if (!base || !process.env.VNAP_TEST_USER || !process.env.VNAP_TEST_PASSWORD) throw new Error('Set VNAP_TEST_URL, VNAP_TEST_USER and VNAP_TEST_PASSWORD.');
const jar = new Map();
let checks = 0;
function assert(condition, label) { if (!condition) throw new Error(label); checks++; console.log(`PASS ${label}`); }
async function request(path, body, anonymous = false) {
  const headers = {};
  if (!anonymous) headers.Cookie = [...jar].map(([k, v]) => `${k}=${v}`).join('; ');
  if (body) headers['Content-Type'] = 'application/x-www-form-urlencoded';
  const response = await fetch(new URL(path, base), { method: body ? 'POST' : 'GET', body: body ? new URLSearchParams(body) : undefined, headers, redirect: 'manual' });
  if (!anonymous) for (const cookie of response.headers.getSetCookie()) { const [pair] = cookie.split(';'); const i = pair.indexOf('='); jar.set(pair.slice(0, i), pair.slice(i + 1)); }
  return { status: response.status, text: await response.text(), location: response.headers.get('location') };
}
await request('wp-login.php');
const login = await request('wp-login.php', { log: process.env.VNAP_TEST_USER, pwd: process.env.VNAP_TEST_PASSWORD, 'wp-submit': 'Log In', testcookie: '1' });
assert(login.status === 302, 'Authenticated admin login');
const route = 'wp-admin/options-general.php?page=vn-address-pro';
const plugin = await request(route);
assert(plugin.status === 200 && plugin.text.includes('vnap-admin'), 'Settings → VN Address available');
const plugins = await request('wp-admin/plugins.php');
assert(plugins.status === 200 && plugins.text.includes('VN Address Pro') && plugins.text.includes('Tunlamweb'), 'Plugin metadata visible in Plugins admin');
for (const tab of ['general', 'data', 'tools', 'status']) {
  const page = await request(`${route}&tab=${tab}`);
  assert(page.status === 200 && page.text.includes('vnap-admin'), `Admin tab ${tab}`);
}
const tabs = { schedule: 'general', check: 'data', sync: 'data', validate: 'tools', repair: 'tools', reinstall: 'tools', clear: 'tools' };
for (const [command, tab] of Object.entries(tabs)) {
  const page = await request(`${route}&tab=${tab}`);
  const forms = [...page.text.matchAll(/<form\b[^>]*>[\s\S]*?<\/form>/g)].map(m => m[0]);
  const form = forms.find(f => f.includes(`name="vnap_command" value="${command}"`));
  const nonce = form?.match(/name="_wpnonce" value="([^"]+)"/)?.[1];
  assert(!!nonce, `Action ${command} has its own nonce`);
  const response = await request('wp-admin/admin-post.php', { action: 'vnap_dataset_action', vnap_command: command, _wpnonce: nonce, ...(command === 'schedule' ? { vnap_schedule: 'disabled' } : {}) });
  assert(response.status === 302 && response.location.includes('page=vn-address-pro'), `Action ${command} completes with safe redirect`);
  const result = await request(response.location);
  assert(result.status === 200 && result.text.includes('notice-info'), `Action ${command} shows escaped feedback`);
}
const denied = await request('wp-admin/admin-post.php', { action: 'vnap_dataset_action', vnap_command: 'sync', _wpnonce: 'invalid' });
assert(denied.status === 403, 'Admin POST with invalid nonce is rejected');
const anonymous = await request('wp-admin/admin-post.php', { action: 'vnap_dataset_action', vnap_command: 'sync' }, true);
assert(anonymous.status >= 400, 'Anonymous POST cannot trigger sync');
console.log(`Success: ${checks} admin HTTP checks passed.`);
