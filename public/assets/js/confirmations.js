(() => {
    'use strict';
    const installed = Symbol.for('tivoli.confirmations');
    if (window[installed]) return;
    window[installed] = true;
    let active = null;
    const approved = new WeakSet(), posting = new WeakSet();
    const focusable = dialog => [...dialog.querySelectorAll('a[href],button:not(:disabled),input:not([type=hidden]):not(:disabled)')].filter(el => el.getClientRects().length);

    function open(dialog, cancel) {
        if (active || typeof dialog.showModal !== 'function') return false;
        active = dialog;
        dialog.removeAttribute('open');
        dialog.showModal();
        document.documentElement.classList.add('app-confirmation-open');
        dialog.querySelector('[data-confirmation-cancel]').focus({preventScroll:true});
        dialog.addEventListener('cancel', event => {event.preventDefault();cancel();});
        dialog.querySelector('[data-confirmation-dismiss]').addEventListener('click', cancel);
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const box = dialog.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) cancel();
        });
        // Contain keyboard focus, including when another app overlay is open.
        dialog.addEventListener('keydown', event => {
            event.stopPropagation();
            if (event.key !== 'Tab') return;
            const controls = focusable(dialog), first = controls[0], last = controls[controls.length-1];
            if (event.shiftKey && document.activeElement === first) {event.preventDefault();last.focus();}
            else if (!event.shiftKey && document.activeElement === last) {event.preventDefault();first.focus();}
        });
        return true;
    }

    function ask(message, title, acceptLabel, origin, accept) {
        if (active) return;
        const dialog = document.createElement('dialog');
        dialog.className = 'app-confirmation';
        dialog.setAttribute('aria-labelledby','app-confirmation-title');
        dialog.setAttribute('aria-describedby','app-confirmation-description');
        dialog.innerHTML = '<div class="app-confirmation-content"><button type="button" class="app-confirmation-close secondary" data-confirmation-dismiss aria-label="Cancel Confirmation">&times;</button><p class="app-eyebrow">PLEASE CONFIRM</p><h2 id="app-confirmation-title"></h2><p id="app-confirmation-description" class="app-confirmation-description"></p><div class="stay-actions"><button type="button" class="button secondary" data-confirmation-cancel>Go Back</button><button type="button" class="button" data-confirmation-accept></button></div></div>';
        dialog.querySelector('h2').textContent = title;
        dialog.querySelector('[data-confirmation-accept]').textContent = acceptLabel;
        dialog.querySelector('#app-confirmation-description').textContent = message;
        const finish = confirmed => {
            if (active !== dialog) return;
            active = null;
            dialog.close();dialog.remove();
            document.documentElement.classList.remove('app-confirmation-open');
            if (origin?.isConnected) origin.focus({preventScroll:true});
            if (confirmed) accept();
        };
        dialog.querySelector('[data-confirmation-cancel]').addEventListener('click',()=>finish(false));
        dialog.querySelector('[data-confirmation-accept]').addEventListener('click',()=>finish(true));
        document.body.append(dialog);
        if (!open(dialog,()=>finish(false))) dialog.remove();
    }

    // Intercept before page-specific submit handlers, so cancellation never marks a
    // form pending or enables wizard fields. Replay the original validated submit
    // only after approval, retaining its exact submitter and operation key.
    document.addEventListener('submit', event => {
        const form = event.target, submitter = event.submitter;
        if (approved.has(form)) {approved.delete(form);return;}
        if ((active && !active.contains(form)) || posting.has(form)) {event.preventDefault();event.stopImmediatePropagation();return;}
        form.dispatchEvent(new Event('tivoli:confirmation-preview'));
        let message = submitter?.dataset.confirm || form.dataset.confirm;
        if (!message) return;
        if (form.hasAttribute('data-checkin-payment')) {
            const method = form.elements.payment_type.value === 'momo' ? 'MoMo' : 'Cash';
            const room = form.dataset.roomLabel ? ' · Room ' + form.dataset.roomLabel : '';
            message = form.elements.guest_name.value + room + ' · ' + form.elements.amount.value
                + ' GHS via ' + method + '. Confirm guest identity, room readiness and payment received?';
        } else if (form.hasAttribute('data-stay-payment')) {
            const method = form.elements.method.value === 'momo' ? 'MoMo' : 'Cash';
            message = 'Room ' + form.dataset.roomLabel + ' · ' + form.elements.amount.value
                + ' GHS via ' + method + '. Record this payment?';
        }
        event.preventDefault();event.stopImmediatePropagation();
        const label = (submitter?.textContent || 'Confirm Action').trim();
        ask(message, form.dataset.confirmTitle || label.replace(/\?$/,'')+'?', 'Yes, '+label, submitter || document.activeElement, () => {
            if (!form.isConnected || (submitter && (!submitter.isConnected || submitter.matches(':disabled')))) return;
            let replay;
            const capture = e => {if(e.target === form) replay = e;};
            form.addEventListener('submit',capture,{capture:true,once:true});
            approved.add(form);
            try {form.requestSubmit(submitter || undefined);}
            finally {
                approved.delete(form);form.removeEventListener('submit',capture,true);
                if (replay && !replay.defaultPrevented) posting.add(form);
            }
        });
    }, true);

    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-confirm]');
        if (!link) return;
        event.preventDefault();event.stopImmediatePropagation();
        ask(link.dataset.confirm, (link.textContent.trim() || 'Confirm Action')+'?', 'Yes, Continue', link, () => window.location.assign(link.href));
    }, true);

    // Payment and departure keep their server-issued confirmation tokens/forms.
    const serverDialog = document.querySelector('[data-stay-confirmation]');
    if (serverDialog) {
        const back = serverDialog.querySelector('[data-confirmation-cancel]');
        open(serverDialog,()=>window.location.assign(back.href));
        const form = serverDialog.querySelector('[data-confirmation-submit]');
        form.addEventListener('submit',event => {
            if (form.dataset.submitting === '1') {event.preventDefault();return;}
            form.dataset.submitting = '1';
            const submit = form.querySelector('button[type=submit]');
            submit.disabled = true;submit.setAttribute('aria-busy','true');
        });
    }
    window.addEventListener('pageshow',()=>{
        document.querySelectorAll('form').forEach(form=>posting.delete(form));
        if (serverDialog) {
            const form = serverDialog.querySelector('form'), submit = form.querySelector('button[type=submit]');
            delete form.dataset.submitting;submit.disabled=false;submit.removeAttribute('aria-busy');
        }
    });
})();
