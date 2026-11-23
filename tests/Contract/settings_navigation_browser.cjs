const fs=require('fs'),assert=require('assert/strict'),path=require('path'),os=require('os');
const root=path.resolve(__dirname,'../..'),evidence=fs.mkdtempSync(path.join(os.tmpdir(),'tivoli-settings-navigation-'));
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


 const f=JSON.parse(fs.readFileSync(root+'/.local-setup/settings-ux-fixture.json','utf8'));
 await login('admin@example.invalid','Synthetic Admin Passphrase 123!');await page('Network.enable');let posts=0;
 ws.addEventListener('message',e=>{const x=JSON.parse(e.data);if(x.sessionId===session&&x.method==='Network.requestWillBeSent'&&x.params.request.method==='POST')posts++;});
 const cases=[['payments','/hr-settings.php','Payment Lists'],['documents','/hr-settings.php?section=documents','Document Types'],['cleaning','/cleaning-settings.php','Cleaning Checklist'],['inspection','/cleaning-settings.php?type=inspection','Inspection Checklist'],['areas','/cleaning-settings.php?type=areas','Audit Areas'],['extras','/cleaning-settings.php?type=extras','Extra Categories'],...['mine','delivery','rules','contacts','services'].map(tab=>[tab,'/reminders.php?page=settings&tab='+tab,'Notifications'])];
 const expected=['Payment Lists','Document Types','Cleaning Checklist','Inspection Checklist','Audit Areas','Extra Categories','Notifications'];
 for(const [name,path,active] of cases){await nav(path);
 await check('document.querySelectorAll(".settings-navigation").length===1&&JSON.stringify([...document.querySelectorAll(".settings-navigation a")].map(a=>a.textContent.trim()))==='+JSON.stringify(JSON.stringify(expected)),name+' one canonical Settings navigation');
 await check('JSON.stringify([...document.querySelectorAll(".settings-navigation [aria-current=page]")].map(a=>a.textContent.trim()))==='+JSON.stringify(JSON.stringify([active])),name+' actual Settings section selected');
 await check('JSON.stringify([...document.querySelectorAll(".app-navigation [aria-current=page] span")].map(a=>a.textContent.trim()))===JSON.stringify(["Settings"])',name+' one Settings sidebar selection');
 await check('!document.querySelector("main [name=action][value=generate]")',name+' no duplicated Check Now');
 for(const width of [1440,390,320]){await page('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
 await check('document.documentElement.scrollWidth<=innerWidth&&!/Fatal error|Warning:|Parse error|SQLSTATE/.test(document.body.innerText)',name+' healthy page fits '+width);
 await check('getComputedStyle(document.querySelector(".settings-navigation [aria-current=page]")).backgroundColor==="rgb(25, 62, 52)"',name+' visibly selected '+width);
 await check('[...document.querySelectorAll("[name=operation_key]")].every(x=>/^[a-f0-9]{32}$/.test(x.value))',name+' operation keys intact '+width);views++;}
 if(['payments','documents','cleaning','inspection','areas','extras'].includes(name)){
 await ev('window.editor=document.querySelector("form[data-settings-editor]");window.editorBefore=JSON.stringify([...new FormData(editor)]);editor.closest("details").open=true;');await wait(100);
 await check('editor.contains(document.activeElement)','Opening '+name+' editor focuses its input');
 await ev('var field=editor.querySelector("input:not([type=hidden]),select,textarea");if(field.tagName==="SELECT")field.selectedIndex=field.options.length-1;else field.value="Unsaved synthetic setting";editor.querySelector("[data-settings-cancel]").click();');
 await check('JSON.stringify([...new FormData(editor)])===editorBefore&&!editor.closest("details").open&&document.activeElement.tagName==="SUMMARY"',name+' Cancel restores all original fields and closes the editor');
 }
 if(name==='mine')await check('[...document.querySelectorAll("nav")].find(n=>n.getAttribute("aria-label")==="Reminder Settings").querySelectorAll("a").length===5&&[...document.querySelectorAll("nav")].find(n=>n.getAttribute("aria-label")==="Reminder Settings").querySelectorAll("[aria-current=page]").length===1','Notification owner controls remain distinct sub-settings');
 if(['areas','mine'].includes(name)){const shot=await page('Page.captureScreenshot',{format:'png'});fs.writeFileSync(evidence+'/'+name+'-320.png',Buffer.from(shot.data,'base64'));}
 }
 assert.equal(posts,0);checks++;console.log('PASS Settings navigation and Cancel produce no business POST');
 await login(f.people.reader.email,f.password);for(const path of ['/hr-settings.php','/hr-settings.php?section=documents']){await nav(path);
 await check('JSON.stringify([...document.querySelectorAll(".settings-navigation a")].map(a=>a.textContent.trim()))===JSON.stringify(["Payment Lists","Document Types","Notifications"])','Read-only custom settings role gets only compatible tabs');
 await check('!document.querySelector("main [name=action][value=catalogue]")&&document.body.innerText.includes("Only An Administrator")','Permitted list reader retains settings without invalid write controls');}
 await login(f.people.clerk.email,f.password);await nav('/reminders.php?page=settings');
 await check('JSON.stringify([...document.querySelectorAll(".settings-navigation a")].map(a=>a.textContent.trim()))===JSON.stringify(["Notifications"])','Clerk sees only own Notifications settings');
 await check('JSON.stringify([...document.querySelectorAll(".app-navigation [aria-current=page] span")].map(a=>a.textContent.trim()))===JSON.stringify(["Reminders"])','Clerk keeps Reminders sidebar context');
 await check('[...document.querySelectorAll("nav")].find(n=>n.getAttribute("aria-label")==="Reminder Settings").querySelectorAll("a").length===2&&!document.body.innerText.includes("Contact Verification")','Clerk has only personal preferences and own delivery history');
 await check('(async()=>{for(const p of ["/hr-settings.php","/cleaning-settings.php","/cleaning-settings.php?type=extras","/reminders.php?page=settings&tab=rules"]){const r=await fetch(p);if(r.status!==403||r.headers.get("X-Tivoli-Test-Mode")!=="synthetic")return false;}return true;})()','Direct Settings owner/admin routes remain denied to clerk');
 assert.deepEqual(errors,[]);console.log('TOTAL '+checks+' Settings browser assertions across '+views+' responsive views; screenshots '+evidence);
 }finally{if(context)await call('Target.disposeBrowserContext',{browserContextId:context});ws.close();}
})().catch(e=>{console.error(e);process.exit(1)});
