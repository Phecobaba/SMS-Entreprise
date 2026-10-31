import './bootstrap';

const oakSheets = () => {
    document.querySelectorAll('.oak-scrim[data-open]').forEach((sheet) => sheet.removeAttribute('data-open'));
};

document.addEventListener('click', (event) => {
    const copy = event.target.closest('[data-copy-text]');
    if (copy) {
        navigator.clipboard?.writeText(copy.dataset.copyText).then(() => {
            copy.textContent = 'Copied';
        });
        return;
    }

    const opener = event.target.closest('[data-open-sheet]');
    if (opener) {
        event.preventDefault();
        const sheet = document.getElementById(opener.dataset.openSheet);
        if (!sheet) return;
        sheet._oakReturnFocus = document.activeElement;
        sheet.setAttribute('data-open', '');
        sheet.querySelector('button, a, input, select, textarea')?.focus();
        return;
    }

    const closer = event.target.closest('[data-close-sheet]');
    const scrim = closer?.closest('.oak-scrim') ?? (event.target.classList?.contains('oak-scrim') ? event.target : null);
    if (scrim) {
        scrim.removeAttribute('data-open');
        scrim._oakReturnFocus?.focus?.();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') oakSheets();
});

const startTicker = (ticker) => {
    if (ticker.dataset.ready) return;
    ticker.dataset.ready = 'true';
    const items = [...ticker.querySelectorAll('.oak-ticker-track > a')];
    const phoneItem = ticker.querySelector('[data-ticker-static]');
    const pause = ticker.querySelector('[data-ticker-pause]');
    let index = 0;
    let timer = null;

    const show = () => {
        const item = items[index];
        if (!item || !phoneItem) return;
        phoneItem.replaceChildren(item.children[1].cloneNode(true), document.createTextNode(` · ${item.children[2].textContent}`));
        phoneItem.href = item.href;
        phoneItem.removeAttribute('data-fade');
        void phoneItem.offsetWidth;
        phoneItem.setAttribute('data-fade', '');
    };
    const stop = () => { window.clearInterval(timer); timer = null; };
    const start = () => {
        if (timer || !items.length || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        timer = window.setInterval(() => { index = (index + 1) % items.length; show(); }, 5000);
    };

    pause?.addEventListener('click', () => {
        const paused = pause.getAttribute('aria-pressed') !== 'true';
        pause.setAttribute('aria-pressed', String(paused));
        pause.setAttribute('aria-label', paused ? 'Resume moving notifications' : 'Pause moving notifications');
        ticker.toggleAttribute('data-paused', paused);
        paused ? stop() : start();
    });
    ticker.addEventListener('focusin', stop);
    ticker.addEventListener('focusout', () => { if (pause?.getAttribute('aria-pressed') !== 'true') start(); });
    start();
};

const bootAssignmentForms = () => {
    document.querySelectorAll('[data-require-choice]').forEach((form) => {
        if (form.dataset.ready) return;
        form.dataset.ready = 'true';
        form.addEventListener('submit', (event) => {
            const hasChoice = [...form.querySelectorAll('input[type="checkbox"]')].some((input) => input.checked);
            const error = form.querySelector('[data-choice-error]');
            if (!hasChoice) {
                event.preventDefault();
                if (error) error.hidden = false;
                form.querySelector('input[type="checkbox"]')?.focus();
            } else if (error) error.hidden = true;
        });
    });

    document.querySelectorAll('[data-change-form]').forEach((form) => {
        if (form.dataset.ready) return;
        form.dataset.ready = 'true';
        const controls = [...form.querySelectorAll('input[type="checkbox"]:not(:disabled), select[name^="teachers"]')];
        const initial = controls.map((control) => control.type === 'checkbox' ? control.checked : control.value);
        const review = form.querySelector('[data-review-button]');
        const update = () => {
            const changed = controls.some((control, index) => (control.type === 'checkbox' ? control.checked : control.value) !== initial[index]);
            if (review) review.disabled = !changed;
        };
        controls.forEach((control) => control.addEventListener('change', update));
        update();
    });
};

const bootResultSectionForms = () => {
    document.querySelectorAll('[data-result-section-form], [data-result-scope-form]').forEach((form) => {
        if (form.dataset.ready) return;
        form.dataset.ready = 'true';
        const section = form.querySelector('[data-result-section]');
        const level = form.querySelector('[data-result-level]');
        const classField = form.querySelector('[data-result-class-field]');
        const classSelect = form.querySelector('[data-result-class]');
        const update = () => {
            if (section) {
                const selected = section.value;
                form.querySelectorAll('[data-section-id]').forEach((item) => {
                    const visible = selected !== '' && item.dataset.sectionId === selected;
                    item.hidden = !visible;
                    if (!visible && item.matches('option')) item.selected = false;
                    if (!visible) item.querySelectorAll?.('input').forEach((input) => { input.checked = false; });
                });
                const allLevels = form.querySelector('[data-all-levels-option]');
                const sectionName = section.selectedOptions[0]?.textContent ?? 'selected Section';
                if (allLevels) allLevels.textContent = `All levels in ${selected === '' ? 'selected Section' : sectionName}`;
            }
            if (level && classField && classSelect) {
                const selectedLevel = level.value;
                classField.hidden = selectedLevel === '';
                classSelect.disabled = selectedLevel === '';
                classSelect.querySelectorAll('[data-level-id]').forEach((option) => {
                    const visible = selectedLevel !== '' && option.dataset.levelId === selectedLevel;
                    option.hidden = !visible;
                    if (!visible) option.selected = false;
                });
            }
        };
        section?.addEventListener('change', update);
        level?.addEventListener('change', update);
        update();
    });
};

const bootOakUi = () => {
    document.querySelectorAll('[data-ticker]').forEach(startTicker);
    bootAssignmentForms();
    bootResultSectionForms();
};
document.addEventListener('DOMContentLoaded', bootOakUi);
document.addEventListener('livewire:navigated', bootOakUi);
