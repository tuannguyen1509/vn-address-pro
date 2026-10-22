import { createRequire } from 'node:module';
const require = createRequire(`${process.cwd()}/package.json`);
const { request } = require('playwright');
const base = process.env.VNAP_TEST_URL, product = process.env.VNAP_TEST_PRODUCT;
if (!base || !product) throw new Error('Disposable site only; set VNAP_TEST_URL and VNAP_TEST_PRODUCT.');
const context = await request.newContext();
let token = '', nonce = '', count = 0;
const assert = (ok, message) => { if (!ok) throw new Error(message); count++; console.log('PASS '+message); };
function endpoint(path) { return new URL('?rest_route=/wc/store/v1/'+path,base).href; }
async function api(path, data) {
    const headers={...(token?{'Cart-Token':token}:{}),...(nonce?{Nonce:nonce}:{})};
    const response = data ? await context.post(endpoint(path), { data, headers }) : await context.get(endpoint(path), { headers });
    token = response.headers()['cart-token'] || token;
    nonce = response.headers().nonce || nonce;
    return {status:response.status(),body:await response.json()};
}
try {
    await api('cart'); assert(!!token,'Native Cart Token authentication available');
    const added=await api('cart/add-item',{id:Number(product),quantity:1});
    assert(added.status>=200&&added.status<300,'Physical product added through Store API: '+JSON.stringify(added.status>=400?{status:added.status,code:added.body.code,message:added.body.message}:{}));
    const wards=await (await context.get(new URL('?rest_route=/vn-address/v1/wards&province_code=79',base).href)).json();
    const ward=wards.data[0];
    const address={first_name:'Test',last_name:'Customer',country:'VN',state:'VN-79',city:'Forged display value',address_1:'Synthetic test street',address_2:'',postcode:'',phone:'0901234567',email:`store-${Date.now()}@example.invalid`};
    let result=await api('cart/update-customer',{billing_address:address,shipping_address:address});
    assert(result.status===200,'Native customer address update and shipping recalculation');
    assert(result.body.shipping_rates.some(pkg=>pkg.shipping_rates.some(rate=>rate.selected&&rate.name==='Province zone')),'Province-specific shipping zone selected through Store API');
    assert(Number(result.body.totals.total_tax)>0,'Province-based tax calculated by native Store API');
    const payload={billing_address:address,shipping_address:address,payment_method:'bacs',extensions:{'vn-address-pro':{billingWardCode:String(ward.code),shippingWardCode:String(ward.code)}}};
    for(const [label,change] of [
        ['missing ward',p=>{delete p.extensions;}],
        ['unknown ward',p=>{p.extensions['vn-address-pro'].billingWardCode='99999999';}],
        ['wrong parent',p=>{p.billing_address.state='VN-1';}],
        ['invalid province',p=>{p.billing_address.state='VN-999999';}],
        ['state label',p=>{p.billing_address.state='Thành phố Hồ Chí Minh';}],
        ['ward array',p=>{p.extensions['vn-address-pro'].billingWardCode=['1'];}],
        ['missing shipping ward',p=>{delete p.extensions['vn-address-pro'].shippingWardCode;}],
        ['unknown extension structure',p=>{p.extensions['vn-address-pro'].unregistered={ward:'1'};}],
    ]) {
        const altered=structuredClone(payload); change(altered);
        result=await api('checkout',altered);
        assert(result.status>=400 && result.status<500,'Direct checkout rejects '+label);
    }
    result=await api('checkout',payload);
    assert(result.status===200,'Valid Store API BACS checkout succeeds: '+JSON.stringify(result.status===200?{}:result.body));
    const order=result.body;
    const reportUrl=new URL(base);reportUrl.searchParams.set('vnap_test_report',order.order_id);reportUrl.searchParams.set('key',order.order_key);
    const report=await(await context.get(reportUrl.href)).json();
    assert(report.billing_city===ward.name && report.shipping_city===ward.name,'Server replaces forged city with authoritative ward name');
    assert(String(report.billing_code)===String(ward.code)&&String(report.shipping_code)===String(ward.code),'Store API order persists same canonical ward keys as Classic');
    assert(report.billing_state==='VN-79'&&!report.formatted.includes('VN-79'),'State contract and human-readable address preserved');
    await api('cart');
    const foreignAdded=await api('cart/add-item',{id:Number(product),quantity:1});
    assert(foreignAdded.status<300,'New foreign cart created after previous checkout: '+JSON.stringify(foreignAdded.status>=400?{code:foreignAdded.body.code,message:foreignAdded.body.message}:{}));
    const foreign={...address,country:'US',state:'CA',city:'Los Angeles',postcode:'90001'};
    await api('cart/update-customer',{billing_address:foreign,shipping_address:foreign});
    result=await api('checkout',{billing_address:foreign,shipping_address:foreign,payment_method:'bacs'});
    assert(result.status===200,'Foreign Store API checkout works without VNAP extension data: '+JSON.stringify(result.status===200?{}:{code:result.body.code,message:result.body.message,details:result.body.data?.details}));
    const foreignUrl=new URL(base);foreignUrl.searchParams.set('vnap_test_report',result.body.order_id);foreignUrl.searchParams.set('key',result.body.order_key);
    const foreignReport=await(await context.get(foreignUrl.href)).json();
    assert(foreignReport.billing_city==='Los Angeles'&&!foreignReport.billing_code&&!foreignReport.shipping_code,'Foreign native address retained without VN canonical metadata');
    await api('cart'); await api('cart/add-item',{id:Number(product),quantity:1});
    const otherWards=await(await context.get(new URL('?rest_route=/vn-address/v1/wards&province_code=1',base).href)).json();
    const other=otherWards.data[0], shipping={...address,state:'VN-1',city:'Forged shipping value'};
    await api('cart/update-customer',{billing_address:address,shipping_address:shipping});
    result=await api('checkout',{billing_address:address,shipping_address:shipping,payment_method:'cod',extensions:{'vn-address-pro':{billingWardCode:String(ward.code),shippingWardCode:String(other.code)}}});
    assert(result.status===200,'Separate billing/shipping Store API COD order succeeds');
    const separateUrl=new URL(base);separateUrl.searchParams.set('vnap_test_report',result.body.order_id);separateUrl.searchParams.set('key',result.body.order_key);
    const separateReport=await(await context.get(separateUrl.href)).json();
    assert(String(separateReport.billing_province)==='79'&&String(separateReport.shipping_province)==='1'&&String(separateReport.billing_code)===String(ward.code)&&String(separateReport.shipping_code)===String(other.code),'All four canonical order codes match their independent native states');
    assert(separateReport.shipping_state==='VN-1'&&separateReport.shipping_city===other.name,'Independent shipping name canonicalized server-side');
    console.log(`Success: ${count} direct Store API checks passed.`);
} finally {await context.dispose();}
