const fs=require('fs'),assert=require('assert/strict'),path=require('path'),os=require('os');
const root=path.resolve(__dirname,'../..'),evidence=fs.mkdtempSync(path.join(os.tmpdir(),'tivoli-finance-stock-ux-'));
(async()=>{
 const guard=await fetch('http://127.0.0.1:8099/auth/login.php');assert.equal(guard.headers.get('X-Tivoli-Test-Mode'),'synthetic');const f=JSON.parse(fs.readFileSync(root+'/.local-setup/stock-issue-http-fixture.json','utf8'));
 const version=await(await fetch('http://127.0.0.1:9236/json/version')).json(),ws=new WebSocket(version.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));let seq=0,session,context,checks=0,views=0;const pending=new Map(),errors=[];
 ws.addEventListener('message',e=>{const x=JSON.parse(e.data);if(x.method==='Runtime.exceptionThrown')errors.push(x.params.exceptionDetails.exception?.description||x.params.exceptionDetails.text);const p=pending.get(x.id);if(p){pending.delete(x.id);x.error?p.reject(Error(x.error.message)):p.resolve(x.result);}});
 const call=(method,params={},sid)=>new Promise((resolve,reject)=>{pending.set(++seq,{resolve,reject});ws.send(JSON.stringify({id:seq,method,params,...(sid?{sessionId:sid}:{})}));}),page=(m,p={})=>call(m,p,session);
 const ev=async expression=>{const x=await page('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(x.exceptionDetails)throw Error(x.exceptionDetails.exception?.description||x.exceptionDetails.text);return x.result.value;};
 const wait=ms=>new Promise(r=>setTimeout(r,ms));async function nav(path){const url='http://127.0.0.1:8099'+path;await page('Page.navigate',{url});for(let i=0;i<1000;i++){await wait(50);if(await ev('document.readyState==="complete"&&location.href==='+JSON.stringify(url))){await wait(100);return;}}throw Error('Navigation timeout');}
 async function login(email,password){await call('Storage.clearCookies',{browserContextId:context});await nav('/auth/login.php');await ev('(async()=>{await fetch("/auth/login.php",{method:"POST",body:new URLSearchParams({csrf:document.querySelector("[name=csrf]").value,email:'+JSON.stringify(email)+',password:'+JSON.stringify(password)+'})});})()');}
 async function check(expression,label){assert(await ev(expression),label);checks++;console.log('PASS '+label);}
 try{context=(await call('Target.createBrowserContext')).browserContextId;const target=(await call('Target.createTarget',{url:'about:blank',browserContextId:context})).targetId;session=(await call('Target.attachToTarget',{targetId:target,flatten:true})).sessionId;await page('Page.enable');await page('Runtime.enable');

 await login(f.users.staff.email,'Synthetic Stock Passphrase 123!');
 for(const width of [1440,390,320]){
 await page('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});await nav('/stock-counts.php?page=new');
 await ev('window.picker=document.querySelector("[data-count-picker]");window.boxes=[...picker.querySelectorAll("input[type=checkbox]")];window.initialKey=picker.querySelector("[name=operation_key]").value;');
 await check('picker&&boxes.length>0&&picker.querySelector("[data-count-start]").disabled','Empty selection cannot start at '+width);
 await ev('picker.querySelector("[data-count-select]").click()');
 await check('boxes.filter(b=>b.checked).length===Math.min(50,boxes.length)&&!picker.querySelector("[data-count-start]").disabled','Select Shown respects 50-item cap');
 await ev('window.chosen=boxes.filter(b=>b.checked).length;window.search=picker.querySelector("[data-count-search]");search.value="no-such-synthetic-item-90817";search.dispatchEvent(new Event("input",{bubbles:true}));');
 await check('!picker.querySelector("[data-count-empty]").hidden&&boxes.filter(b=>b.checked).length===chosen','Search shows no-results and retains selected items');
 await ev('picker.querySelector("[data-count-clear]").click();search.value="";search.dispatchEvent(new Event("input",{bubbles:true}));boxes[0].click();');
 await check('boxes.filter(b=>b.checked).length===1&&picker.querySelector("[data-count-start]").textContent==="Start Counting (1)"','Clear and single selection update feedback');
 await check('picker.querySelector("[name=purpose]").value==="Weekly Stock Count"&&!picker.querySelector("details").open&&!document.body.innerText.includes("Expected At Count")','Weekly default stays simple and balances blind');
 await check('picker.querySelector("[name=operation_key]").value===initialKey&&/^[a-f0-9]{32}$/.test(initialKey)','Picker interactions preserve durable operation key');
 await ev('window.sends=0;picker.addEventListener("submit",e=>{e.preventDefault();sends++;window.sent=[...new FormData(picker).entries()];});picker.querySelector("[data-count-start]").click();');
 await check('sends===1&&sent.filter(([k])=>k==="items[]").length===1&&sent.some(([k,v])=>k==="operation_key"&&v===initialKey)&&sent.some(([k,v])=>k==="csrf"&&v.length>0)','Start submits original fields once without writing browser fixture');
 await check('document.documentElement.scrollWidth<=innerWidth','Stock start fits '+width);
 const shot=await page('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(evidence+'/stock-start-'+width+'.png',Buffer.from(shot.data,'base64'));views++;
 }
 await login('admin@example.invalid','Synthetic Admin Passphrase 123!');
 for(const width of [1440,390]){
 await page('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
 for(const path of ['/purchases.php','/expenses.php','/financial-corrections.php?area=purchases','/finance.php?page=deposits&form=deposit_record']){
 await nav(path);await check('document.querySelectorAll(".finance-navigation").length===1','One primary finance navigation '+path+' '+width);
 await check('![...document.querySelectorAll(".finance-navigation a")].some(a=>a.textContent==="Provider Payments"||a.textContent==="Payroll & Staff Cost")','Overlapping workspace entries consolidated');
 await check('document.documentElement.scrollWidth<=innerWidth','Finance page fits '+width);
 const shot=await page('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(evidence+'/finance-'+width+'-'+views+'.png',Buffer.from(shot.data,'base64'));views++;
 }}
 assert.deepEqual(errors,[]);console.log('TOTAL '+checks+' stock picker / finance browser assertions and '+views+' responsive views passed');console.log('EVIDENCE '+evidence);
 }finally{if(context)await call('Target.disposeBrowserContext',{browserContextId:context});ws.close();}
})().catch(e=>{console.error(e);process.exit(1)});
