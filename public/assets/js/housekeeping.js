(()=>{'use strict';const root=document.querySelector('.housekeeping');if(!root)return;const cleaning=root.querySelector('[data-cleaning-form]');function count(){if(!cleaning)return;const boxes=[...cleaning.querySelectorAll('[name="done[]"]')];cleaning.querySelector('[data-count]').textContent=boxes.filter(x=>x.checked).length+' / '+boxes.length+' Checked';}root.addEventListener('click',e=>{const clean=e.target.closest('[data-select-clean]');if(clean&&cleaning){for(const box of cleaning.querySelectorAll('[name="done[]"]'))box.checked=clean.dataset.selectClean==='all';count();}const inspect=e.target.closest('[data-select-inspect]');if(inspect){const f=inspect.closest('form');for(const radio of f.querySelectorAll('[type=radio]'))radio.checked=inspect.dataset.selectInspect==='all'&&radio.value==='pass';}});function countInspection(){
    const form=root.querySelector('[data-inspection-form]');
    if(!form)return;
    const choices=form.querySelectorAll('input[type="radio"][value="pass"]');
    const passed=[...choices].filter(choice=>choice.checked).length;
    form.querySelector('[data-inspection-count]').textContent=passed+' / '+choices.length+' Passed';
}
root.addEventListener('click',event=>{if(event.target.closest('[data-select-inspect]'))countInspection();});
root.addEventListener('change',()=>{count();countInspection();});
countInspection();root.addEventListener('submit',e=>{const f=e.target;if(f.dataset.pending){e.preventDefault();return;}f.dataset.pending='1';});count();const target=root.querySelector('#housekeeping-error')||root.querySelector('#housekeeping-form')||root.querySelector('#housekeeping-status')||(location.hash?root.querySelector('#housekeeping-action'):null);if(target)requestAnimationFrame(()=>{target.focus({preventScroll:true});target.scrollIntoView({block:'start',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});});})();
