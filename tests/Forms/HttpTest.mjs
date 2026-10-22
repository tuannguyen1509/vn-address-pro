import { readFileSync } from 'node:fs';
const fixture = JSON.parse(readFileSync(process.env.VNAP_TEST_FIXTURE,'utf8'));
const url = new URL(fixture.page); url.search=''; url.searchParams.set('rest_route',`/contact-form-7/v1/contact-forms/${fixture.forms[0]}/feedback`);
let checks=0; const check=(ok,label)=>{if(!ok)throw new Error(label);checks++;console.log('PASS '+label);};
const base={_wpcf7:String(fixture.forms[0]),_wpcf7_unit_tag:`wpcf7-f${fixture.forms[0]}-o1`,_wpcf7_locale:'en_US',contact_name:'Synthetic Contact',contact_email:'synthetic@example.invalid',home:'79',home_ward:String(fixture.ward.code),office:'',office_ward:'',street:'',home_name:'FORGED NAME',home_ward_name:'FORGED WARD'};
async function send(changes={}) {
    const values={...base,...changes}, data=new FormData();
    for(const [name,value] of Object.entries(values)){if(value!==null)data.append(name,value);}
    const response=await fetch(url,{method:'POST',body:data}); const body=await response.json();
    if(response.status!==200)throw new Error(JSON.stringify(body));return body;
}
for(const [changes,label] of [
    [{home:''},'required province'],[{home_ward:''},'required ward'],[{home:'999999999'},'unknown province'],[{home_ward:'999999999'},'unknown ward'],
    [{home:'1'},'province/ward mismatch'],[{home:'79x'},'malformed province'],[{home_ward:'0'},'malformed ward'],
    [{home:null,'home[]':'79'},'province array'],[{home_ward:null,'home_ward[]':String(fixture.ward.code)},'ward array'],
    [{office:'79'},'incomplete optional group'],[{street:'x'.repeat(401)},'oversized street']
]){
    const result=await send(changes);check(result.status==='validation_failed' && result.invalid_fields.length>0 && result.vnap_test_mail.length===0,`Native CF7 HTTP rejects ${label} before mail`);
}
const valid=await send();check(valid.status==='mail_sent','Direct HTTP valid canonical pair and empty optional fields accepted');
check(valid.vnap_test_mail.length===2 && valid.vnap_test_mail.every(body=>body.includes(fixture.province.name)&&body.includes(fixture.ward.name)&&!body.includes('FORGED')),'Native captured Mail/Mail2 trust only repository names');
check(valid.vnap_test_codes.home==='79'&&valid.vnap_test_codes.home_ward===String(fixture.ward.code),'Canonical posted values preserved for downstream consumers');
console.log(`Success: ${checks} CF7 HTTP checks passed.`);
