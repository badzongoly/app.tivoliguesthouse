(()=>{'use strict';const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;const focusAction=()=>{const target=document.getElementById(location.hash.slice(1))||document.querySelector('.error');if(!target)return;target.scrollIntoView({behavior:reduced?'instant':'smooth',block:'start'});const field=target.querySelector('input:not([type=hidden]),select,textarea,button');(field||target).focus({preventScroll:true});};window.addEventListener('pageshow',()=>setTimeout(focusAction,30));window.addEventListener('hashchange',()=>setTimeout(focusAction,30));document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{const b=form.querySelector('button[type=submit],button:not([type])');if(b){b.setAttribute('aria-busy','true');setTimeout(()=>b.removeAttribute('aria-busy'),4000);}}));})();

(()=>{'use strict';const form=document.querySelector('[data-cleaning-wizard]');if(!form)return;
const steps=[...form.querySelectorAll('[data-cleaning-step]'),form.querySelector('[data-cleaning-review]')];
const controls=form.querySelector('[data-cleaning-controls]'),back=form.querySelector('[data-cleaning-back]'),next=form.querySelector('[data-cleaning-next]'),progress=form.querySelector('[data-cleaning-progress]');let index=0;
const show=(focus=true)=>{steps.forEach((step,i)=>step.hidden=i!==index);back.disabled=index===0;next.hidden=index===steps.length-1;controls.hidden=false;const done=form.querySelectorAll('input[type=checkbox]:checked').length,total=form.querySelectorAll('input[type=checkbox]').length;progress.textContent=`Step ${index+1} of ${steps.length} · ${done} of ${total} items completed`;if(focus){const target=steps[index];target.tabIndex=-1;target.focus();target.scrollIntoView({block:'start',behavior:'instant'});}};
next.addEventListener('click',()=>{for(const input of steps[index].querySelectorAll('input'))if(!input.reportValidity())return;index++;show();});
back.addEventListener('click',()=>{if(index>0){index--;show();}});
form.addEventListener('invalid',event=>{const step=steps.findIndex(s=>s.contains(event.target));if(step>=0&&step!==index){index=step;show(false);}},true);
form.noValidate=true;
form.addEventListener('submit',event=>{if(index<steps.length-1){event.preventDefault();next.click();return;}for(const input of form.querySelectorAll('input[type=checkbox]'))if(!input.checked){event.preventDefault();index=steps.findIndex(step=>step.contains(input));show();input.reportValidity();return;}});
form.addEventListener('change',()=>show(false));show(false);
})();

(() => {
    'use strict';
    const form=document.querySelector('[data-checkin-payment]');
    if(form){
        const stayType=form.querySelector('[name="stay_type"]');
        const expected=form.querySelector('[name="expected_rate"]');
        const amount=form.querySelector('[name="amount"]');
        stayType.addEventListener('change',()=>{
            const rate=stayType.value==='short'?form.dataset.shortRate:form.dataset.overnightRate;
            if(amount.value===''||amount.value===expected.value)amount.value=rate;
            expected.value=rate;
            form.querySelector('[data-arrival-rate]').textContent='₵'+rate;
            form.querySelector('[data-arrival-coverage]').textContent=stayType.value==='short'
                ?'Fixed Short-Stay Session · Maximum Two Hours'
                :'First Overnight Charge · Through Next Day at 12:00:00 UTC';
        });
    }
    document.querySelectorAll('.stay-form[data-confirm]').forEach(target=>{
        target.addEventListener('submit',event=>{
            let message=target.dataset.confirm;
            if(target.hasAttribute('data-checkin-payment')){
                message=target.querySelector('[name="guest_name"]').value+' · Room Payment '
                    +target.querySelector('[name="amount"]').value+' GHS · '
                    +target.querySelector('[name="payment_type"]').value
                    +'. Confirm check-in and record the receipt?';
            }
            if(!window.confirm(message))event.preventDefault();
        });
    });
})();
