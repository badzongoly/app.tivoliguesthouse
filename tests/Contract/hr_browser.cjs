const fs=require('fs'),assert=require('assert/strict'),path=require('path'),os=require('os');
const root=path.resolve(__dirname,'../..'),evidence=fs.mkdtempSync(path.join(os.tmpdir(),'tivoli-hr-navigation-'));
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


 const f=JSON.parse(fs.readFileSync(root+'/.local-setup/hr-ux-fixture.json','utf8'));f.ids.unlinked=JSON.parse(fs.readFileSync(root+'/.local-setup/hr-browser-fixture.json','utf8')).staff_id;
 await login('admin@example.invalid','Synthetic Admin Passphrase 123!');
 await page('Network.enable');let posts=0;ws.addEventListener('message',e=>{const x=JSON.parse(e.data);if(x.sessionId===session&&x.method==='Network.requestWillBeSent'&&x.params.request.method==='POST')posts++;});
 async function arrived(url){for(let i=0;i<1000;i++){await wait(50);try{if(await ev('document.readyState==="complete"&&location.href==='+JSON.stringify(url))){await wait(100);return;}}catch{}}throw Error('Navigation timeout');}
 const cases=[['directory','/staff.php'],['new','/staff.php?new=1'],...['overview','employment','salary','leave','documents','access','activity'].map(t=>[t,'/staff.php?id='+f.ids.active+'&tab='+t]),['edit','/staff.php?id='+f.ids.active+'&edit=1'],['suspended','/staff.php?id='+f.ids.suspended+'&tab=employment'],['exited','/staff.php?id='+f.ids.exited+'&tab=employment'],['scheduled','/staff.php?id='+f.ids.exit_scheduled+'&tab=employment'],['draft-access','/staff.php?id='+f.ids.draft+'&tab=access'],['invite','/staff.php?id='+f.ids.unlinked+'&tab=access'],['providers','/hr-settings.php?section=payments'],['document-types','/hr-settings.php?section=documents']];
 for(const [name,path] of cases){await nav(path);for(const width of [1440,390,320]){await page('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
 await check('!!document.querySelector("#workspace-main")&&!/Fatal error|Warning:|Parse error|SQLSTATE/.test(document.body.innerText)',name+' healthy shared page '+width);
 await check('document.documentElement.scrollWidth<=innerWidth',name+' fits '+width);
 await check('[...document.querySelectorAll("[name=operation_key]")].every(x=>/^[a-f0-9]{32}$/.test(x.value))',name+' form keys intact '+width);views++;
 if(width===390&&['new','employment','invite','providers'].includes(name)){const shot=await page('Page.captureScreenshot',{format:'png'});fs.writeFileSync(evidence+'/'+name+'-390.png',Buffer.from(shot.data,'base64'));}
 }
 if(name==='directory')await check('[...document.querySelectorAll("#workspace-main a")].filter(a=>a.textContent.trim()==="New Staff Member").length===1','Directory has one New Staff entry');
 else if(name!=='providers'&&name!=='document-types')await check('![...document.querySelectorAll("#workspace-main a")].some(a=>a.textContent.trim()==="New Staff Member"||a.textContent.trim()==="Settings")','No repeated New Staff or generic Settings entries on '+name);
 if(['overview','employment','salary','leave','documents','access','activity'].includes(name))await check('(()=>{const n=document.querySelector(".app-section-nav");return n.children.length===7&&n.querySelectorAll("[aria-current=page]").length===1&&new Set([...n.children].map(a=>a.href)).size===7})()','Seven distinct profile sections; one selected on '+name);
 if(name==='employment')await check('JSON.stringify([...document.querySelectorAll("main form [name=action]")].map(x=>x.value).sort())===JSON.stringify(["confirm_probation","offboard","suspend"])','Active employment has only applicable decisions');
 if(name==='suspended')await check('JSON.stringify([...document.querySelectorAll("main form [name=action]")].map(x=>x.value).sort())===JSON.stringify(["offboard","resume"])','Suspended employment offers resume instead of suspend');
 if(name==='exited')await check('!document.querySelector("main form")','Exited employment record remains visible without terminal actions');
 if(name==='scheduled')await check('document.querySelector("[name=exit_date]").value>new Date().toISOString().slice(0,10)&&document.body.innerText.includes("Exit Date")','Scheduled exit retains existing future date');
 if(name==='draft-access')await check('!!document.querySelector("[name=action][value=link_user]")&&!document.querySelector("[name=action][value=new_account]")','Draft can link an existing user but cannot issue invitation');
 if(name==='invite')await check('document.querySelectorAll("details[name=hr-account-choice]").length===2&&document.querySelectorAll("details[name=hr-account-choice][open]").length===1','Existing account and invitation are clear alternate choices');
 }
 await nav('/staff.php?new=1');await check('document.querySelectorAll("[data-hr-step]").length===5&&document.querySelectorAll("[data-hr-panel]").length===5','Onboarding uses five steps');
 for(let step=0;step<5;step++){await ev('[...document.querySelectorAll("[data-hr-step]")]['+step+'].click()');await check('[...document.querySelectorAll("[data-hr-panel]")].filter(p=>!p.hidden).length===1&&!document.querySelectorAll("[data-hr-panel]")['+step+'].hidden&&document.activeElement.tagName==="H2"','Wizard step '+step+' focuses its content');}
 await check('document.querySelector("[name=contract_file]")&&!document.querySelector("[name=contract_file]").required&&[...document.querySelectorAll("input[type=file]")].find(x=>x.name==="identity_file[]").multiple','Optional contract and multiple ID attachments retained');
 await ev('document.querySelector("[data-hr-save]").click()');await check('!document.querySelectorAll("[data-hr-panel]")[0].hidden&&document.activeElement.name==="name"','Save validation focuses the required name even from Review');
 await nav('/staff.php?id='+f.ids.active+'&tab=salary');await ev('document.querySelector("[data-password-toggle=hr-momo-number]").click()');await check('document.querySelector("#hr-momo-number").type==="text"','MoMo visibility still works');
 const opening=await ev('location.href');await ev('[...document.links].find(a=>a.textContent.trim()==="Edit Profile").click()');for(let i=0;i<600;i++){await wait(50);if(await ev('!!document.querySelector("[data-hr-wizard]")'))break;}
 await check('[...document.links].find(a=>a.textContent.trim()==="Cancel").href==='+JSON.stringify(opening),'Edit Cancel preserves original Salary tab');
 await ev('document.querySelector("[name=name]").value="Unsaved synthetic name";[...document.links].find(a=>a.textContent.trim()==="Cancel").click()');await arrived(opening);assert.equal(posts,0);checks++;console.log('PASS browsing and Cancel post no business records');
 await login(f.email,f.password);await nav('/staff.php?id='+f.ids.active);
 await check('JSON.stringify([...document.querySelectorAll(".app-section-nav a")].map(a=>a.textContent.trim()))===JSON.stringify(["Overview","Employment","Leave"])','Clerk sees only own permitted profile sections');
 await nav('/staff.php?id='+f.ids.active+'&tab=leave');await check('!!document.querySelector("[name=action][value=request_leave]")&&!document.querySelector("[name=action][value=review_leave]")','Clerk keeps own leave request without approval action');
 await check('(async()=>{for(const path of '+JSON.stringify(['/staff.php?id='+f.ids.active+'&tab=salary','/staff.php?id='+f.ids.active+'&tab=documents','/staff.php?id='+f.ids.draft]) +'){const r=await fetch(path);if(r.status!==403||r.headers.get("X-Tivoli-Test-Mode")!=="synthetic")return false;}return true;})()','Direct private and other-staff URLs remain denied');
 // Browser-only retained legacy layout: no application submission.
 await page('Page.setDocumentContent',{frameId:(await page('Page.getFrameTree')).frameTree.frame.id,html:'<!doctype html><form data-hr-salary><select name="preferred"><option value="momo">MoMo</option><option value="bank">Bank</option><option value="cash">Cash</option></select><input name="momo_id"><input name="momo_name"><input name="momo_number"><input name="bank_id"><input name="bank_holder"><input name="bank_number"><input name="confirmed" type="checkbox" checked></form>'});
 await ev(fs.readFileSync(root+'/public/assets/js/hr.js','utf8'));
 await check('document.querySelector("[name=momo_id]").required&&!document.querySelector("[name=bank_id]").required','Retained legacy salary layout validates selected MoMo fields');
 await ev('var pay=document.querySelector("form");pay.elements.preferred.value="bank";pay.elements.preferred.dispatchEvent(new Event("change"));');
 await check('document.querySelector("[name=bank_id]").required&&!document.querySelector("[name=momo_id]").required&&!document.querySelector("[name=confirmed]").checked','Retained bank layout requires bank fields and renewed confirmation');
 await ev('pay.elements.preferred.value="cash";pay.elements.preferred.dispatchEvent(new Event("change"));');
 await check('!document.querySelector("[name=bank_id]").required&&!document.querySelector("[name=momo_id]").required','Retained cash layout has no hidden destination requirements');
 assert.deepEqual(errors,[]);console.log('TOTAL '+checks+' HR browser assertions passed across '+views+' responsive views; screenshots '+evidence);
 }finally{if(context)await call('Target.disposeBrowserContext',{browserContextId:context});ws.close();}
})().catch(e=>{console.error(e);process.exit(1)});
