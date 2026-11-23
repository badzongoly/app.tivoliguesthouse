const fs=require('fs'),assert=require('assert/strict'),path=require('path'),os=require('os');
const root=path.resolve(__dirname,'../..'),evidence=fs.mkdtempSync(path.join(os.tmpdir(),'tivoli-access-navigation-'));
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


 const f=JSON.parse(fs.readFileSync(root+'/.local-setup/access-ux-fixture.json','utf8'));
 await login('admin@example.invalid','Synthetic Admin Passphrase 123!');await page('Network.enable');let posts=0;
 ws.addEventListener('message',e=>{const x=JSON.parse(e.data);if(x.sessionId===session&&x.method==='Network.requestWillBeSent'&&x.params.request.method==='POST')posts++;});
 const visible=id=>'document.querySelector("#'+id+'").hidden===false&&[...document.querySelectorAll("[data-access-panel]")].filter(p=>!p.hidden).length===1';
 for(const part of ['staff','roles','privileges']){await nav('/users.php#'+part);await check(visible(part),'Old '+part+' hash opens exactly its section');
 for(const width of [1440,390,320]){await page('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
 await check('document.documentElement.scrollWidth<=innerWidth',part+' fits '+width);await check('!!document.querySelector("[data-access-section='+part+'][aria-current=page]")&&getComputedStyle(document.querySelector("[data-access-section='+part+']")).backgroundColor==="rgb(249, 251, 245)"',part+' selected tab is visible '+width);views++;}
 }
 await nav('/users.php#staff');await ev('document.querySelector("[data-access-section=roles]").click()');await wait(100);await check(visible('roles')+'&&document.activeElement.tagName==="H2"','Tab click focuses section');await ev('history.back()');await wait(100);await check(visible('staff'),'Browser Back restores selected section');
 const id=f.people.limited.id;await nav('/users.php?account='+id+'#staff');await check('document.activeElement.closest(".access-record")?.dataset.accessAccount==="'+id+'"','Manage Account focuses the selected record');
 await ev('var field=[...document.querySelectorAll("#staff form")].find(f=>f.elements.action.value==="assign_roles"&&f.elements.id.value==="'+id+'");for(let d=field.closest("details");d;d=d.parentElement.closest("details"))d.open=true;');
 for(const width of [1440,390,320]){await page('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});await check('document.documentElement.scrollWidth<=innerWidth','Selected account editor fits '+width);views++;}
 // Read selections through input properties, so no selector escaping changes payload.
 await ev('window.beforeIds=[...field.querySelectorAll("input[type=checkbox]")].filter(e=>e.checked).map(e=>e.value);field.querySelector("input[type=checkbox]").checked=!field.querySelector("input[type=checkbox]").checked;field.elements.reason.value="Unsaved synthetic reason";field.querySelector("[data-access-cancel]").click()');
 await check('JSON.stringify([...field.querySelectorAll("input[type=checkbox]")].filter(e=>e.checked).map(e=>e.value))===JSON.stringify(beforeIds)&&!field.closest("details").open&&field.elements.reason.value===""','Cancel resets unsaved roles and closes only that action');
 await ev('var profile=[...document.querySelectorAll("#staff form")].find(f=>f.elements.action.value==="save_user"&&f.elements.id.value==="'+id+'");for(let d=profile.closest("details");d;d=d.parentElement.closest("details"))d.open=true;profile.elements.name.value="Unsaved Name";profile.elements.current_password.value="Unsaved Secret";profile.querySelector("[data-password-toggle]").click();profile.querySelector("[data-access-cancel]").click()');
 await check('profile.elements.name.value!=="Unsaved Name"&&profile.elements.current_password.value===""&&profile.elements.current_password.type==="password"','Cancel discards account edits, clears password and masks its field');
 await nav('/users.php?account='+f.admin+'#staff');await check('![...document.querySelectorAll("#staff form")].some(f=>f.elements.id.value==="'+f.admin+'"&&f.elements.action.value==="set_active")','Administrator cannot select self-deactivation');
 await nav('/users.php#roles');await ev('var roleForm=[...document.querySelectorAll("#roles form")].find(f=>f.elements.action.value==="assign_privileges"&&f.elements.id.value==="'+f.roles.delegate+'");roleForm.closest("details").open=true;');
 await check('[...roleForm.querySelectorAll("input[type=checkbox]")].every(x=>!x.disabled)&&new FormData(roleForm).getAll("ids[]").length===2','Existing privilege selections remain enabled and fully submitted');
 const shot=await page('Page.captureScreenshot',{format:'png'});fs.writeFileSync(evidence+'/roles-320.png',Buffer.from(shot.data,'base64'));assert.equal(posts,0);checks++;console.log('PASS tabs and all Cancels submit no POST');
 // A denied action selects its own section even if the URL retains another hash.
 await nav('/users.php#staff');await ev('var bad=[...document.querySelectorAll("#privileges form")].find(f=>f.elements.action.value==="create_privilege");bad.elements.code.value="users.forbidden";bad.elements.description.value="Synthetic denied namespace";bad.elements.reason.value="Synthetic browser denial";bad.elements.current_password.value="Synthetic Admin Passphrase 123!";bad.requestSubmit();');
 for(let i=0;i<600;i++){await wait(50);try{if(await ev('document.readyState==="complete"&&document.querySelector("[data-access-workspace]")?.dataset.accessErrorAction==="create_privilege"')){await wait(100);break;}}catch{}}
 await check(visible('privileges')+'&&document.activeElement.getAttribute("role")==="alert"','Denied action focuses error and correct section');
 await ev('document.querySelector("[data-access-section=staff]").click()');await check(visible('staff'),'Same-hash Users link still works after error');
 for(const who of ['delegate','role_editor','privilege_editor']){await login(f.people[who].email,f.password);await nav('/users.php');const preferred=who==='delegate'?'staff':who==='role_editor'?'roles':'privileges';await check(visible(preferred),who+' opens useful permitted section');
 await check('![...document.querySelectorAll("#staff form")].some(f=>f.elements.id.value==="'+f.admin+'")',who+' cannot edit higher administrator');
 if(who==='delegate'){await nav('/users.php?account='+id+'#staff');await check('![...document.querySelectorAll("#staff form")].some(f=>f.elements.id.value==="0")','Delegate cannot create a clerk above own authority');await check('[...document.querySelectorAll("#staff form")].some(f=>f.elements.id.value==="'+id+'"&&f.elements.action.value==="assign_roles")','Delegate retains permissible role assignment');}
 }
 assert.deepEqual(errors,[]);console.log('TOTAL '+checks+' System Access browser assertions across '+views+' responsive views; screenshots '+evidence);
 }finally{if(context)await call('Target.disposeBrowserContext',{browserContextId:context});ws.close();}
})().catch(e=>{console.error(e);process.exit(1)});
