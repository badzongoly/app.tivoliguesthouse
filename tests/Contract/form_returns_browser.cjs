const fs=require('fs'),assert=require('assert/strict'),path=require('path'),os=require('os');
const root=path.resolve(__dirname,'../..'),evidence=fs.mkdtempSync(path.join(os.tmpdir(),'tivoli-form-returns-'));
(async()=>{
 const guard=await fetch('http://127.0.0.1:8099/auth/login.php');assert.equal(guard.headers.get('X-Tivoli-Test-Mode'),'synthetic');
 const version=await(await fetch('http://127.0.0.1:9236/json/version')).json(),ws=new WebSocket(version.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));let seq=0,session,context,checks=0,views=0;const pending=new Map(),errors=[];
 ws.addEventListener('message',e=>{const x=JSON.parse(e.data);if(x.method==='Runtime.exceptionThrown')errors.push(x.params.exceptionDetails.exception?.description||x.params.exceptionDetails.text);const p=pending.get(x.id);if(p){pending.delete(x.id);x.error?p.reject(Error(x.error.message)):p.resolve(x.result);}});
 const call=(method,params={},sid)=>new Promise((resolve,reject)=>{pending.set(++seq,{resolve,reject});ws.send(JSON.stringify({id:seq,method,params,...(sid?{sessionId:sid}:{})}));}),page=(m,p={})=>call(m,p,session);
 const ev=async expression=>{const x=await page('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(x.exceptionDetails)throw Error(x.exceptionDetails.exception?.description||x.exceptionDetails.text);return x.result.value;};
 const wait=ms=>new Promise(r=>setTimeout(r,ms));async function nav(path){const url='http://127.0.0.1:8099'+path;await page('Page.navigate',{url});for(let i=0;i<1000;i++){await wait(50);if(await ev('document.readyState==="complete"&&location.href==='+JSON.stringify(url))){await wait(100);return;}}throw Error('Navigation timeout');}
 async function login(email,password){await call('Storage.clearCookies',{browserContextId:context});await nav('/auth/login.php');await ev('(async()=>{await fetch("/auth/login.php",{method:"POST",body:new URLSearchParams({csrf:document.querySelector("[name=csrf]").value,email:'+JSON.stringify(email)+',password:'+JSON.stringify(password)+'})});})()');}
 async function check(expression,label){assert(await ev(expression),label);checks++;console.log('PASS '+label);}
 try{context=(await call('Target.createBrowserContext')).browserContextId;const target=(await call('Target.createTarget',{url:'about:blank',browserContextId:context})).targetId;session=(await call('Target.attachToTarget',{targetId:target,flatten:true})).sessionId;await page('Page.enable');await page('Runtime.enable');

 await login('admin@example.invalid','Synthetic Admin Passphrase 123!');
 await page('Network.enable');let writes=0;ws.addEventListener('message',e=>{const x=JSON.parse(e.data);if(x.sessionId===session&&x.method==='Network.requestWillBeSent'&&x.params.request.method==='POST')writes++;});
 async function arrived(predicate){for(let i=0;i<600;i++){await wait(50);if(await ev('document.readyState==="complete"&&('+predicate+')')){await wait(100);return;}}throw Error('Navigation timeout');}
 const cases=[['followups','/weekly-audits.php?status=closed&before=0','Record Room Audit','room_audit'],['rooms','/weekly-audits.php?page=room&range=all&before=0','Record Audit','room_audit'],['areas','/weekly-audits.php?page=area&range=all&before=0','Record Audit','area_audit'],['purchases','/purchases.php?page=history&before=0','New Request','request'],['counts','/stock-counts.php?page=activity&before=0','Start Count','start'],['issues','/stock-issues.php?before=0','Report / Request Issue','report'],['income','/finance.php?page=income&period=month&start=2026-11-01','Record Activity Payment','income_record']];
 for(const [name,source,label,action] of cases){await nav(source);const from=await ev('location.href');writes=0;await ev('[...document.links].find(a=>a.textContent.trim()==='+JSON.stringify(label)+').click()');await arrived(action==='report'?'!!document.querySelector("#stockissue-form")':'!!document.querySelector("form [name=action][value='+action+']")');
 await check('[...document.links].find(a=>a.textContent.trim()==="Cancel")?.href==='+JSON.stringify(from),name+' Cancel returns to its opening page and filters');
 await ev('for(const e of document.querySelectorAll("form textarea"))e.value="Unsaved synthetic cancellation check";[...document.links].find(a=>a.textContent.trim()==="Cancel").click()');await arrived('location.href==='+JSON.stringify(from));assert.equal(writes,0,name+' Cancel must make no POST');checks++;console.log('PASS '+name+' cancel navigates without saving');
 }
 assert.deepEqual(errors,[]);console.log('TOTAL '+checks+' live form return assertions passed');
 }finally{if(context)await call('Target.disposeBrowserContext',{browserContextId:context});ws.close();}
})().catch(e=>{console.error(e);process.exit(1)});
