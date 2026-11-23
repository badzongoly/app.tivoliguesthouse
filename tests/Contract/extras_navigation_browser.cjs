const fs=require('fs'),assert=require('assert/strict'),path=require('path'),os=require('os');
const root=path.resolve(__dirname,'../..'),evidence=fs.mkdtempSync(path.join(os.tmpdir(),'tivoli-extras-navigation-'));
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

 for(const [who,email,password] of [['owner','admin@example.invalid','Synthetic Admin Passphrase 123!'],['clerk',f.users.staff.email,'Synthetic Stock Passphrase 123!']]){
 await login(email,password);
 for(const width of [1440,390,320]){
 await page('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
 for(const path of ['/occupancy.php?section=extras','/stock-counts.php','/stock-issues.php']){
 await nav(path);
 await check('document.querySelectorAll(".extras-navigation").length===1',who+' one shared navigation '+path+' '+width);
 await check('[...document.querySelectorAll("nav a")].filter(a=>a.textContent==="Non-Sale Issues").length===1',who+' non-sale entry appears once');
 await check('document.querySelectorAll(".extras-navigation [aria-current=page]").length===1',who+' one active main tab');
 await check('document.documentElement.scrollWidth<=innerWidth',who+' page fits '+width);
 const shot=await page('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(evidence+'/extras-'+who+'-'+width+'-'+views+'.png',Buffer.from(shot.data,'base64'));views++;
 }}}
 assert.deepEqual(errors,[]);console.log('TOTAL '+checks+' extras navigation browser assertions and '+views+' responsive views passed');console.log('EVIDENCE '+evidence);
 }finally{if(context)await call('Target.disposeBrowserContext',{browserContextId:context});ws.close();}
})().catch(e=>{console.error(e);process.exit(1)});
