const fs=require('fs'),assert=require('assert/strict');
// Browser-only synthetic DOM checks. No application requests or database access.
const path=require('path'),os=require('os');
const root=path.resolve(__dirname,'../..');
const evidence=fs.mkdtempSync(path.join(os.tmpdir(),'tivoli-confirmations-'));
(async()=>{
 const version=await(await fetch('http://127.0.0.1:9236/json/version')).json();
 const ws=new WebSocket(version.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 let seq=0,session,context,count=0;const pending=new Map(),errors=[];
 ws.addEventListener('message',e=>{const r=JSON.parse(e.data);if(r.method==='Page.javascriptDialogOpening'){call('Page.handleJavaScriptDialog',{accept:false},session).catch(()=>{});}if(r.method==='Runtime.exceptionThrown')errors.push(r.params.exceptionDetails.text);const p=pending.get(r.id);if(p){pending.delete(r.id);r.error?p.reject(Error(r.error.message)):p.resolve(r.result);}});
 function call(method,params={},sid){return new Promise((resolve,reject)=>{pending.set(++seq,{resolve,reject});ws.send(JSON.stringify({id:seq,method,params,...(sid?{sessionId:sid}:{})}));});}
 const page=(m,p={})=>call(m,p,session),ev=async expression=>{const x=await page('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(x.exceptionDetails)throw Error(x.exceptionDetails.exception?.description||x.exceptionDetails.text);return x.result.value;};
 const check=async(expression,label)=>{assert(await ev(expression),label);console.log('PASS '+label);count++;};
 const key=async(key,shift=false)=>{for(const type of ['keyDown','keyUp'])await page('Input.dispatchKeyEvent',{type,key,code:key,windowsVirtualKeyCode:key==='Tab'?9:27,modifiers:shift?8:0});};
 const script=p=>fs.readFileSync(root+'/'+p,'utf8');
 try{
 context=(await call('Target.createBrowserContext')).browserContextId;const targetId=(await call('Target.createTarget',{url:'about:blank',browserContextId:context})).targetId;session=(await call('Target.attachToTarget',{targetId,flatten:true})).sessionId;await page('Page.enable');await page('Runtime.enable');
 await page('Page.setDocumentContent',{frameId:(await page('Page.getFrameTree')).frameTree.frame.id,html:'<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main class="finance"><button id="outside">Outside</button><form data-confirm="Record this exact action once?"><input name="csrf" value="synthetic-token" type="hidden"><input name="operation_key" value="synthetic-idempotency" type="hidden"><input name="note" required value="Retain this input"><input name="evidence" type="file"><button id="send" name="decision" value="release">Record Action</button><button id="rework" name="decision" value="rework" data-confirm="Return for cleaning?">Return For Cleaning</button></form></main></body></html>'});
 await ev('window.posts=[];document.querySelector("form").addEventListener("submit",e=>{if(e.defaultPrevented)return;e.preventDefault();posts.push(Object.fromEntries(new FormData(e.target,e.submitter)));});');
 if(fs.existsSync(root+'/public/assets/js/confirmations.js')){await ev(script('public/assets/js/confirmations.js'));await ev('document.head.append(Object.assign(document.createElement("style"),{textContent:'+JSON.stringify(script('public/assets/css/confirmations.css'))+'}));');}
 else {await ev(script('public/assets/js/finance.js'));await ev('document.dispatchEvent(new Event("DOMContentLoaded"));');}
 await ev(script('public/assets/js/confirmations.js'));
 await ev('document.querySelector("#send").click();');
 await check('!!document.querySelector("dialog:modal")','Shared confirmation opens as a real modal');
 await check('posts.length===0&&document.activeElement.matches("[data-confirmation-cancel]")','No posting before confirmation; safe initial focus');
 await check('document.documentElement.classList.contains("app-confirmation-open")','Background scrolling is locked');
 await ev('document.querySelector("form").requestSubmit(document.querySelector("#send"));');await check('document.querySelectorAll("dialog:modal").length===1&&posts.length===0','Repeated requests cannot stack prompts or submit in background');
 await ev('document.querySelector("#outside").focus();');await check('document.querySelector("dialog:modal").contains(document.activeElement)','Background cannot receive focus');
 for(let i=0;i<5;i++)await key('Tab');await check('document.querySelector("dialog:modal").contains(document.activeElement)','Keyboard remains in modal');
 await key('Escape');await check('!document.querySelector("dialog:modal")&&posts.length===0&&document.activeElement.id==="send"','Escape cancels and restores focus');
 await check('!document.documentElement.classList.contains("app-confirmation-open")','Cancel unlocks background');
 for(const selector of ['[data-confirmation-dismiss]','[data-confirmation-cancel]']){await ev('document.querySelector("#send").click();document.querySelector('+JSON.stringify(selector)+').click();');await check('!document.querySelector("dialog:modal")&&posts.length===0','Cancel safely '+selector);}
 await ev('document.querySelector("#send").click();');await page('Input.dispatchMouseEvent',{type:'mousePressed',x:1,y:1,button:'left',clickCount:1});await page('Input.dispatchMouseEvent',{type:'mouseReleased',x:1,y:1,button:'left',clickCount:1});await check('!document.querySelector("dialog:modal")&&posts.length===0','Backdrop cancels without submission');
 await ev('document.querySelector("#rework").click();');await check('document.querySelector("#app-confirmation-description").textContent==="Return for cleaning?"','Submitter-specific wording is retained');
 await ev('var acceptButton=document.querySelector("[data-confirmation-accept]");acceptButton.click();acceptButton.click();');await check('posts.length===1&&posts[0].decision==="rework"&&posts[0].csrf==="synthetic-token"&&posts[0].operation_key==="synthetic-idempotency"&&posts[0].note==="Retain this input"','Confirmed action preserves submitter, CSRF, operation key and fields exactly once');
 await ev('document.querySelector("[name=note]").value="";document.querySelector("#send").click();');await check('!document.querySelector("dialog:modal")&&posts.length===1','Invalid forms do not open or submit');
 await ev('document.querySelector("[name=note]").value="<img src=x onerror=alert(1)>";document.querySelector("form").dataset.confirm="<script>unsafe</script>";document.querySelector("#send").click();');await check('!document.querySelector("dialog script")&&document.querySelector("#app-confirmation-description").textContent==="<script>unsafe</script>"','Prompt text is rendered safely');await key('Escape');
 for(const width of [1440,390,320]){await page('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<500});await ev('document.querySelector("#send").click();');await check('(()=>{const d=document.querySelector("dialog:modal"),r=d.getBoundingClientRect();return r.left>=0&&r.right<='+width+'&&r.bottom<=900&&d.scrollWidth<=d.clientWidth+1})()','Modal fits '+width+'px');const shot=await page('Page.captureScreenshot',{format:'png'});fs.writeFileSync(evidence+'/modal-'+width+'.png',Buffer.from(shot.data,'base64'));await key('Escape');}
 const modules=[['finance','finance'],['guest-charge-corrections','corrections'],['expense-corrections','corrections'],['payroll','payroll'],['purchase-corrections','corrections'],['receipt-corrections','corrections'],['purchases','purchases'],['stock-issues','stock-issues'],['stock-counts','stock-counts'],['housekeeping','housekeeping']];
 await page('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
 for(const [name,klass] of modules){
  await ev('document.body.innerHTML='+JSON.stringify('<main class="'+klass+'"><form data-confirm="Confirm '+name+'?"><input type="hidden" name="operation_key" value="retained-key"><input type="file" name="evidence"><button id="send" name="decision" value="release">Record Action</button></form></main>')+';window.modulePosts=[];');
  await ev('(function(){'+script('public/assets/js/'+name+'.js')+'})()');
  await ev('document.addEventListener("submit",function record(e){if(e.target!==document.querySelector("form"))return;if(!e.defaultPrevented){modulePosts.push(Object.fromEntries(new FormData(e.target,e.submitter)));e.preventDefault();}},{once:true});var transfer=new DataTransfer();transfer.items.add(new File(["synthetic evidence"],"evidence.txt"));document.querySelector("[type=file]").files=transfer.files;document.querySelector("#send").click();');
  await check('!!document.querySelector("dialog:modal")&&modulePosts.length===0&&!document.querySelector("form").dataset.pending',name+' opens before pending/submission');
  await key('Escape');await check('!document.querySelector("form").dataset.pending&&document.querySelector("[type=file]").files[0].name==="evidence.txt"',name+' cancellation retains evidence and allows retry');
  await ev('document.querySelector("#send").click();document.querySelector("[data-confirmation-accept]").click();');
  await check('modulePosts.length===1&&modulePosts[0].decision==="release"&&modulePosts[0].operation_key==="retained-key"&&modulePosts[0].evidence.name==="evidence.txt"',name+' confirms one intact action');
 }
 await ev('document.body.innerHTML='+JSON.stringify('<main class="handover"><form data-handover-wizard><input type="hidden" name="operation_key" value="handover-key"><div data-step="0"><button type="button" data-next>Next</button></div><div data-step="1"><select name="recipient_id"><option value="synthetic">Synthetic Recipient</option></select><input name="duty_start" value="2026-11-22T09:00"><input name="duty_end" value="2026-11-22T17:00"><textarea name="notes">Retain notes</textarea><button type="button" data-next>Next</button></div><div data-step="2"><div data-submission-summary></div><input type="checkbox" name="confirmed" value="1" checked><button id="send">Submit Handover</button></div></form></main>')+';window.wizardPosts=[];');
 await ev(script('public/assets/js/handover.js'));
 await ev('document.addEventListener("submit",e=>{if(e.target.hasAttribute("data-handover-wizard")&&!e.defaultPrevented){wizardPosts.push(Object.fromEntries(new FormData(e.target,e.submitter)));e.preventDefault();}});document.querySelector("[data-step=\\"0\\"] [data-next]").click();document.querySelector("[data-step=\\"1\\"] [data-next]").click();document.querySelector("#send").click();');
 await check('!!document.querySelector("dialog:modal")&&document.querySelector("[name=recipient_id]").disabled','Handover confirms only on final step before enabling fields');await key('Escape');
 await check('wizardPosts.length===0&&document.querySelector("[name=recipient_id]").disabled','Handover cancellation leaves wizard unchanged');
 await ev('document.querySelector("#send").click();document.querySelector("[data-confirmation-accept]").click();');
 await check('wizardPosts.length===1&&wizardPosts[0].recipient_id==="synthetic"&&wizardPosts[0].notes==="Retain notes"&&wizardPosts[0].operation_key==="handover-key"','Handover confirmation enables and submits original fields once');
 await ev('document.body.innerHTML='+JSON.stringify('<form data-cd-form><p data-cd-preview>Verified GHS 10.00; unresolved GHS 5.00.</p><button id="send">Record Action</button></form>')+';');await ev(script('public/assets/js/cash-discrepancies.js'));await ev('document.querySelector("#send").click();');
 await check('document.querySelector("#app-confirmation-description").textContent.includes("Verified GHS 10.00; unresolved GHS 5.00.")','Cash discrepancy retains its current effect preview');await key('Escape');

 // Exercise every discovered confirmation attribute through the single shared
 // gate. Template expressions are inert prompt text, never executed as PHP/JS.
 const walk=dir=>fs.readdirSync(dir,{withFileTypes:true}).flatMap(e=>e.isDirectory()?walk(path.join(dir,e.name)):[path.join(dir,e.name)]);
 const prompts=[];
 for(const file of [...walk(root+'/templates'),...walk(root+'/public').filter(p=>p.endsWith('.php'))]){
  if(!file.endsWith('.php'))continue;
  for(const match of fs.readFileSync(file,'utf8').matchAll(/data-confirm="([^"]+)"/g))prompts.push(match[1]);
 }
 assert(prompts.length>30,'Confirmation inventory unexpectedly empty');
 for(let i=0;i<prompts.length;i++){
  await ev('document.body.innerHTML='+JSON.stringify('<button id="outside" style="position:fixed;left:2px;top:2px">Background Action</button><form><input name="note" value="Untouched"><button id="send" name="decision" value="original">Record Action</button></form>')+';window.inventoryPosts=0;window.outsideClicks=0;document.querySelector("form").dataset.confirm='+JSON.stringify(prompts[i])+';document.querySelector("#outside").onclick=()=>outsideClicks++;document.querySelector("form").addEventListener("submit",e=>{if(!e.defaultPrevented){e.preventDefault();inventoryPosts++;}});document.querySelector("#send").click();document.querySelector("[name=note]").focus();');
  await check('document.querySelectorAll("dialog:modal").length===1&&document.querySelector("dialog:modal").contains(document.activeElement)&&getComputedStyle(document.documentElement).overflowY==="hidden"&&inventoryPosts===0','Inventory '+(i+1)+' shows one modal with inert, scroll-locked background');
  await page('Input.dispatchMouseEvent',{type:'mousePressed',x:10,y:10,button:'left',clickCount:1});await page('Input.dispatchMouseEvent',{type:'mouseReleased',x:10,y:10,button:'left',clickCount:1});
  await check('outsideClicks===0&&inventoryPosts===0&&!document.querySelector("dialog:modal")&&!document.documentElement.classList.contains("app-confirmation-open")','Inventory '+(i+1)+' backdrop cancels without triggering underlying action');
  await ev('document.querySelector("#send").click();document.querySelector("[data-confirmation-accept]").click();');
  await check('inventoryPosts===1&&!document.querySelector("dialog:modal")','Inventory '+(i+1)+' confirmation submits once without another prompt');
 }
 console.log('INVENTORY '+prompts.length+' confirmation attributes verified');
 assert.deepEqual(errors,[]);console.log('TOTAL '+count+' shared-modal assertions passed');
 }finally{if(context)await call('Target.disposeBrowserContext',{browserContextId:context});ws.close();}
})().catch(e=>{console.error(e);process.exit(1)});
