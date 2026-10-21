// Node 20+. Test server must separately deny outbound HTTP; inspect its attempt counter afterwards.
const base = process.env.VNAP_TEST_URL;
if (!base) throw new Error('Set VNAP_TEST_URL.');
for (const [route, code] of [['provinces'], ['wards', 79], ['province/79'], ['health']]) {
  const url = new URL('./', base);
  url.searchParams.set('rest_route', `/vn-address/v1/${route}`);
  if (code) url.searchParams.set('province_code', String(code));
  const response = await fetch(url);
  const result = await response.json();
  if (response.status !== 200 || result.success !== true) throw new Error(`Offline REST failed: ${route}`);
  console.log(`PASS offline HTTP 200 ${route}`);
}
