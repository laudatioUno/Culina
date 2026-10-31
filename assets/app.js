(() => {
    'use strict';

    // Submit the surrounding form when a marked control changes.
    document.addEventListener('change', (e) => {
        const el = e.target.closest('[data-autosubmit]');
        if (!el || !el.form) return;
        if (el.type === 'number' && el.value === '') return;
        if (typeof el.form.requestSubmit === 'function') el.form.requestSubmit();
        else el.form.submit();
    });

    // Confirmation for forms or buttons carrying data-confirm.
    document.addEventListener('submit', (e) => {
        const msg = (e.submitter && e.submitter.dataset.confirm) || e.target.dataset.confirm;
        if (msg && !window.confirm(msg)) e.preventDefault();
    });

    // Shopping list: tick items without reloading the page.
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!form.matches('form[data-ajax]') || e.defaultPrevented) return;
        e.preventDefault();
        const box = form.querySelector('input[type=checkbox]');
        const item = form.closest('.shop-item');
        if (item && box) item.classList.toggle('is-hidden', box.checked);
        try {
            const res = await fetch(form.action || window.location.href, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'fetch' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error(String(res.status));
        } catch (err) {
            form.removeAttribute('data-ajax');
            form.submit();
        }
    });

    // Recipe form: add and remove ingredient rows.
    const rows = document.querySelector('[data-ing-rows]');
    const tpl = document.getElementById('ing-template');
    if (rows && tpl) {
        let counter = 1000;
        const addRow = () => {
            const html = tpl.innerHTML.replace(/__i__/g, String(counter++));
            rows.insertAdjacentHTML('beforeend', html);
            const inputs = rows.lastElementChild.querySelectorAll('input');
            if (inputs[0]) inputs[0].focus();
        };
        document.querySelector('[data-add-row]')?.addEventListener('click', addRow);
        rows.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-remove-row]');
            if (!btn) return;
            const row = btn.closest('.ing-row');
            if (rows.children.length > 1) row.remove();
            else row.querySelectorAll('input[type=text]').forEach((i) => { i.value = ''; });
        });
        // Enter in the last row adds a new row instead of submitting.
        rows.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' || e.target.tagName !== 'INPUT') return;
            e.preventDefault();
            if (e.target.closest('.ing-row') === rows.lastElementChild) addRow();
        });
    }

    // Sortable lists (aisles, categories): buttons and drag & drop.
    document.querySelectorAll('[data-sortable]').forEach((list) => {
        list.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-move]');
            if (!btn) return;
            const li = btn.closest('li');
            if (btn.dataset.move === '-1' && li.previousElementSibling) li.previousElementSibling.before(li);
            if (btn.dataset.move === '1' && li.nextElementSibling) li.nextElementSibling.after(li);
            btn.focus();
        });
        let dragged = null;
        list.addEventListener('dragstart', (e) => {
            if (!e.target.closest('.grip')) return;
            dragged = e.target.closest('li');
            e.dataTransfer.setDragImage(dragged, 12, 12);
            dragged.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        list.addEventListener('dragend', () => { dragged?.classList.remove('dragging'); dragged = null; });
        list.addEventListener('dragover', (e) => {
            if (!dragged) return;
            e.preventDefault();
            const over = e.target.closest('li');
            if (!over || over === dragged) return;
            const rect = over.getBoundingClientRect();
            if (e.clientY > rect.top + rect.height / 2) over.after(dragged);
            else over.before(dragged);
        });
    });

    // Copy buttons.
    document.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const src = document.querySelector(btn.dataset.copy);
            if (!src) return;
            try {
                await navigator.clipboard.writeText(src.value || src.textContent);
            } catch {
                src.select();
                document.execCommand('copy');
            }
            const old = btn.textContent;
            btn.textContent = 'Kopiert ✓';
            setTimeout(() => { btn.textContent = old; }, 1600);
        });
    });

    // Cooking mode: keep the screen awake.
    const wake = document.querySelector('[data-wakelock]');
    if (wake && 'wakeLock' in navigator) {
        let lock = null;
        wake.hidden = false;
        const set = (on) => { wake.setAttribute('aria-pressed', on ? 'true' : 'false'); wake.textContent = on ? 'Bildschirm bleibt wach ✓' : 'Bildschirm wach halten'; };
        wake.addEventListener('click', async () => {
            if (lock) { await lock.release(); lock = null; set(false); return; }
            try {
                lock = await navigator.wakeLock.request('screen');
                set(true);
                lock.addEventListener('release', () => { lock = null; set(false); });
            } catch { /* not allowed, ignore */ }
        });
    }
})();
