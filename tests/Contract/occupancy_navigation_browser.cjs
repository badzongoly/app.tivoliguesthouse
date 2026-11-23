const fs=require('fs'),assert=require('assert/strict'),path=require('path'),os=require('os');
const root=path.resolve(__dirname,'../..'),evidence=fs.mkdtempSync(path.join(os.tmpdir(),'tivoli-occupancy-navigation-'));
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

 const cases=[
 ['staff','guest','/occupancy.php?booking_id='+f.booking,'Guest Stay Account'],
 ['staff','payment','/occupancy.php?booking_id='+f.booking+'&form=payment','Guest Stay Account'],
 ['staff','checkin','/occupancy.php?room_id='+f.ready_room+'&form=checkin','Room Board'],
 ['staff','clean','/occupancy.php?section=housekeeping&id='+f.attempt+'&form=clean','Room Board'],
 ['staff','original','/occupancy.php?section=housekeeping&id='+f.source_attempt+'&from_stay='+f.source_booking,'Guest Stay Account'],
 ['owner','incident','/occupancy.php?booking_id='+f.incident_booking,'Guest Stay Account'],
 ['rooms','rooms','/occupancy.php?section=housekeeping','Room Board']];
 let role='';for(const width of [1440,390,320]){await page('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
 for(const [who,name,path,parent] of cases){
 if(role!==who){await login(who==='owner'?'admin@example.invalid':who==='rooms'?f.rooms_email:f.email,who==='owner'?'Synthetic Admin Passphrase 123!':who==='rooms'?'Synthetic Rooms Only Passphrase 123!':'Synthetic Visibility Passphrase 123!');role=who;}
 await nav(path);
 await check('document.querySelectorAll("nav[aria-label=Stays]").length===1',name+' one main stays navigation '+width);
 await check('JSON.stringify([...document.querySelectorAll("nav[aria-label=Stays] [aria-current=page]")].map(a=>a.textContent))==='+JSON.stringify(JSON.stringify([parent])),name+' correct single parent tab');
 await check('(()=>{const a=document.querySelector("nav[aria-label=Stays] [aria-current=page]"),s=getComputedStyle(a);return s.backgroundColor==="rgb(249, 251, 245)"&&s.color==="rgb(25, 62, 52)"})()',name+' selected parent is visibly highlighted');
 await check('document.documentElement.scrollWidth<=innerWidth',name+' page fits '+width);
 await check('[...document.querySelectorAll("[name=operation_key]")].every(x=>/^[a-f0-9]{32}$/.test(x.value))',name+' form operation keys remain intact');
 const specific={
 guest:'[...document.querySelectorAll("nav")].some(n=>n.getAttribute("aria-label")==="Guest account sections"&&n.textContent.includes("Stay Activity"))',
 payment:'![...document.links].some(a=>a.textContent==="Record Payment")&&document.querySelector("form [name=action][value=payment]")!==null',
 checkin:'document.querySelector("[name=action][value=checkin]")!==null&&![...document.links].some(a=>a.closest("[data-room-id]")?.dataset.roomId==="'+f.ready_room+'"&&a.textContent==="Check In")',
 clean:'![...document.links].some(a=>a.textContent==="Continue Cleaning"||a.textContent==="Back To Room Board")&&[...document.links].filter(a=>a.textContent==="Cleaning History").length===1',
 original:'!document.querySelector("input[name=action]")&&[...document.links].some(a=>a.textContent==="Back To Guest Stay Account"&&a.href.includes("booking_id='+f.source_booking+'"))',
 incident:'[...document.links].filter(a=>a.textContent==="Recover Outstanding").length===1',
 rooms:'![...document.links].some(a=>a.textContent==="Guest Stay Account"||a.textContent==="Back To Room Board")'
 };
 await check(specific[name],name+' consolidated actions and permissions');
 const shot=await page('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(evidence+'/'+name+'-'+width+'.png',Buffer.from(shot.data,'base64'));views++;
 }}assert.deepEqual(errors,[]);console.log('TOTAL '+checks+' occupancy browser assertions and '+views+' responsive views passed');console.log('EVIDENCE '+evidence);
 }finally{if(context)await call('Target.disposeBrowserContext',{browserContextId:context});ws.close();}
})().catch(e=>{console.error(e);process.exit(1)});
