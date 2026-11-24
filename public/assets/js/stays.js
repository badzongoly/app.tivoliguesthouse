(()=>{'use strict';const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;const focusAction=()=>{if(document.querySelector('dialog[open]'))return;const target=document.getElementById(location.hash.slice(1))||document.querySelector('.error');if(!target)return;target.scrollIntoView({behavior:reduced?'instant':'smooth',block:'start'});const field=target.querySelector('input:not([type=hidden]),select,textarea,button');(target.id==='stay-bill'?target:(field||target)).focus({preventScroll:true});};window.addEventListener('pageshow',()=>setTimeout(focusAction,30));window.addEventListener('hashchange',()=>setTimeout(focusAction,30));document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{const b=form.querySelector('button[type=submit],button:not([type])');if(b){b.setAttribute('aria-busy','true');setTimeout(()=>b.removeAttribute('aria-busy'),4000);}}));})();

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
            for (const label of form.querySelectorAll('[data-arrival-rate], [data-arrival-summary]')) label.textContent='₵'+rate;
            form.querySelector('[data-arrival-coverage]').textContent=stayType.value==='short'
                ?'Fixed Short-Stay Session · Maximum Two Hours'
                :'First Overnight Charge · Through Next Day at 12:00:00 UTC';
        });
    }
})();

(() => {
    'use strict';
    const panel = document.querySelector('[data-payment-recovery]');
    const scope = document.querySelector('[data-return-scope]')?.dataset.returnScope;
    if (!panel || !scope) return;
    const key = 'tivoli-payment:' + scope;
    let pending;
    try {
        pending = sessionStorage.getItem(key);
    } catch {
        return;
    }
    const message = panel.querySelector('[data-payment-recovery-message]');
    const check = panel.querySelector('[data-payment-check]');
    const account = panel.querySelector('[data-payment-account]');
    const dismiss = panel.querySelector('[data-payment-dismiss]');
    const notice = document.querySelector('#stay-result[role=status]');
    if (pending && notice?.dataset.receiptOperation === pending) {
        sessionStorage.removeItem(key);
        pending = null;
    }

    document.addEventListener('submit', event => {
        const form = event.target;
        if (event.defaultPrevented || !form.matches('[data-stay-payment], [data-checkin-payment], [data-payment-final]')) return;
        const operation = form.elements.operation_key?.value;
        if (!/^[a-f0-9]{32}$/.test(operation || '')) return;
        try { sessionStorage.setItem(key, operation); } catch { /* The original submit can still proceed. */ }
    });
    if (!pending) return;

    panel.hidden = false;
    check.addEventListener('click', async () => {
        check.disabled = true;
        try {
            const url = new URL(panel.dataset.paymentRecovery, location.href);
            url.searchParams.set('payment_status', pending);
            const response = await fetch(url, {headers:{Accept:'application/json'}, cache:'no-store'});
            if (!response.ok) throw new Error('Status unavailable');
            const result = await response.json();
            if (result.status === 'recorded') {
                message.textContent = 'Payment recorded. Receipt #' + result.receipt + '.';
                url.searchParams.delete('payment_status');
                url.searchParams.set('booking_id', result.booking_id);
                url.searchParams.set('tab', 'bill');
                account.href = url;
                account.hidden = false;
                dismiss.hidden = false;
            } else {
                message.textContent = 'No saved receipt was found for this submission yet. Check again and review the guest account before entering another payment.';
            }
        } catch {
            message.textContent = 'Payment status is unavailable. Check again after reconnecting before entering another payment.';
        } finally {
            check.disabled = false;
        }
    });
    dismiss.addEventListener('click', () => {
        sessionStorage.removeItem(key);
        panel.hidden = true;
    });
})();

(() => {
    const panel = document.querySelector('[data-room-search-panel]');
    if (!panel) return;
    const rooms = [...document.querySelectorAll('#stay-room-grid .stay-room')];
    const filter = panel.querySelector('[data-room-filter]');
    const empty = document.querySelector('[data-room-filter-empty]');
    panel.querySelector('[data-room-search-field]').hidden = false;
    const entries = rooms.map(room => ({
        room,
        search: [...room.querySelectorAll('h2,h3')].map(name => name.textContent.trim().toLocaleLowerCase()).join(' ')
    }));
    const search = panel.querySelector('[data-room-search]');
    const status = panel.querySelector('[data-room-search-status]');
    const render = () => {
        const terms = search.value.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
        let shown = 0;
        for (const entry of entries) {
            const matchesStatus = filter.value === 'all' || entry.room.dataset.preparation === filter.value;
            entry.room.hidden = !matchesStatus || !terms.every(term => entry.search.includes(term));
            if (!entry.room.hidden) shown++;
        }
        status.textContent = terms.length || filter.value !== 'all' ? shown + ' matching room' + (shown===1?'':'s') : '';
        empty.hidden = shown !== 0;
    };
    search.addEventListener('input', render);
    addEventListener('pageshow',render);
    filter.addEventListener('change', () => {
        const url = new URL(location.href);
        if (filter.value === 'all') url.searchParams.delete('preparation');
        else url.searchParams.set('preparation', filter.value);
        history.replaceState(history.state, '', url);
        render();
    });
    panel.addEventListener('submit', event => event.preventDefault());
    render();
})();

(() => {
    const feedback = document.querySelector('[data-extra-feedback]');
    if (!feedback) return;
    setTimeout(() => {
        feedback.classList.add('stay-feedback-dismissed');
        setTimeout(() => { feedback.hidden = true; }, 250);
    }, 4200);
})();

document.querySelectorAll('[data-start-cleaning]').forEach(form => {
    const button = form.querySelector('button');
    const error = form.querySelector('[data-cleaning-start-error]');
    const token = form.elements.room_token;
    let loading = false;
    form.addEventListener('submit', async event => {
        if (token.value) return;
        event.preventDefault();
        if (loading) return;
        loading = true;
        button.disabled = true;
        error.hidden = true;
        try {
            const response = await fetch(form.dataset.startCleaning, {cache:'no-store'});
            const data = await response.json();
            if (!response.ok || !/^[a-f0-9]{64}$/.test(data.token || '')) throw new Error();
            token.value = data.token;
            form.dataset.confirm = form.dataset.startConfirm;
        } catch {
            error.textContent = 'Room details could not be checked. Reload and try again.';
            error.hidden = false;
        } finally {
            loading = false;
            button.disabled = false;
        }
        if (token.value) form.requestSubmit(button);
    });
    form.hidden = false;
});
