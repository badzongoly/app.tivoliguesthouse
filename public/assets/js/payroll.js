'use strict';
document.querySelectorAll('.payroll form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!window.confirm(form.dataset.confirm))event.preventDefault();}));
const form=document.querySelector('[data-payroll-base]');
if(form){
 const parse=v=>/^\d{1,10}(\.\d{1,2})?$/.test(v)?BigInt(v.split('.')[0])*100n+BigInt((v.split('.')[1]||'').padEnd(2,'0')):null;
 const money=n=>'GHS '+(n/100n).toString()+'.'+(n%100n).toString().padStart(2,'0');
 const update=()=>{
  const v=['allowance','overtime','deductions','employer'].map(n=>parse(form.elements[n].value));
  const output=form.querySelector('[data-payroll-preview]');
  if(v.some(n=>n===null)){output.textContent='Enter valid amounts with up to two decimal places.';return;}
  const gross=BigInt(form.dataset.payrollBase)+v[0]+v[1],net=gross-v[2];
  output.textContent=net<0?'Deductions exceed gross pay.':'Gross '+money(gross)+' · Net '+money(net)+' · Staff Cost '+money(gross+v[3]);
  form.elements.adjustment.required=v.some(n=>n>0n);
 };form.addEventListener('input',update);update();
}
const target=document.getElementById('payroll-error')||document.getElementById('payroll-status')||(location.hash==='#payroll-action'?document.getElementById('payroll-action'):null);
if(target){target.focus();target.scrollIntoView({block:'start'});}

const correction=document.getElementById('payroll-correction');
if(correction){const parse=v=>{if(!/^\d{1,10}(\.\d{1,2})?$/.test(v))throw Error('Enter valid non-negative amounts with up to two decimal places.');const [a,b='']=v.split('.');return BigInt(a)*100n+BigInt(b.padEnd(2,'0'));};const money=n=>(n<0n?'-':'')+'GHS '+((n<0n?-n:n)/100n)+'.'+((n<0n?-n:n)%100n).toString().padStart(2,'0');const update=()=>{const type=correction.elements.type.value;correction.querySelectorAll('[data-correction-only]').forEach(box=>{box.hidden=box.dataset.correctionOnly!==type;box.querySelectorAll('input,select').forEach(input=>{input.disabled=box.hidden;input.required=!box.hidden;});});try{let net=BigInt(correction.dataset.beforeNet),cost=BigInt(correction.dataset.beforeCost),paid=BigInt(correction.dataset.paid);if(type==='components'){const values=Object.fromEntries(['base','allowance','overtime','deductions','employer'].map(n=>[n,parse(correction.elements[n].value)]));const gross=values.base+values.allowance+values.overtime;net=gross-values.deductions;cost=gross+values.employer;if(net<0n)throw Error('Deductions cannot exceed gross pay.');}else{const selected=correction.elements.payment_id.selectedOptions[0];if(!selected)throw Error('Choose an original recorded transfer.');paid+=parse(correction.elements.amount.value)-BigInt(selected.dataset.amount);}const delta=net-paid;const preview='Staff Cost '+money(cost)+' · Current-Period Cost Adjustment '+money(cost-BigInt(correction.dataset.beforeCost))+' · Net Pay '+money(net)+' · Recorded Paid '+money(paid)+' · Employee Due '+money(delta>0n?delta:0n)+' · Private Recovery Due '+money(delta<0n?-delta:0n)+' · Actual Money Moved GHS 0.00.';correction.querySelector('[data-correction-preview]').textContent=preview;correction.dataset.confirm='Confirm this supported payroll correction? '+preview;}catch(error){correction.querySelector('[data-correction-preview]').textContent=error.message;}};correction.addEventListener('input',update);correction.addEventListener('change',event=>{if(event.target.name==='payment_id')correction.elements.amount.value=(BigInt(event.target.selectedOptions[0].dataset.amount)/100n)+'.'+(BigInt(event.target.selectedOptions[0].dataset.amount)%100n).toString().padStart(2,'0');update();});update();}
