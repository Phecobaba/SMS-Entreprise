import './bootstrap';

const oakSheets = () => {
    document.querySelectorAll('.oak-scrim[data-open]').forEach((sheet) => sheet.removeAttribute('data-open'));
};

document.addEventListener('click', (event) => {
    const tooltip = event.target.closest('[data-oak-tooltip-trigger]');
    if (tooltip) {
        const wrapper = tooltip.closest('[data-oak-tooltip]');
        const opening = !wrapper.hasAttribute('data-open');
        document.querySelectorAll('[data-oak-tooltip][data-open]').forEach((item) => {
            item.removeAttribute('data-open');
            item.querySelector('[data-oak-tooltip-trigger]')?.setAttribute('aria-expanded', 'false');
        });
        wrapper.toggleAttribute('data-open', opening);
        tooltip.setAttribute('aria-expanded', String(opening));
        return;
    }
    document.querySelectorAll('[data-oak-tooltip][data-open]').forEach((item) => {
        item.removeAttribute('data-open');
        item.querySelector('[data-oak-tooltip-trigger]')?.setAttribute('aria-expanded', 'false');
    });

    const credentials = event.target.closest('[data-copy-credentials]');
    if (credentials) {
        const sheet = document.querySelector('[data-credential-sheet]');
        const status = document.querySelector('[data-copy-status]');
        const text = [...(sheet?.querySelectorAll('[data-credential-slip]') ?? [])].map((slip) => {
            const username = slip.querySelector('[data-credential-username]')?.textContent.trim() ?? '';
            const password = slip.querySelector('[data-credential-password]')?.textContent.trim() ?? '';
            return `Username: ${username}\nPassword: ${password}`;
        }).join('\n\n');
        const confirm = (message) => {
            if (!status) return;
            status.textContent = message;
            window.clearTimeout(status._oakTimer);
            status._oakTimer = window.setTimeout(() => { status.textContent = ''; }, 3500);
        };
        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(text).then(() => confirm('Copied')).catch(() => {
                const selection = window.getSelection();
                const range = document.createRange();
                range.selectNodeContents(sheet);
                selection.removeAllRanges();
                selection.addRange(range);
                confirm('Copy is unavailable. The details are selected; press copy.');
            });
        } else {
            const selection = window.getSelection();
            const range = document.createRange();
            range.selectNodeContents(sheet);
            selection.removeAllRanges();
            selection.addRange(range);
            confirm('Copy is unavailable. The details are selected; press copy.');
        }
        return;
    }

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

    const group = event.target.closest('[data-more-group]');
    if (group) {
        const sheet = group.closest('.oak-sheet');
        sheet.querySelector('[data-more-root]').hidden = true;
        sheet.querySelector(`[data-more-panel="${group.dataset.moreGroup}"]`).hidden = false;
        sheet.querySelector('[data-more-back]')?.focus();
        return;
    }

    const back = event.target.closest('[data-more-back]');
    if (back) {
        const sheet = back.closest('.oak-sheet');
        sheet.querySelectorAll('[data-more-panel]').forEach((panel) => { panel.hidden = true; });
        sheet.querySelector('[data-more-root]').hidden = false;
        sheet.querySelector('[data-more-group]')?.focus();
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
    if (event.key === 'Escape') {
        oakSheets();
        document.querySelectorAll('[data-oak-tooltip][data-open]').forEach((item) => {
            item.removeAttribute('data-open');
            item.querySelector('[data-oak-tooltip-trigger]')?.setAttribute('aria-expanded', 'false');
        });
    }
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

const bootHeadPickers = () => {
    document.querySelectorAll('[data-head-picker]').forEach((picker) => {
        if (picker.dataset.ready) return;
        picker.dataset.ready = 'true';
        const search = picker.querySelector('[data-head-search]');
        const select = picker.querySelector('[data-head-select]');
        const count = picker.querySelector('[data-head-count]');
        const options = [...(select?.querySelectorAll('option[data-head-name]') ?? [])];
        const filter = () => {
            const query = search.value.trim().toLocaleLowerCase();
            let visible = 0;
            options.forEach((option) => {
                const matches = query === '' || option.dataset.headName.includes(query);
                option.hidden = !matches;
                if (matches) visible += 1;
            });
            if (count) count.textContent = String(visible);
        };
        search?.addEventListener('input', filter);
        filter();
    });
};

const bootRoleDashboardTabs = () => {
    document.querySelectorAll('[data-role-tabs]').forEach((tabs) => {
        if (tabs.dataset.ready) return;
        tabs.dataset.ready = 'true';
        const buttons = [...tabs.querySelectorAll('[data-role-tab]')];
        const blocks = [...document.querySelectorAll('[data-role-block]')];
        const show = (role) => {
            buttons.forEach((button) => button.setAttribute('aria-selected', button.dataset.roleTab === role ? 'true' : 'false'));
            blocks.forEach((block) => block.toggleAttribute('data-phone-hidden', block.dataset.roleBlock !== role));
        };
        buttons.forEach((button) => button.addEventListener('click', () => show(button.dataset.roleTab)));
        show(tabs.dataset.defaultRole);
    });
};

const bootOakUi = () => {
    document.querySelectorAll('[data-ticker]').forEach(startTicker);
    bootAssignmentForms();
    bootResultSectionForms();
    bootHeadPickers();
    bootRoleDashboardTabs();
};
document.addEventListener('DOMContentLoaded', bootOakUi);
document.addEventListener('livewire:navigated', bootOakUi);
