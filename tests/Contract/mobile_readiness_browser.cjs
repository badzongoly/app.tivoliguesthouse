const fs=require('fs'),assert=require('assert/strict'),path=require('path'),os=require('os');
const root=path.resolve(__dirname,'../..'),evidence=fs.mkdtempSync(path.join(os.tmpdir(),'tivoli-mobile-readiness-'));
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


 await login(f.email,'Synthetic Visibility Passphrase 123!');
 for(const [width,height] of [[320,740],[390,844],[768,1024],[1024,768],[1440,900]]) {
   await page('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<700});
   await nav('/dashboard.php');
   await check('document.querySelector("h1").textContent==="Today" && !document.body.innerText.includes("Vacant")','Today shows staff workflow at '+width);
   const ready=await ev('Array.from(document.querySelectorAll("a.card")).find(a=>a.href.includes("preparation=ready"))?.querySelector("strong")?.textContent');
   assert.match(ready,/^\d+$/);
   await check('document.documentElement.scrollWidth <= innerWidth + 1','Today reflows at '+width);
   const shot=await page('Page.captureScreenshot',{format:'png'});
   fs.writeFileSync(path.join(evidence,'today-'+width+'.png'),Buffer.from(shot.data,'base64'));
   await nav('/occupancy.php?preparation=ready');
   await check('document.querySelectorAll(".stay-room").length==='+Number(ready)+' && [...document.querySelectorAll(".stay-room")].every(room=>room.dataset.preparation==="ready"&&room.innerText.includes("Check In"))','Ready list matches Today and exposes check-in at '+width);
 }
 await page('Emulation.setDeviceMetricsOverride',{width:1024,height:768,deviceScaleFactor:1,mobile:false});
 await nav('/occupancy.php?preparation=ready');
 const nextLinks=await ev('[...document.querySelectorAll(".stay-room a")].filter(a=>a.href.includes("form=checkin")).map(a=>a.href.replace(location.origin,""))');
 assert(nextLinks.length>=2,'Two fixture rooms are needed to test switching');
 for(const link of nextLinks.slice(0,2)){
   await nav(link);
   await check('(() => { const room = document.querySelector(".stay-room[aria-current=true]"); const form = document.querySelector("[data-checkin-payment]"); return room.dataset.roomId===form.elements.room_id.value && document.querySelector(".stay-board-pane").getBoundingClientRect().left < form.getBoundingClientRect().left; })()','Tablet selection matches the active room and form');
   await check('document.documentElement.scrollWidth <= innerWidth + 1','Tablet selection fits without page overflow');
 }
 const shot=await page('Page.captureScreenshot',{format:'png'});
 fs.writeFileSync(path.join(evidence,'tablet-room-selection.png'),Buffer.from(shot.data,'base64'));
 await ev('(() => { const detail=document.querySelector(".stay-optional"); detail.open=true; const input=detail.querySelector("input[type=file]"); const selected=new DataTransfer(); selected.items.add(new File(["%PDF"],"synthetic-id.pdf",{type:"application/pdf"})); input.files=selected.files; input.dispatchEvent(new Event("change",{bubbles:true})); })()');
 await check('document.querySelector(".app-file-preview").innerText.includes("synthetic-id.pdf")','Attachment name visible before any upload');
 await login('admin@example.invalid','Synthetic Admin Passphrase 123!');
 await page('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
 await nav('/owner_reports.php?view=cash&start=2026-11-02&end=2026-11-08');
 await check('document.body.innerText.includes("Report Period") && [...document.links].some(link=>link.href.includes("page=payments")&&link.href.includes("start=2026-11-02")&&link.href.includes("end=2026-11-08"))','Owner report keeps the selected dates on the payment log link');
 await page('Emulation.setDeviceMetricsOverride',{width:320,height:740,deviceScaleFactor:1,mobile:true});
 await nav('/finance.php?page=payments&start=2026-11-02&end=2026-11-08');
 await check('getComputedStyle(document.querySelector(".finance-payment-table")).minWidth==="0px" && document.documentElement.scrollWidth <= innerWidth + 1','Payment Log fits a narrow phone');
 assert.deepEqual(errors,[]);
 console.log(checks+' mobile readiness assertions passed. Screenshots: '+evidence);
 }finally{if(context)await call('Target.disposeBrowserContext',{browserContextId:context});ws.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
