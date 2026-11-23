const fs=require('fs'),assert=require('assert/strict'),path=require('path');
// All HTTP responses are intercepted synthetic HTML; no app, cookies or database.
const root=path.resolve(__dirname,'../..'),origin='http://return-test.invalid',base=origin+'/app/';
const source=fs.readFileSync(root+'/public/assets/js/form-returns.js','utf8');
const confirmation=fs.readFileSync(root+'/public/assets/js/confirmations.js','utf8');
(async()=>{
 const version=await(await fetch('http://127.0.0.1:9236/json/version')).json();
 const ws=new WebSocket(version.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 let seq=0,session,context,count=0,posts=[],errors=[],actor='1',scope='synthetic-login-one',before='',body='';
 const pending=new Map(),routes=new Map();let redirect=null;
 function call(method,params={},sid){return new Promise((resolve,reject)=>{pending.set(++seq,{resolve,reject});ws.send(JSON.stringify({id:seq,method,params,...(sid?{sessionId:sid}:{})}));});}
 const page=(m,p={})=>call(m,p,session);
 const ev=async expression=>{const r=await page('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
 const wait=ms=>new Promise(r=>setTimeout(r,ms));
 const html=content=>'<!doctype html><html><head><meta charset="utf-8"></head><body>'+content+'<script>'+before+'</script><script>'+confirmation+'</script><script data-return-base="/app/" data-return-actor="'+actor+'" data-return-scope="'+scope+'">'+source+'</script></body></html>';
 const form='<form method="post"><input name="csrf" value="synthetic-csrf"><input name="operation_key" value="synthetic-operation"><textarea name="comment">Unsaved value</textarea><button id="save">Record Action</button><a id="cancel" href="/app/weekly-audits.php?page=room">Cancel</a></form>';
 ws.addEventListener('message',async e=>{const x=JSON.parse(e.data);const p=pending.get(x.id);if(p){pending.delete(x.id);x.error?p.reject(Error(x.error.message)):p.resolve(x.result);return;}
  if(x.sessionId!==session)return;
  if(x.method==='Runtime.exceptionThrown')errors.push(x.params.exceptionDetails.exception?.description||x.params.exceptionDetails.text);
  if(x.method==='Fetch.requestPaused')try{
   const {requestId,request}=x.params,u=new URL(request.url);
   if(request.method==='POST'){posts.push(request.postData);if(redirect){await page('Fetch.fulfillRequest',{requestId,responseCode:303,responseHeaders:[{name:'Location',value:redirect}]});return;}}
   const content=routes.get(u.pathname)||body;
   await page('Fetch.fulfillRequest',{requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'},{name:'Referrer-Policy',value:'no-referrer'}],body:Buffer.from(html(content)).toString('base64')});
  }catch(e){errors.push(String(e));}
 });
 async function arrived(url){for(let i=0;i<200;i++){await wait(20);try{if(await ev('document.readyState==="complete"&&location.href==='+JSON.stringify(url))){await wait(20);return;}}catch{}}throw Error('Navigation did not reach '+url);}
 async function nav(url,content=form){body=content;await page('Page.navigate',{url});await arrived(url);}
 async function check(expression,label){assert(await ev(expression),label);count++;console.log('PASS '+label);}
 function url(path,from){const u=new URL(path,base);if(from)u.searchParams.set('_return_to',from);return u.href;}
 async function follow(id){const target=await ev('document.getElementById('+JSON.stringify(id)+').href');await ev('document.getElementById('+JSON.stringify(id)+').click()');await arrived(target);return target;}
 try{
  context=(await call('Target.createBrowserContext')).browserContextId;
  const targetId=(await call('Target.createTarget',{url:'about:blank',browserContextId:context})).targetId;
  session=(await call('Target.attachToTarget',{targetId,flatten:true})).sessionId;
  await page('Page.enable');await page('Runtime.enable');await page('Fetch.enable',{patterns:[{urlPattern:'*'}]});
  const register=url('weekly-audits.php?status=closed&before=27#follow-ups');
  await nav(register,'<a id="open" href="/app/weekly-audits.php?form=room_audit">Record Audit</a>');
  await check('new URL(document.querySelector("#open").href).searchParams.get("_return_to")==='+JSON.stringify(register),'Entry link carries exact source filters and anchor before clicking/new tab');
  body=form;await follow('open');
  await check('document.querySelector("#cancel").href==='+JSON.stringify(register),'Cancel restores Follow-Ups instead of hardcoded Room Audits');
  await check('document.referrer===""','Does not depend on referrer disclosure');
  await check('document.querySelector("form").method==="post"&&new URLSearchParams(new FormData(document.querySelector("form"))).toString()==="csrf=synthetic-csrf&operation_key=synthetic-operation&comment=Unsaved+value"','No added fields or modified CSRF, operation key or input values');
  const current=await ev('location.href');await page('Page.reload');await arrived(current);
  await check('document.querySelector("#cancel").href==='+JSON.stringify(register),'Reload retains opening context');
  await follow('cancel');assert.equal(posts.length,0);count++;console.log('PASS Cancel makes GET only, saving no entered values');
  await ev('history.back()');await arrived(current);
  await check('document.querySelector("#cancel").href==='+JSON.stringify(register),'Browser Back preserves form return context');
  await ev('history.forward()');await arrived(register);
  assert.equal(posts.length,0);count++;console.log('PASS Browser Forward never replays a save');

  const roomSource=url('weekly-audits.php?page=room&range=all&before=8');
  await nav(url('weekly-audits.php?form=room_audit',roomSource));
  await check('document.querySelector("#cancel").href==='+JSON.stringify(roomSource),'Same form remembers Room Audits when opened there');
  await ev('document.body.insertAdjacentHTML("beforeend",\'<a id="child" href="/app/weekly-audits.php?form=assign&id=9">Assign</a>\')');await wait(30);
  await check('new URL(document.querySelector("#child").href).searchParams.get("_return_to")==='+JSON.stringify(url('weekly-audits.php?form=room_audit')),'Dynamic nested links carry clean parent without nesting markers');
  await follow('child');await check('document.querySelector("#cancel").href==='+JSON.stringify(url('weekly-audits.php?form=room_audit')),'Nested Cancel returns to immediate parent');
  await follow('cancel');await check('document.querySelector("#cancel").href==='+JSON.stringify(roomSource),'Parent Cancel still returns to original filtered register');
  await nav(url('dashboard.php'),'<p>Separate page</p>');await nav(url('weekly-audits.php?form=room_audit'));
  await check('document.querySelector("#cancel").getAttribute("href")==="/app/weekly-audits.php?page=room"','Direct form entry does not reuse an unrelated old return destination');

  // Navigation eligibility and malicious return inputs.
  const invalid=['https://external.invalid/a','//external.invalid/a','javascript:alert(1)',origin+'/outside/weekly-audits.php',base+'auth/logout.php',base+'finance-evidence.php?id=1',base+'weekly-audits.php?action=delete',base+'weekly-audits.php?confirm=1',base+'weekly-audits.php?confirmation_token=secret',base+'weekly-audits.php?operation_key=secret',base+'weekly-audits.php?password=secret',base+'weekly-audits.php?export=csv','http://user:pass@return-test.invalid/app/weekly-audits.php',base+'%2e%2e/auth/logout.php',base+'weekly-audits.php#'+('x'.repeat(4100))];
  for(let i=0;i<invalid.length;i++){
   await nav(url('weekly-audits.php?form=room_audit&test='+i,invalid[i]));
   await check('document.querySelector("#cancel").getAttribute("href")==="/app/weekly-audits.php?page=room"','Unsafe return '+i+' keeps safe fallback');
  }
  await nav(url('weekly-audits.php?form=room_audit&test=controls',register),form+'<a id="business" data-confirm="Cancel request?" href="/app/purchases.php?form=cancel&id=3">Cancel Request</a><dialog><a id="modal" href="/app/occupancy.php?form=checkout&id=3">Cancel</a></dialog><a id="inline" href="/app/finance.php" onclick="return false">Cancel</a><a id="hash" href="#part">Section</a><a id="external" href="https://external.invalid">External</a><a id="logout" href="/app/auth/logout.php">Logout</a><a id="download" download href="/app/finance.php">Download</a>');
  for(const [id,href] of [['business','/app/purchases.php?form=cancel&id=3'],['modal','/app/occupancy.php?form=checkout&id=3'],['inline','/app/finance.php'],['hash','#part'],['external','https://external.invalid'],['logout','/app/auth/logout.php'],['download','/app/finance.php']])await check('document.getElementById('+JSON.stringify(id)+').getAttribute("href")==='+JSON.stringify(href),'Preserves '+id+' control');
  await ev('var script=document.createElement("script");script.dataset.returnBase="/app/";script.dataset.returnActor="1";script.dataset.returnScope="synthetic-login-one";script.textContent='+JSON.stringify(source)+';document.body.append(script);');
  await check('document.querySelector("#cancel").href==='+JSON.stringify(register),'Repeated installation is harmless');
  await ev('document.querySelector("form").dataset.confirm="Record action?";document.querySelector("#save").click()');
  await check('document.querySelectorAll("dialog:modal").length===1&&'+posts.length+'===0','Only one shared modal; no POST before approval');
  await ev('document.querySelector("[data-confirmation-cancel]").click()');
  await check('!document.querySelector("dialog:modal")&&document.querySelector("textarea").value==="Unsaved value"','Modal Cancel dismisses and retains unsaved form');
  assert.equal(posts.length,0);count++;console.log('PASS Modal and page Cancel have not posted business data');

  // Multi-step POST context; HTTP is intercepted and never reaches the app.
  await nav(url('payroll.php?form=start',url('payroll.php?page=history&before=55')));
  redirect=url('payroll.php?form=review&id=9');await ev('document.querySelector("#save").click()');await arrived(redirect);redirect=null;
  await check('document.querySelector("#cancel").href==='+JSON.stringify(url('payroll.php?page=history&before=55')),'Approved POST continuation retains original source');
  assert.equal(posts.length,1);assert.equal(posts[0],'csrf=synthetic-csrf&operation_key=synthetic-operation&comment=Unsaved+value');count++;console.log('PASS Actual POST payload remains exact without navigation metadata');
  await nav(url('payroll.php?form=other&id=99'));
  await check('document.querySelector("#cancel").getAttribute("href")==="/app/weekly-audits.php?page=room"','Consumed POST context cannot attach to unrelated direct entry in same family');
  await nav(url('finance.php?form=review&id=9'));
  await check('document.querySelector("#cancel").getAttribute("href")==="/app/weekly-audits.php?page=room"','POST return context never crosses workflow families');

  // A cached document can retain in-memory metadata after another page consumed it.
  await nav(url('payroll.php?form=cached',url('payroll.php?page=history&before=92')));
  await ev('window.addEventListener("submit",e=>e.preventDefault(),{once:true});document.querySelector("#save").click();window.dispatchEvent(new PageTransitionEvent("pageshow",{persisted:true}));');
  await nav(url('payroll.php?form=unrelated&id=74'));
  await check('document.querySelector("#cancel").getAttribute("href")==="/app/weekly-audits.php?page=room"','Restored cached page cannot resurrect consumed pending context');

  // GET-only selection steps also keep their opening page without extra fields.
  const issueSource=url('stock-issues.php?before=77');
  await nav(url('stock-issues.php?page=new',issueSource),'<form method="get"><input name="page" value="new"><select name="extra_id"><option value="5">Synthetic item</option></select><button id="continue">Continue</button><a id="cancel" href="/app/stock-issues.php">Cancel</a></form>');
  body=form;const selected=url('stock-issues.php?page=new&extra_id=5');await ev('document.querySelector("#continue").click()');await arrived(selected);
  await check('document.querySelector("#cancel").href==='+JSON.stringify(issueSource),'GET selection step retains register filters without editing its payload');

  // Storage denied and auth boundary.
  before='Storage.prototype.getItem=function(){throw Error("blocked")};Storage.prototype.setItem=function(){throw Error("blocked")};';
  await nav(url('weekly-audits.php?form=room_audit&blocked=1',register));
  await check('document.querySelector("#cancel").href==='+JSON.stringify(register),'Explicit source works when browser storage is unavailable');before='';
  await nav(url('weekly-audits.php?form=room_audit&actor=one',register));
  await ev('window.oldStorageKey=Object.keys(sessionStorage).find(k=>k.startsWith("tivoli.formReturns:"))');const oldKey=await ev('window.oldStorageKey');
  actor='2';scope='synthetic-login-two';await nav(url('weekly-audits.php?form=room_audit&actor=one'));
  await check('document.querySelector("#cancel").getAttribute("href")==="/app/weekly-audits.php?page=room"&&!sessionStorage.getItem('+JSON.stringify(oldKey)+')','Next staff account has no previous navigation context');
  actor='0';await nav(url('weekly-audits.php?form=room_audit&anonymous=1',register));
  await check('document.querySelector("#cancel").getAttribute("href")==="/app/weekly-audits.php?page=room"','Anonymous page does not activate navigation tracking');
  assert.deepEqual(errors,[]);count++;console.log('PASS No browser runtime exceptions');
  console.log('TOTAL '+count+' synthetic DOM navigation assertions passed');
 }finally{if(context)await call('Target.disposeBrowserContext',{browserContextId:context});ws.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
