(()=>{
 'use strict';
 const root=document.querySelector('[data-access-workspace]'),installed=Symbol.for('tivoli.accessWorkspace');
 if(!root||window[installed])return;window[installed]=true;
 const panes=[...root.querySelectorAll('[data-access-panel]')],links=[...root.querySelectorAll('[data-access-section]')];
 const known=new Set(panes.map(p=>p.id));
 function show(focus=false,forced=''){
  const requested=forced||location.hash.slice(1),selected=known.has(requested)?requested:root.dataset.accessDefault;
  for(const pane of panes)pane.hidden=pane.id!==selected;
  for(const link of links){if(link.dataset.accessSection===selected)link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');}
  if(focus){const heading=document.getElementById(selected).querySelector('h2');heading.focus({preventScroll:true});heading.scrollIntoView({block:'start',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});}
 }
 for(const form of root.querySelectorAll('[data-permission-preview]')){
  const choices=[...form.querySelectorAll('[data-permission-labels]')];
  const output=form.querySelector('[data-permission-changes]');
  const collect=initial=>Object.assign({},...choices.filter(choice=>initial?choice.defaultChecked:choice.checked)
   .map(choice=>JSON.parse(choice.dataset.permissionLabels)));
  const original=collect(true);
  const update=()=>{
   const selected=collect(false);
   const gains=Object.keys(selected).filter(id=>!(id in original)).map(id=>selected[id]);
   const losses=Object.keys(original).filter(id=>!(id in selected)).map(id=>original[id]);
   const effect=[gains.length?'Will gain: '+gains.join('; '):'',losses.length?'Will lose: '+losses.join('; '):''].filter(Boolean);
   output.textContent=effect.length?effect.join('. '):'No permission changes.';
   output.hidden=false;
  };
  form.addEventListener('change',update);
  form.addEventListener('reset',()=>queueMicrotask(update));
  update();
 }
 for(const cancel of root.querySelectorAll('[data-access-cancel]'))cancel.hidden=false;
 root.addEventListener('click',e=>{
  const link=e.target.closest('[data-access-section]');if(link){show(true,link.dataset.accessSection);return;}
  const cancel=e.target.closest('[data-access-cancel]');if(!cancel)return;
  const form=cancel.closest('form'),action=form.closest('details');form.reset();
  for(const button of form.querySelectorAll('[data-password-toggle]')){const field=document.getElementById(button.dataset.passwordToggle);field.type='password';button.textContent='Show';button.setAttribute('aria-pressed','false');button.setAttribute('aria-label','Show password');}
  action.open=false;action.querySelector('summary').focus();
 });
 window.addEventListener('hashchange',()=>show(true));
 show(false,root.dataset.accessErrorAction?root.dataset.accessDefault:'');
 if(root.dataset.accessErrorAction){
  const form=[...root.querySelectorAll('form')].find(f=>f.elements.action?.value===root.dataset.accessErrorAction&&f.elements.id?.value===root.dataset.accessErrorId);
  if(form){let details=form.closest('details');while(details&&root.contains(details)){details.open=true;details=details.parentElement.closest('details');}}
  const error=root.querySelector('[role=alert]');if(error)error.tabIndex=-1;
 }
 // Native fragment navigation can reset focus after deferred scripts run.
 // Apply the initial action/error focus after page load, once per document.
 const initialFocus=()=>requestAnimationFrame(()=>{
  const error=root.querySelector('[role=alert]');
  if(root.dataset.accessErrorAction&&error){show(false,root.dataset.accessDefault);error.focus({preventScroll:true});error.scrollIntoView({block:'start'});return;}
  if(!document.getElementById('staff').hidden&&root.dataset.accessAccount!=='0'){
   const record=[...root.querySelectorAll('.access-record')].find(r=>r.dataset.accessAccount===root.dataset.accessAccount);
   if(record){const summary=record.querySelector('summary');summary.focus({preventScroll:true});summary.scrollIntoView({block:'start'});}
  }
 });
 if(document.readyState==='complete')initialFocus();else window.addEventListener('pageshow',initialFocus,{once:true});
})();
