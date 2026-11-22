const fs=require('fs');
(async()=>{
 const target=await(await fetch('http://127.0.0.1:9230/json/new?about:blank',{method:'PUT'})).json();
 const ws=new WebSocket(target.webSocketDebuggerUrl);
 await new Promise(resolve=>ws.addEventListener('open',resolve,{once:true}));
 let id=0;const pending=new Map();ws.addEventListener('message',event=>{const data=JSON.parse(event.data);if(pending.has(data.id)){const p=pending.get(data.id);pending.delete(data.id);data.error?p.reject(new Error(data.error.message)):p.resolve(data.result);}});
 const call=(method,params={})=>new Promise((resolve,reject)=>{pending.set(++id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.text);return r.result.value;};
 async function nav(path){const url='http://127.0.0.1:8099'+path;await call('Page.navigate',{url});let ready=false;for(let i=0;i<100;i++){await new Promise(resolve=>setTimeout(resolve,100));if(await evaluate('location.href==='+JSON.stringify(url)+'&&document.readyState==="complete"')){ready=true;break;}}if(!ready)throw new Error('Navigation did not complete: '+path);await new Promise(resolve=>setTimeout(resolve,180));}
 await call('Page.enable');await nav('/auth/login.php');
 if(!await evaluate(`(async()=>(await fetch('/auth/login.php')).headers.get('X-Tivoli-Test-Mode')==='synthetic')()`))throw new Error('Refusing unverified HTTP test server');
 await evaluate(`(async()=>{const csrf=document.querySelector('[name=csrf]').value;await fetch('/auth/login.php',{method:'POST',body:new URLSearchParams({csrf,email:'admin@example.invalid',password:'Synthetic Admin Passphrase 123!'}),redirect:'manual'});})()`);
 const pages=['owner_reports.php','bookings.php','checkin.php?room_id=2','checkout.php?booking_id=1','rooms.php','extras.php','stock.php','extra_sales.php','payments.php','reports.php','admin_recovery.php','expenses.php','users.php','audit.php','auth/change-password.php','auth/logout.php'];
 for(const width of [1440,390,320]){
  await call('Emulation.setDeviceMetricsOverride',{width,height:width===1440?1000:844,deviceScaleFactor:1,mobile:width<1000});
  for(const page of pages){
   await nav('/'+page);
   const result=await evaluate(`({width:innerWidth,scroll:document.documentElement.scrollWidth,main:!!document.querySelector('#workspace-main'),errors:/Fatal error|Warning:|Parse error/.test(document.body.innerText),overflow:[...document.querySelectorAll('main *')].filter(e=>e.getBoundingClientRect().right>innerWidth+2&&getComputedStyle(e).position!=='fixed'&&!e.closest('.table-responsive,.scroll')).slice(0,5).map(e=>e.tagName+'.'+e.className)})`);
   if(result.scroll>width||!result.main||result.errors)throw new Error(page+' '+width+' '+JSON.stringify(result));
   if(['owner_reports.php','expenses.php','users.php'].includes(page)&&width!==320){const shot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync('.local-setup/layout-'+page.split('.')[0]+'-'+width+'.png',Buffer.from(shot.data,'base64'));}
  }
  await nav('/expenses.php');
  if(width<1000){
   await evaluate(`document.getElementById('app-menu').click()`);
   const open=await evaluate(`document.body.classList.contains('app-menu-open')&&!document.getElementById('app-backdrop').hidden&&document.querySelector('.content').inert&&document.getElementById('app-sidebar').contains(document.activeElement)`);
   if(!open)throw new Error('Mobile drawer state/focus failed');
   await call('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
   if(!await evaluate(`!document.body.classList.contains('app-menu-open')&&document.activeElement.id==='app-menu'`))throw new Error('Escape focus return failed');
  } else {
   await evaluate(`document.getElementById('app-menu').click()`);
   if(!await evaluate(`document.body.classList.contains('app-collapsed')`))throw new Error('Collapse failed');
   await evaluate(`document.getElementById('app-menu').click()`);
  }
  console.log('PASS '+pages.length+' pages at '+width+'px; navigation interaction passed');
 }
 await fetch('http://127.0.0.1:9230/json/close/'+target.id);ws.close();
})().catch(error=>{console.error(error);process.exit(1)});
