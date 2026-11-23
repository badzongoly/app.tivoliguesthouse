const fs=require('fs'),assert=require('assert/strict'),path=require('path'),os=require('os');
const root=path.resolve(__dirname,'../..'),evidence=fs.mkdtempSync(path.join(os.tmpdir(),'tivoli-extras-catalogue-'));
(async()=>{
 const guard=await fetch('http://127.0.0.1:8099/auth/login.php');assert.equal(guard.headers.get('X-Tivoli-Test-Mode'),'synthetic');const f=JSON.parse(fs.readFileSync(root+'/.local-setup/occupancy-ui-fixture.json','utf8'));
 const version=await(await fetch('http://127.0.0.1:9236/json/version')).json(),ws=new WebSocket(version.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));let seq=0,session,context,checks=0,views=0;const pending=new Map(),errors=[];
 ws.addEventListener('message',e=>{const x=JSON.parse(e.data);if(x.method==='Runtime.exceptionThrown')errors.push(x.params.exceptionDetails.exception?.description||x.params.exceptionDetails.text);const p=pending.get(x.id);if(p){pending.delete(x.id);x.error?p.reject(Error(x.error.message)):p.resolve(x.result);}});
 const call=(method,params={},sid)=>new Promise((resolve,reject)=>{pending.set(++seq,{resolve,reject});ws.send(JSON.stringify({id:seq,method,params,...(sid?{sessionId:sid}:{})}));}),page=(m,p={})=>call(m,p,session);
 const ev=async expression=>{const x=await page('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(x.exceptionDetails)throw Error(x.exceptionDetails.exception?.description||x.exceptionDetails.text);return x.result.value;};
 const wait=ms=>new Promise(r=>setTimeout(r,ms));async function nav(path){const url='http://127.0.0.1:8099'+path;await page('Page.navigate',{url});for(let i=0;i<1000;i++){await wait(50);if(await ev('document.readyState==="complete"&&location.href==='+JSON.stringify(url))){await wait(100);return;}}throw Error('Navigation timeout');}
 async function login(email,password){await call('Storage.clearCookies',{browserContextId:context});await nav('/auth/login.php');await ev('(async()=>{await fetch("/auth/login.php",{method:"POST",body:new URLSearchParams({csrf:document.querySelector("[name=csrf]").value,email:'+JSON.stringify(email)+',password:'+JSON.stringify(password)+'})});})()');}
 async function check(expression,label){assert(await ev(expression),label);checks++;console.log('PASS '+label);}
 try{context=(await call('Target.createBrowserContext')).browserContextId;const target=(await call('Target.createTarget',{url:'about:blank',browserContextId:context})).targetId;session=(await call('Target.attachToTarget',{targetId:target,flatten:true})).sessionId;await page('Page.enable');await page('Runtime.enable');



 await login('admin@example.invalid','Synthetic Admin Passphrase 123!');
 const route='/occupancy.php?section=extras&tab=catalogue';
 for(const width of [320,390,768,1440]){
   await page('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<700});
   await nav(route);
   await check('document.documentElement.scrollWidth<=innerWidth+1','Catalogue fits '+width+'px');
   await check('document.querySelector(".extras-catalogue-table")?.getAttribute("aria-label")==="Extras Catalogue" && ![...document.querySelectorAll(".catalogue-status")].some(s=>s.innerText.includes("Inactive"))','Catalogue defaults to active items');
   await check('document.querySelectorAll("[data-extra-id]").length<=40 && ![...document.querySelectorAll(".catalogue-details a")].some(a=>a.href.includes("stock-counts.php"))','Pagination and one shared count action');
   await ev('document.querySelector(".catalogue-details summary").click()');
   await check('document.querySelector(".catalogue-details details").open && document.querySelector(".catalogue-details details").innerText.includes("Item Status")','Item details expand at '+width+'px');
   const shot=await page('Page.captureScreenshot',{format:'png'});
   fs.writeFileSync(path.join(evidence,'catalogue-'+width+'.png'),Buffer.from(shot.data,'base64'));
 }
 await check('(()=>{const s=document.querySelector("select[name=catalogue_status]");return s.value==="active"&&[...s.options].map(o=>o.value).join(",")==="active,inactive,all"&&!Array.from(document.querySelectorAll("nav")).some(n=>n.getAttribute("aria-label")==="Catalogue Status");})()','Item status is a list filter with Active selected by default');
 await ev('(()=>{const s=document.querySelector("select[name=catalogue_status]"); s.value="inactive"; s.form.requestSubmit();})()');
 for(let i=0;i<150;i++){
   await wait(100);
   if(await ev('document.readyState==="complete"&&new URL(location.href).searchParams.get("catalogue_status")==="inactive"')) break;
 }
 await check('document.querySelector("select[name=catalogue_status]").value==="inactive"&&[...document.querySelectorAll(".catalogue-status")].every(s=>s.innerText.includes("Inactive"))','Applying the status filter shows only inactive items');
 await nav(route+'&catalogue_status=all&q=NoSyntheticProductMatchesThis');
 await check('!document.querySelector("[data-extra-id]") && document.body.innerText.includes("No items match these filters.")','Search shows a clear empty state');
 await nav(route+'&catalogue_status=all&sort=stock-low');
 const edit=await ev('[...document.querySelectorAll(".catalogue-details a")].find(a=>a.textContent==="Edit")?.href.replace(location.origin,"")');
 assert(edit);
 await nav(edit);
 await check('[...document.links].some(a=>a.textContent.trim()==="Cancel" && a.href.includes("sort=stock-low") && a.href.includes("catalogue_status=all"))','Cancel keeps catalogue filters');
 await login(f.email,'Synthetic Visibility Passphrase 123!');
 await nav(route);
 await check('document.body.innerText.includes("View Only") && ![...document.links].some(a=>a.href.includes("form=extra_save"))','Clerk cannot edit any catalogue record');
 assert.deepEqual(errors,[]);
 console.log(checks+' catalogue browser checks passed. Screenshots: '+evidence);
 }finally{if(context)await call('Target.disposeBrowserContext',{browserContextId:context});ws.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
