'use strict';

const ccForm=document.getElementById('cc-request');
if(ccForm){
 const parse=v=>{if(!/^\d{1,10}(\.\d{1,2})?$/.test(v))throw Error('Enter a valid amount with up to two decimal places.');const p=v.split('.');return BigInt(p[0])*100n+BigInt((p[1]||'').padEnd(2,'0'));};
 const money=n=>(n<0n?'-':'')+'GHS '+((n<0n?-n:n)/100n).toString()+'.'+((n<0n?-n:n)%100n).toString().padStart(2,'0');
 const update=()=>{
  const type=ccForm.elements.type.value;
  ccForm.querySelectorAll('[data-cc-fields]').forEach(group=>{const active=group.dataset.ccFields===type;group.hidden=!active;group.querySelectorAll('input,select').forEach(el=>el.disabled=!active);});
  ccForm.elements.amount.required=type==='amount';ccForm.elements.keeper_id.required=type==='duplicate';ccForm.elements.reference.required=type==='method'&&ccForm.elements.method.value==='momo';
  const output=ccForm.querySelector('[data-cc-preview]'),submit=ccForm.querySelector('[data-cc-submit]');submit.disabled=type==='refund';
  if(type==='refund'){output.textContent='No recording correction will be submitted. Use the separate guest refund workflow.';return;}
  try{const before=BigInt(ccForm.dataset.amount),after=type==='amount'?parse(ccForm.elements.amount.value):type==='duplicate'?0n:before;if(after<BigInt(ccForm.dataset.refunded))throw Error('The corrected total is below already recorded refunds.');const delta=after-before,balance=BigInt(ccForm.dataset.balance)-delta;const linked=ccForm.querySelector('[data-cc-linked]');if(linked){const beforeCash=ccForm.dataset.method==='cash'?before:0n,afterMethod=type==='method'?ccForm.elements.method.value:ccForm.dataset.method,afterCash=afterMethod==='cash'?after:0n,afterMomo=afterMethod==='momo'?after:0n;for(const [selector,active] of [['[data-cc-cash-support]',beforeCash>afterCash&&type!=='duplicate'],['[data-cc-wallet-support]',BigInt(ccForm.dataset.walletVerified)>afterMomo&&type!=='duplicate']]){const group=linked.querySelector(selector);group.hidden=!active;for(const field of group.querySelectorAll('input,select'))field.disabled=!active;}}output.textContent='Recorded Receipt Adjustment '+money(delta)+' · Guest Balance '+money(balance)+' · Actual Money Moved GHS 0.00. Charges and earned revenue remain unchanged.';}catch(error){output.textContent=error.message;}
 };ccForm.addEventListener('input',update);ccForm.addEventListener('change',update);update();
}
document.querySelector('[data-cc-print]')?.addEventListener('click',()=>window.print());
const ccTarget=document.getElementById('cc-error')||document.getElementById('cc-status')||(location.hash==='#correction-action'?document.getElementById('correction-action'):null);
if(ccTarget){ccTarget.focus();ccTarget.scrollIntoView({block:'start'});}
