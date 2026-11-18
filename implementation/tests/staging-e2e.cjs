const {chromium}=require('../../work/node_modules/playwright');
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),crypto=require('crypto');
const dir=path.resolve(__dirname,'../../work/staging'),creds=JSON.parse(fs.readFileSync(path.join(dir,'credentials.json'))),origin='https://new.la2plus.com';
(async()=>{
 const browser=await chromium.launch({executablePath:'/usr/bin/chromium',args:['--no-sandbox']});
 const ctx=await browser.newContext({viewport:{width:1440,height:1000}}),p=await ctx.newPage();let checks=0;
 const check=(b,label)=>{assert.ok(b,label);checks++;console.log('PASS',label)};
 const go=async url=>{const r=await p.goto(origin+url,{waitUntil:'networkidle'});check(r.status()===200,'Page '+url);return r};
 const entry=await go('/login/?test=flows');check(!entry.headers()['www-authenticate'],'No hosting password dialog');
 await p.locator('[name="LoginForm[login]"]').fill('PreviewOwner');await p.locator('[name="LoginForm[password]"]').fill(creds.player_password);
 await Promise.all([p.waitForURL('**/cabinet/'),p.locator('form [type="submit"]').click()]);await p.waitForLoadState('networkidle');check(await p.locator('[data-portal-root]').count()===1,'Application login and stable session');
 const csrf=async()=>p.locator('[name="LA2PLUS_NEW_CSRF"]').first().inputValue();
 const key=()=>crypto.randomUUID().replaceAll('-','');
 async function post(op,fields={},requestKey=key()) {
  const body={op,request_key:requestKey,LA2PLUS_NEW_CSRF:await csrf(),...fields};
  const response=await p.evaluate(async ({origin,body})=>{const r=await fetch(origin+'/cabinet/portal/request/',{method:'POST',credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(body)});return {status:r.status,text:await r.text()};},{origin,body});
  let data;try{data=JSON.parse(response.text)}catch(e){throw new Error(op+' returned '+response.status+': '+response.text.slice(0,300))}
  return {status:response.status,...data};
 }
 const ok=async(op,fields={},requestKey)=>{const r=await post(op,fields,requestKey);check(r.ok,op+(r.error?': '+r.error:''));return r.result};
 await go('/cabinet/portal/exchange/?tab=wallet');
 const balance=async()=>Number((await p.locator('.portal-overview .portal-card').nth(3).locator('strong').innerText()).replace(/[^0-9.]/g,''));
 const before=await balance(),creditKey=key();
 await ok('demoTopup',{amount:'5.00'},creditKey);await ok('demoTopup',{amount:'5.00'},creditKey);await p.reload({waitUntil:'networkidle'});
 check(Math.abs(await balance()-before-5)<0.001,'Demo top-up credits once without a real provider');
 await go('/cabinet/portal/exchange/');
 let buy=p.locator('form').filter({has:p.locator('[name="op"][value="buy"]')}).first();
 if(await buy.count()) {const lotId=await buy.locator('[name="lot_id"]').inputValue();const r=await ok('buy',{lot_id:lotId,pin:creds.pin});check(r.fee===250,'Sale fee is 10% of $25');}
 await go('/cabinet/portal/warehouse/');
 const deliverForm=p.locator('form').filter({has:p.locator('[name="op"][value="deliver"]')}).first();
 if(await deliverForm.count()){const itemId=await deliverForm.locator('[name="item_id"]').inputValue();const deliveryKey=key();
 await ok('deliver',{item_id:itemId,link_id:'1',char_id:'101',pin:creds.pin},deliveryKey);
 await ok('deliver',{item_id:itemId,link_id:'1',char_id:'101',pin:creds.pin},deliveryKey);
 }
 let sourceChar='101',targetChar='102';let inventory=await ok('inventory',{link_id:'1',char_id:sourceChar});
 if(!inventory.items.some(i=>i.enchant===16&&i.augmented)){sourceChar='102';targetChar='101';inventory=await ok('inventory',{link_id:'1',char_id:sourceChar});}
 const augmented=inventory.items.find(i=>i.enchant===16&&i.augmented);check(!!augmented,'Test inventory contains augmented +16 item');
 const imported=await ok('import',{link_id:'1',char_id:sourceChar,object_id:String(augmented.object_id),pin:creds.pin});
 const listed=await ok('sell',{item_id:String(imported.id),amount:'12.50',pin:creds.pin});
 const selfBuy=await post('buy',{lot_id:String(listed.id),pin:creds.pin});check(!selfBuy.ok,'Cannot buy own lot');
 await ok('cancel',{lot_id:String(listed.id),pin:creds.pin});
 const transferItemKey=key();await ok('deliver',{item_id:String(imported.id),link_id:'1',char_id:targetChar,pin:creds.pin},transferItemKey);await ok('deliver',{item_id:String(imported.id),link_id:'1',char_id:targetChar,pin:creds.pin},transferItemKey);
 const destination=await ok('inventory',{link_id:'1',char_id:targetChar});check(destination.items.filter(i=>i.enchant===16&&i.augmented&&i.item_id===augmented.item_id).length===1,'One delivered augmented +16 item after retry');
 const denied=await post('inventory',{link_id:'2',char_id:'201'});check(!denied.ok,'Other player inventory denied');
 await go('/cabinet/deposit/?target_link_id=1&target_char_id=101');check((await p.locator('body').innerText()).includes('Asterion'),'Top-up remembers selected character');
 await go('/cabinet/');const coinsKey=key();await ok('coins',{link_id:'1',char_id:'101',coins:'2',pin:creds.pin},coinsKey);await ok('coins',{link_id:'1',char_id:'101',coins:'2',pin:creds.pin},coinsKey);
 await ok('transfer',{amount:'1.00',pin:creds.pin});
 const w=await ok('withdraw',{amount:'10.00',address:'TJRabPrwbZy45sbavfcjinPJC18kjpRTv8',pin:creds.pin});check(w.net===450&&w.fee===550,'Demo withdrawal fee: $3 + 25%, net 4.50');
 const duplicate=await post('withdraw',{amount:'10.00',address:'TJRabPrwbZy45sbavfcjinPJC18kjpRTv8',pin:creds.pin});check(!duplicate.ok,'Only one active withdrawal');
 await ok('withdrawAdmin',{withdrawal_id:String(w.id),action:'processing'});await ok('withdrawAdmin',{withdrawal_id:String(w.id),action:'reject'});
 await go('/cabinet/portal/security/');await ok('code',{purpose:'verify_email',current_password:creds.player_password});
 await go('/test-mail/');const mail=await p.locator('article').first().innerText(),code=mail.match(/Код подтверждения:\s*(\d{6})/);check(!!code,'Email code goes to private test mailbox');
 await go('/cabinet/portal/security/');await ok('verifyEmail',{code:code[1]});const reused=await post('verifyEmail',{code:code[1]});check(!reused.ok,'Email code is single-use');
 await go('/rating/#tab-top_castle');await p.locator('[data-rating-table="castles"] tbody tr').first().waitFor();
 for(const tab of ['pvp','pk','exp','clans','clanpvp','alliances','castles']){await p.locator(`[data-rating="${tab}"]`).click();check(await p.locator(`[data-rating-table="${tab}"] tbody tr`).count()>0,'Rating '+tab)}
 await go('/cabinet/');await go('/cabinet/default/logout/');check(p.url().includes('/login/'),'Logout returns to login');
 await go('/cabinet/portal/warehouse/');check(p.url().includes('/login/'),'Cabinet protected after logout');
 const report={checks,completed_at:new Date().toISOString(),real_payments:false,real_game:false};fs.writeFileSync(path.join(dir,'flow-report.json'),JSON.stringify(report,null,2));
 await browser.close();console.log('PASS: '+checks+' staging end-to-end assertions');
})().catch(e=>{console.error(e);process.exit(1)});
