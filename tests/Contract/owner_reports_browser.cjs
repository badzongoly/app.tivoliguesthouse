const fs=require('fs');
(async()=>{
let target;for(let attempt=0;attempt<60;attempt++){try{target=await(await fetch('http://127.0.0.1:9233/json/new?about:blank',{method:'PUT'})).json();break;}catch(error){await new Promise(r=>setTimeout(r,100));}}if(!target)throw new Error('QA browser did not become ready');const ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));let id=0;const pending=new Map();ws.addEventListener('message',e=>{const d=JSON.parse(e.data);if(pending.has(d.id)){const p=pending.get(d.id);pending.delete(d.id);d.error?p.reject(new Error(d.error.message)):p.resolve(d.result);}});const call=(method,params={})=>new Promise((resolve,reject)=>{pending.set(++id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});const ev=async expression=>{const r=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.text);return r.result.value;};
async function nav(path){const url='http://127.0.0.1:8099'+path;await call('Page.navigate',{url});for(let i=0;i<100;i++){await new Promise(r=>setTimeout(r,80));if(await ev('location.href==='+JSON.stringify(url)+'&&document.readyState==="complete"'))return;}throw new Error('Navigation failed '+path);}
await call('Page.enable');await call('Network.clearBrowserCookies');await nav('/auth/login.php');if(!await ev(`(async()=>(await fetch('/auth/login.php')).headers.get('X-Tivoli-Test-Mode')==='synthetic')()`))throw new Error('Unverified HTTP fixture server');
await ev(`(async()=>{const csrf=document.querySelector('[name=csrf]').value;await fetch('/auth/login.php',{method:'POST',body:new URLSearchParams({csrf,email:'admin@example.invalid',password:'Synthetic Admin Passphrase 123!'}),redirect:'manual'});})()`);

const pages=['daily','room','cash','extras','monthly'].map(view=>'/owner_reports.php?view='+view);
for(const width of [1440,390,320]){
  await call('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<1000});
  for(let i=0;i<pages.length;i++){
    await nav(pages[i]);
    const result=await ev("({scroll:document.documentElement.scrollWidth, tabs:document.querySelectorAll('.or-tabs a').length, current:document.querySelectorAll('.or-tabs [aria-current=page]').length, alert:document.querySelector('[role=alert]')?.innerText, heading:document.querySelector('h1')?.textContent})");
    if(result.scroll>width||result.tabs!==5||result.current!==1||result.alert||!result.heading)throw new Error(pages[i]+' '+width+' '+JSON.stringify(result));
    const shot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
    fs.writeFileSync('.local-setup/owner-report-'+i+'-'+width+'.png',Buffer.from(shot.data,'base64'));
  }
  console.log('PASS five Owner Reports at '+width+'px');
}
await nav(pages[0]);
await ev("document.querySelector('.or-tabs a').focus()");
await call('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
await call('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
if(!await ev("document.activeElement?.textContent.trim()==='Room Performance'"))throw new Error('Report links are not sequentially keyboard reachable');
console.log('PASS report link keyboard order');
ws.close();
await fetch('http://127.0.0.1:9233/json/close/'+target.id);
})().catch(e=>{console.error(e);process.exit(1);});
