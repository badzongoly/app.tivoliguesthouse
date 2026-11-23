const assert=require('assert/strict');
(async()=>{
 const guard=await fetch('http://127.0.0.1:8099/auth/login.php');assert.equal(guard.headers.get('X-Tivoli-Test-Mode'),'synthetic');
 const version=await(await fetch('http://127.0.0.1:9236/json/version')).json(),ws=new WebSocket(version.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));let seq=0,session,context,checks=0;const pending=new Map(),errors=[];
 ws.addEventListener('message',e=>{const x=JSON.parse(e.data);if(x.method==='Runtime.exceptionThrown')errors.push(x.params.exceptionDetails.exception?.description||x.params.exceptionDetails.text);const p=pending.get(x.id);if(p){pending.delete(x.id);x.error?p.reject(Error(x.error.message)):p.resolve(x.result);}});
 const call=(method,params={},sid)=>new Promise((resolve,reject)=>{pending.set(++seq,{resolve,reject});ws.send(JSON.stringify({id:seq,method,params,...(sid?{sessionId:sid}:{})}));}),page=(m,p={})=>call(m,p,session);
 const ev=async expression=>{const x=await page('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(x.exceptionDetails)throw Error(x.exceptionDetails.exception?.description||x.exceptionDetails.text);return x.result.value;};
 const wait=ms=>new Promise(r=>setTimeout(r,ms));async function nav(path){const url='http://127.0.0.1:8099'+path;await page('Page.navigate',{url});for(let i=0;i<1000;i++){await wait(50);if(await ev('document.readyState==="complete"&&location.href==='+JSON.stringify(url))){await wait(100);return;}}throw Error('Navigation timeout');}
 async function login(email,password){await call('Storage.clearCookies',{browserContextId:context});await nav('/auth/login.php');await ev('(async()=>{await fetch("/auth/login.php",{method:"POST",body:new URLSearchParams({csrf:document.querySelector("[name=csrf]").value,email:'+JSON.stringify(email)+',password:'+JSON.stringify(password)+'})});})()');}
 async function check(expression,label){assert(await ev(expression),label);checks++;console.log('PASS '+label);}
 try{context=(await call('Target.createBrowserContext')).browserContextId;const target=(await call('Target.createTarget',{url:'about:blank',browserContextId:context})).targetId;session=(await call('Target.attachToTarget',{targetId:target,flatten:true})).sessionId;await page('Page.enable');await page('Runtime.enable');


 await login('admin@example.invalid','Synthetic Admin Passphrase 123!');
 const viewsToCheck=[
  ['/occupancy.php','Stays','Room Board'],
  ['/weekly-audits.php','Property & Audits','Follow-Ups'],
  ['/finance.php','Finance sections','Money Overview'],
  ['/purchases.php','Purchase Sections','Requests'],
  ['/handover.php','Handover Sections',null],
  ['/payroll.php','Payroll',null],
  ['/owner_reports.php','Owner report views','Daily Overview'],
  ['/reminders.php','Reminder Views','To Act On'],
  ['/reminders.php?history=1','Reminder Views','History'],
  ['/reminders.php?page=settings','Settings','Notifications'],
  ['/cleaning-settings.php','Settings','Cleaning Checklist'],
  ['/users.php#roles','System Access Sections','Roles'],
 ];
 const navSelector=label=>'[...document.querySelectorAll("nav.workspace-tab-strip")].find(n=>n.getAttribute("aria-label")==='+JSON.stringify(label)+')';
 for(const width of [390,1440]){
   await page('Emulation.setDeviceMetricsOverride',{width,height:950,deviceScaleFactor:1,mobile:width<681});
   for(const [url,label,expected] of viewsToCheck){
      await nav(url);
      const sel=navSelector(label);
      await check('!!('+sel+')','Shared strip exists for '+label+' at '+width);
      const actual=await ev('('+sel+').querySelector("a[aria-current=page]")?.textContent.trim()');
      if(expected)assert.equal(actual,expected);
      const count=await ev('('+sel+').querySelectorAll("a").length');
      if(count<2)continue;
      if(width<681){
         await check('(()=>{const n='+sel+';return !n.querySelector(".workspace-tab-controls").hidden&&n.querySelector(".workspace-tab-links").hidden&&n.querySelector(".workspace-current-tab").textContent===('+JSON.stringify(actual||label)+')})()','Mobile current view visible for '+label);
         await ev('('+sel+').querySelector(".workspace-tab-more").click()');
         await check('(()=>{const n='+sel+';return !n.querySelector(".workspace-tab-links").hidden&&n.querySelector(".workspace-tab-more").getAttribute("aria-expanded")==="true"})()','More opens for '+label);
         await ev('('+sel+').querySelector(".workspace-tab-more").dispatchEvent(new KeyboardEvent("keydown",{key:"Escape",bubbles:true}))');
         await check('(()=>{const n='+sel+';return n.querySelector(".workspace-tab-links").hidden&&document.activeElement===n.querySelector(".workspace-tab-more")})()','Escape restores focus for '+label);
      }else{
         await check('(()=>{const n='+sel+';const current=n.querySelector("a[aria-current=page]");return n.querySelector(".workspace-tab-controls").hidden&&!n.querySelector(".workspace-tab-links").hidden&&(!current||getComputedStyle(current).backgroundColor==="rgb(249, 251, 245)")})()','Desktop strip visible for '+label);
      }
   }
 }
 await page('Emulation.setDeviceMetricsOverride',{width:390,height:950,deviceScaleFactor:1,mobile:true});
 await nav('/users.php#roles');
 const access=navSelector('System Access Sections');
 await ev('('+access+').querySelector(".workspace-tab-more").click()');
 await ev('document.querySelector("[data-access-section=privileges]").click()');
 await check('('+access+').querySelector(".workspace-current-tab").textContent==="Privileges"&&('+access+').querySelector(".workspace-tab-links").hidden','Same-page tab switch updates the mobile current label');
 assert.deepEqual(errors,[]);
 console.log('TOTAL '+checks+' shared workspace navigation checks passed');
 }finally{if(context)await call('Target.disposeBrowserContext',{browserContextId:context});ws.close();}
})().catch(e=>{console.error(e);process.exit(1)});
