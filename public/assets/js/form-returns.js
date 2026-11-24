/* Navigation only: never submit a form or alter its payload. */
(()=>{
 'use strict';
 const tag=document.currentScript,actor=tag?.dataset.returnActor,scope=tag?.dataset.returnScope;
 const singleton=Symbol.for('tivoli.formReturns');
 if(!actor||actor==='0'||!scope||window[singleton])return;
 let base;try{base=new URL(tag.dataset.returnBase,location.href);}catch{return;}
 if(base.origin!==location.origin)return;
 window[singleton]=true;
 const marker='_return_to',ttl=8*60*60*1000;
 const pages=new Set(('index.php dashboard.php owner_reports.php bookings.php checkin.php checkout.php rooms.php extras.php extra_sales.php stock.php payments.php reports.php expenses.php finance.php purchases.php financial-corrections.php payroll.php stock-counts.php stock-issues.php weekly-audits.php occupancy.php housekeeping.php handover.php staff.php hr-settings.php cleaning-settings.php users.php audit.php admin_recovery.php reminders.php owner-exceptions.php auth/change-password.php').split(' '));
 const prefix='tivoli.formReturns:'+base.pathname+':',key=prefix+actor+':'+scope;
 function safe(raw){
  if(typeof raw!=='string'||!raw||raw.length>8192)return null;
  try{
   const u=new URL(raw,location.href);
   if(u.origin!==base.origin||u.username||u.password||!u.pathname.startsWith(base.pathname)||!pages.has(u.pathname.slice(base.pathname.length)))return null;
   u.searchParams.delete(marker);
   for(const k of u.searchParams.keys())if(/(?:token|password|secret|csrf|operation_key)/i.test(k)||/^(?:action|delete|logout|download|export|confirm)$/i.test(k))return null;
   return u.href.length<=4096?u.href:null;
  }catch{return null;}
 }
 function read(){try{return JSON.parse(sessionStorage.getItem(key))||{};}catch{return {};}}
 function write(value){try{sessionStorage.setItem(key,JSON.stringify(value));}catch{/* Explicit URL context still works without storage. */}}
 let store=read();if(!store||typeof store!=='object'||Array.isArray(store))store={};
 let trail=store.trail&&typeof store.trail==='object'&&!Array.isArray(store.trail)?store.trail:{};
 for(const [url,item] of Object.entries(trail))if(!item||Date.now()-item.at>ttl||!safe(url)||!safe(item.to))delete trail[url];
 // Navigation metadata belongs to this login, never to the next staff member.
 try{for(const old of Object.keys(sessionStorage))if(old.startsWith(prefix)&&old!==key)sessionStorage.removeItem(old);}catch{}
 function persist(){const entries=Object.entries(trail).sort((a,b)=>b[1].at-a[1].at).slice(0,80);trail=Object.fromEntries(entries);write({trail,pending:store.pending,restore:store.restore});}
 function family(raw){const u=new URL(raw);return u.pathname+(u.pathname.endsWith('/occupancy.php')?':'+(u.searchParams.get('section')||'board'):'');}
 let current=safe(location.href),returnTo=null;
 const isCancel=a=>a instanceof HTMLAnchorElement&&(a.hasAttribute('data-cancel-return')||a.textContent.trim()==='Cancel')&&!a.closest('dialog')&&!a.matches('[data-confirm],[data-confirmation-cancel],[data-confirmation-dismiss],[onclick],[download]');
 function applyReturn(){
  current=safe(location.href);
  const cancels=[...document.links].filter(isCancel);
  if(!current||!cancels.length)return;
  const explicit=safe(new URL(location.href).searchParams.get(marker));
  const h=history.state?.tivoliFormReturn;
  const remembered=h?.scope===scope&&h?.actor===actor&&h?.url===current?safe(h.to):null;
  const pending=store.pending;
  const continued=pending&&Date.now()-pending.at<300000&&pending.family===family(current)?safe(pending.to):null;
  const restoring=store.restore?.url===current&&Date.now()-store.restore.at<60000;
  const parent=restoring||continued?safe(trail[current]?.to):null;
  returnTo=[explicit,remembered,continued,parent].find(u=>u&&u!==current)||null;
  if(!returnTo)return;
  trail[current]={to:returnTo,at:Date.now()};persist();
  try{if(history.state===null||typeof history.state==='object')history.replaceState({...history.state,tivoliFormReturn:{actor,scope,url:current,to:returnTo}},'');}catch{}
  for(const a of cancels)a.href=returnTo;
 }
 function prepare(a){
  if(!(a instanceof HTMLAnchorElement)||isCancel(a)||a.closest('dialog')||a.matches('[data-confirm],[onclick],[download],[data-confirmation-cancel],[data-confirmation-dismiss]'))return;
  const raw=a.getAttribute('href');
  if(!raw||raw.startsWith('#'))return;
  const to=safe(raw),from=safe(location.href);
  if(!to||!from||to===from)return;
  const u=new URL(to),source=new URL(from);
  if(u.pathname===source.pathname&&u.search===source.search)return;
  u.searchParams.set(marker,from);a.href=u.href;
 }
 function refresh(){applyReturn();for(const a of document.links)prepare(a);}
 function start(){
  refresh();
  delete store.restore;persist();
  // Consume a continuation once. Reload uses history state; a later direct-entry
  // form must never inherit an unrelated submission's opening page.
  if(!document.querySelector('[data-stay-confirmation]')){delete store.pending;persist();}
  new MutationObserver(records=>{for(const record of records)for(const node of record.addedNodes){if(node.nodeType!==1)continue;if(node.matches('a'))prepare(node);for(const a of node.querySelectorAll('a'))prepare(a);}applyReturn();}).observe(document.body,{childList:true,subtree:true});
 }
 for(const name of ['click','auxclick','contextmenu'])document.addEventListener(name,e=>{
  const a=e.target.closest?.('a');
  if(isCancel(a)){const to=safe(a.href);if(to){store.restore={url:to,at:Date.now()};persist();}}
  else prepare(a);
 },true);
 window.addEventListener('pageshow',e=>{
  // A cached document has an old in-memory copy from before its submission.
  if(e.persisted){delete store.pending;delete store.restore;persist();}
  refresh();
 });
 window.addEventListener('hashchange',refresh);
 // Submitted forms may advance to the next step. Store only the source URL;
 // confirmation controls, CSRF, files, values and operation keys stay untouched.
 document.addEventListener('submit',e=>{
  if(e.defaultPrevented||!(e.target instanceof HTMLFormElement))return;
  // Form fields named "method" or "action" can shadow the DOM properties.
  const method=e.submitter?.getAttribute('formmethod')||e.target.getAttribute('method')||'get';
  if(!['get','post'].includes(method.toLowerCase()))return;
  const from=safe(location.href);if(!from)return;
  const destination=safe(e.submitter?.getAttribute('formaction')||e.target.getAttribute('action')||from);
  if(!destination||family(destination)!==family(from))return;
  store.pending={family:family(from),to:returnTo||safe(new URL(location.href).searchParams.get(marker))||from,at:Date.now()};persist();
 });
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();
