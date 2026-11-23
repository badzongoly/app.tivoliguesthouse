(()=>{
 'use strict';const installed=Symbol.for('tivoli.settingsWorkspace');if(window[installed])return;window[installed]=true;
 for(const cancel of document.querySelectorAll('[data-settings-cancel]'))cancel.hidden=false;
 document.addEventListener('click',e=>{
  const cancel=e.target.closest('[data-settings-cancel]');if(!cancel)return;
  const form=cancel.closest('form[data-settings-editor]');if(!form)return;
  form.reset();const details=form.closest('details');
  if(details){details.open=false;details.querySelector('summary').focus();}
 });
 // Opening an editor focuses its work; no form data is stored or posted here.
 document.addEventListener('toggle',e=>{
  const details=e.target;if(!details.matches?.('details[data-settings-entry]')||!details.open)return;
  const field=details.querySelector('input:not([type=hidden]),select,textarea');if(field){field.focus({preventScroll:true});field.scrollIntoView({block:'nearest'});}
 },true);
 const focusOutcome=()=>requestAnimationFrame(()=>{
  const point=document.querySelector('#workspace-main [role=alert]')||document.querySelector('#workspace-main [role=status]');
  if(point){point.tabIndex=-1;point.focus({preventScroll:true});point.scrollIntoView({block:'start'});}
 });
 if(document.readyState==='complete')focusOutcome();else window.addEventListener('pageshow',focusOutcome,{once:true});
})();
