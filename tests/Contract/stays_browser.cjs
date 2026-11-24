const fs=require('fs');
(async()=>{
let target;for(let attempt=0;attempt<60;attempt++){try{target=await(await fetch('http://127.0.0.1:9233/json/new?about:blank',{method:'PUT'})).json();break;}catch(error){await new Promise(r=>setTimeout(r,100));}}if(!target)throw new Error('QA browser did not become ready');const ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));let id=0;const pending=new Map();ws.addEventListener('message',e=>{const d=JSON.parse(e.data);if(pending.has(d.id)){const p=pending.get(d.id);pending.delete(d.id);d.error?p.reject(new Error(d.error.message)):p.resolve(d.result);}});const call=(method,params={})=>new Promise((resolve,reject)=>{pending.set(++id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});const ev=async expression=>{const r=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.text);return r.result.value;};
async function nav(path){const url='http://127.0.0.1:8099'+path;await call('Page.navigate',{url});for(let i=0;i<100;i++){await new Promise(r=>setTimeout(r,80));if(await ev('location.href==='+JSON.stringify(url)+'&&document.readyState==="complete"'))return;}throw new Error('Navigation failed '+path);}
await call('Page.enable');await call('Network.clearBrowserCookies');await nav('/auth/login.php');if(!await ev(`(async()=>(await fetch('/auth/login.php')).headers.get('X-Tivoli-Test-Mode')==='synthetic')()`))throw new Error('Unverified HTTP fixture server');
await ev(`(async()=>{const csrf=document.querySelector('[name=csrf]').value;await fetch('/auth/login.php',{method:'POST',body:new URLSearchParams({csrf,email:'admin@example.invalid',password:'Synthetic Admin Passphrase 123!'}),redirect:'manual'});})()`);
const fixture=JSON.parse(fs.readFileSync('.local-setup/stays-http-fixture.json','utf8'));
const pages=['/cleaning-settings.php?type=areas','/occupancy.php?section=areas','/occupancy.php?section=areas&range=all','/occupancy.php?section=areas&form=area_audit','/occupancy.php?section=rooms&form=room_audit&room_id='+fixture.room_id,'/occupancy.php?section=rooms&form=repair&id='+fixture.fault_id,'/occupancy.php?section=rooms&form=fault&room_id='+fixture.room_id,'/occupancy.php?section=rooms&form=room_save&id='+fixture.room_id,'/cleaning-settings.php?type=inspection','/occupancy.php?form=inspect&room_id='+fixture.room_id,'/cleaning-settings.php','/occupancy.php?form=clean&room_id='+fixture.room_id,'/occupancy.php?section=folios','/occupancy.php?section=activity','/occupancy.php','/occupancy.php?tab=short','/occupancy.php?section=folios&range=all','/occupancy.php?section=folios&start=2001-01-03&end=2001-01-03',...['bill','payments','statement','activity'].map(tab=>'/occupancy.php?booking_id='+fixture.booking_id+'&tab='+tab),'/occupancy.php?form=checkin&room_id='+fixture.room_id,'/occupancy.php?section=extras','/occupancy.php?section=extras&tab=stock','/occupancy.php?section=extras&form=extra_save',...['master','faults','audits'].map(tab=>'/occupancy.php?section=rooms&tab='+tab)];
for(const width of [1440,390,320]){await call('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<1000});for(let i=0;i<pages.length;i++){await nav(pages[i]);const result=await ev(`({width:innerWidth,scroll:document.documentElement.scrollWidth,error:/Fatal error|Warning:|Parse error|\u00c2[\u0080-\u00bf]|\u00c3[\u0080-\u00bf]|\ufffd/.test(document.body.innerText),shell:!!document.querySelector('#workspace-main'),heading:document.querySelector('h1')?.textContent})`);if(result.scroll>width||result.width>width||result.error||!result.shell)throw new Error(pages[i]+' '+width+' '+JSON.stringify(result));if(width!==320&&[0,3,7,8,10,11].includes(i)){const shot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync('.local-setup/stays-page-'+i+'-'+width+'.png',Buffer.from(shot.data,'base64'));}}
console.log('PASS 29 occupancy views at '+width+'px without horizontal page overflow');}
await nav('/occupancy.php?form=checkin&room_id='+fixture.room_id+'#stay-action');await new Promise(r=>setTimeout(r,500));if(!await ev(`document.activeElement?.name==='guest_name'`))throw new Error('Check-in action did not focus guest name');console.log('PASS action anchor focuses the check-in guest field');

await nav('/occupancy.php?form=clean&room_id='+fixture.room_id+'#stay-action');
if(!await ev(`document.querySelectorAll('[data-cleaning-step]').length===5&&document.querySelectorAll('[data-cleaning-step]:not([hidden])').length===1`))throw new Error('Wizard must start on one visible stage');
await ev(`document.querySelector('[data-cleaning-next]').click()`);
if(!await ev(`document.querySelector('[data-cleaning-progress]').textContent.startsWith('Step 1 ')`))throw new Error('Wizard advanced without required checks');
await ev(`document.querySelector('[data-cleaning-step]').querySelectorAll('input').forEach(input=>input.checked=true);document.querySelector('[data-cleaning-next]').click()`);
if(!await ev(`document.querySelector('[data-cleaning-progress]').textContent.startsWith('Step 2 ')&&document.activeElement===document.querySelectorAll('[data-cleaning-step]')[1]`))throw new Error('Wizard did not focus next stage');
await ev(`document.querySelector('[data-cleaning-back]').click()`);
if(!await ev(`Array.from(document.querySelector('[data-cleaning-step]').querySelectorAll('input')).every(input=>input.checked)`))throw new Error('Back lost completed checks');
await ev(`for(let i=0;i<5;i++){document.querySelectorAll('[data-cleaning-step]')[i].querySelectorAll('input').forEach(input=>input.checked=true);document.querySelector('[data-cleaning-next]').click();}`);
if(!await ev(`!document.querySelector('[data-cleaning-review]').hidden&&document.querySelector('[data-cleaning-next]').hidden&&!document.querySelector('[name=reason]').required&&document.querySelector('[data-cleaning-progress]').textContent.startsWith('Step 6 ')`))throw new Error('Wizard review or optional notes incorrect');
console.log('PASS wizard rejects incomplete stages, focuses next stage, retains Back checks and reaches optional-note review');
const shot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync('.local-setup/cleaning-wizard-review-mobile.png',Buffer.from(shot.data,'base64'));
await ev(`document.querySelector('[data-cleaning-back]').click()`);
const stepShot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync('.local-setup/cleaning-wizard-step-mobile.png',Buffer.from(stepShot.data,'base64'));


await nav('/occupancy.php?form=inspect&room_id='+fixture.room_id+'#stay-action');
await new Promise(r=>setTimeout(r,200));
if(!await ev(`document.querySelectorAll('[data-inspection-checklist] input[type=checkbox][required]').length===6&&!document.querySelector('[data-inspection-checklist] [name=reason]').required&&document.activeElement?.name==='done_v1_1'`))throw new Error('Inspection required checks, optional notes or action focus failed');
console.log('PASS inspection has six required checks, optional notes and action focus');
const inspectionShot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync('.local-setup/inspection-checklist-mobile.png',Buffer.from(inspectionShot.data,'base64'));

await nav('/occupancy.php?section=folios');
if(!await ev(`JSON.stringify(Array.from(document.querySelectorAll('nav[aria-label=Stays] a')).map(a=>a.textContent))===JSON.stringify(['Overnight','Short Stay','Guest Stay Account','Activity'])&&document.querySelector('[name=start]').value!==''&&document.querySelector('[name=end]').value!==''&&!document.querySelector('#stay-accounts').textContent.includes('Balance')`))throw new Error('Account navigation, default filter or columns incorrect');
await call('Emulation.setDeviceMetricsOverride',{width:390,height:1000,deviceScaleFactor:1,mobile:true});
const accountShot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync('.local-setup/guest-stay-account-mobile.png',Buffer.from(accountShot.data,'base64'));
console.log('PASS account tab order, populated default dates and removed balance column');

for(const width of [1440,390,320]){
await call('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<1000});
await nav('/occupancy.php?section=rooms&form=room_save&id='+fixture.room_id+'#stay-action');
if(!await ev(`(()=>{const group=document.querySelector('.stay-form-actions'),b=group.querySelector('button').getBoundingClientRect(),a=group.querySelector('a').getBoundingClientRect();return Math.abs(b.top-a.top)<3&&!!document.querySelector('select[name=type][required]')&&!document.querySelector('[name=reason]').required;})()`))throw new Error('Room form actions, type list or optional comment failed at '+width);
}
console.log('PASS room form dropdown and optional Comment; Save Room/Cancel align at desktop and mobile widths');
const roomFormShot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync('.local-setup/room-form-mobile.png',Buffer.from(roomFormShot.data,'base64'));

await nav('/occupancy.php?section=areas&form=area_audit#stay-action');await new Promise(r=>setTimeout(r,200));
if(!await ev(`document.querySelectorAll('[name^=check_]').length===0&&!!document.querySelector('select[name=area_id][required]')&&!!document.querySelector('select[name=result][required]')&&document.activeElement?.name==='area_id'`))throw new Error('Area audit checks or focus missing');
const areaShot=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync('.local-setup/weekly-area-audit-mobile.png',Buffer.from(areaShot.data,'base64'));
console.log('PASS area audit uses Area dropdown, overall Result and action focus');
await fetch('http://127.0.0.1:9233/json/close/'+target.id);ws.close();
})().catch(e=>{console.error(e);process.exit(1)});
