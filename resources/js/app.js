import './bootstrap';

// Tally-style keyboard shortcuts. Links carry data-shortcut="F5", "Ctrl+F8", "Alt+L" etc.
const keyName = (e) => {
    const parts = [];
    if (e.ctrlKey) parts.push('Ctrl');
    if (e.metaKey) parts.push('Meta');
    if (e.altKey) parts.push('Alt');
    const key = e.key.length === 1 ? e.key.toUpperCase() : e.key;
    if (!['Control', 'Alt', 'Meta', 'Shift'].includes(key)) parts.push(e.altKey && e.code.startsWith('Key') ? e.code.slice(3) : key);
    return parts.join('+');
};

document.addEventListener('keydown', (e) => {
    const name = keyName(e);

    if (name === 'Escape' && !document.activeElement?.closest('[data-no-escape]') && !overlayOpen()) {
        const back = document.querySelector('[data-shortcut="Escape"]');
        if (back) { e.preventDefault(); back.click(); }
        return;
    }

    const target = document.querySelector(`[data-shortcut="${name}"]`);
    if (target) {
        e.preventDefault();
        // Commit the field being edited (wire:model.blur) so it is sent with the action.
        document.activeElement?.blur();
        target.click();
    }
});

// Export the report tables on the page to CSV (opens in Excel; UTF-8 BOM keeps Arabic intact).
document.addEventListener('click', (e) => {
    const button = e.target.closest('[data-export-csv]');
    if (!button) return;

    const cellText = (cell) => {
        const input = cell.querySelector('input, select');
        let text = input ? (input.tagName === 'SELECT' ? input.selectedOptions[0]?.text ?? '' : input.value) : cell.innerText;
        text = text.replace(/\s+/g, ' ').trim();
        // Numeric columns: drop thousands separators so Excel treats them as numbers.
        if (cell.classList.contains('num') && /^-?[\d,]+(\.\d+)?( (Dr|Cr))?$/.test(text)) {
            const [, amount, side] = text.match(/^(-?[\d,]+(?:\.\d+)?)(?: (Dr|Cr))?$/);
            text = amount.replace(/,/g, '');
            if (side === 'Cr') text = '-' + text.replace(/^-/, '');
        }
        return '"' + text.replace(/"/g, '""') + '"';
    };

    const lines = [`"${document.title}"`, ''];
    document.querySelectorAll('main table').forEach((table) => {
        table.querySelectorAll('tr').forEach((row) => {
            if (row.closest('.no-print') || row.classList.contains('no-print')) return;
            const cells = [];
            row.querySelectorAll('th, td').forEach((cell) => {
                if (cell.classList.contains('no-print')) return;
                cells.push(cellText(cell));
                for (let i = 1; i < (cell.colSpan || 1); i++) cells.push('""');
            });
            if (cells.some((c) => c !== '""')) lines.push(cells.join(','));
        });
        lines.push('');
    });

    const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = document.title.split('·')[0].trim().replace(/[^\w\- ]+/g, '').replace(/\s+/g, '-') + '-' + new Date().toISOString().slice(0, 10) + '.csv';
    link.click();
    URL.revokeObjectURL(link.href);
});

const overlayOpen = () => [...document.querySelectorAll('[data-no-escape]')].some((el) => el.offsetParent !== null);

const isTyping = (el) => el && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));

// Top menu: E Export and P Print act on the current screen.
document.addEventListener('click', (e) => {
    const action = e.target.closest('[data-action]')?.dataset.action;
    if (action === 'export') {
        const exporter = document.querySelector('main [data-export-csv]');
        exporter ? exporter.click() : alert('This screen has nothing to export.');
    } else if (action === 'print') {
        const printer = document.querySelector('main [data-print]');
        printer ? printer.click() : window.print();
    }
});

// Tally data entry: Enter moves to the next field instead of submitting the form.
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' || e.shiftKey || e.ctrlKey || e.metaKey || e.altKey) return;
    const field = e.target;
    const form = field.closest?.('form');
    if (!form || form.hasAttribute('data-enter-submits')) return;
    if (!['INPUT', 'SELECT'].includes(field.tagName) || ['submit', 'button', 'checkbox', 'radio'].includes(field.type)) return;

    const focusable = [...form.querySelectorAll('input, select, textarea, button[type="submit"], button:not([type])')]
        .filter((el) => !el.disabled && el.type !== 'hidden' && el.offsetParent !== null);
    const next = focusable[focusable.indexOf(field) + 1];
    if (next) {
        e.preventDefault();
        next.focus();
        next.select?.();
    }
});

// Right button bar: mirror the current screen's own shortcuts (Accept, Detailed, Print...).
const refreshButtonBar = () => {
    const bar = document.getElementById('button-bar-page');
    if (!bar) return;
    const seen = new Set();
    const buttons = [];
    document.querySelectorAll('main [data-shortcut]').forEach((el) => {
        const key = el.dataset.shortcut;
        if (key === 'Escape' || /^(Ctrl\+)?F[4-9]$|^Alt\+F(7|10)$/.test(key) || seen.has(key)) return;
        seen.add(key);
        const label = [...el.childNodes].filter((n) => !(n.classList?.contains('kbd'))).map((n) => n.textContent).join('').replace(/\s+/g, ' ').trim();
        buttons.push({ key, label: label || key, el });
    });
    const back = document.querySelector('main [data-shortcut="Escape"]');
    if (back) buttons.unshift({ key: 'Esc', label: 'Back', el: back });

    bar.replaceChildren(...buttons.map(({ key, label, el }) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'tally-fbutton w-full';
        b.innerHTML = `<span class="tally-fkey"></span><span class="truncate"></span>`;
        b.children[0].textContent = key;
        b.children[1].textContent = label;
        b.addEventListener('click', () => { document.activeElement?.blur(); el.click(); });
        return b;
    }));
};

let barTimer;
const scheduleBar = () => { clearTimeout(barTimer); barTimer = setTimeout(refreshButtonBar, 50); };
document.addEventListener('livewire:navigated', scheduleBar);
document.addEventListener('DOMContentLoaded', () => {
    scheduleBar();
    const main = document.querySelector('main');
    if (main) new MutationObserver(scheduleBar).observe(main, { childList: true, subtree: true });
});
document.addEventListener('livewire:navigated', () => {
    const main = document.querySelector('main');
    if (main && !main.__barObserved) {
        main.__barObserved = true;
        new MutationObserver(scheduleBar).observe(main, { childList: true, subtree: true });
    }
});

document.addEventListener('alpine:init', () => {
    // Gateway menus: single-letter hotkeys, arrow keys and Enter, like Tally.
    window.Alpine.data('tallyMenu', (indexes) => ({
        indexes,
        current: indexes[0] ?? null,
        onKey(e) {
            if (isTyping(document.activeElement) || e.ctrlKey || e.metaKey || e.altKey) return;
            if (overlayOpen()) return;

            const pos = this.indexes.indexOf(this.current);
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const next = pos + (e.key === 'ArrowDown' ? 1 : -1);
                this.current = this.indexes[(next + this.indexes.length) % this.indexes.length];
            } else if (e.key === 'Enter' && this.current !== null) {
                e.preventDefault();
                this.$root.querySelector(`[data-index="${this.current}"]`)?.click();
            } else if (e.key.length === 1) {
                const link = this.$root.querySelector(`[data-hotkey="${e.key.toUpperCase()}"]`);
                if (link) {
                    e.preventDefault();
                    this.current = Number(link.dataset.index);
                    link.click();
                }
            }
        },
    }));

    // Go To (Alt+G): search every screen and ledger.
    window.Alpine.data('tallyGoTo', (items) => ({
        items,
        visible: false,
        query: '',
        current: 0,
        get results() {
            const words = this.query.toLowerCase().split(/\s+/).filter(Boolean);
            return this.items.filter((item) => words.every((w) => (item.label + ' ' + item.group).toLowerCase().includes(w))).slice(0, 50);
        },
        open() {
            this.visible = true;
            this.query = '';
            this.current = 0;
            this.$nextTick(() => setTimeout(() => this.$refs.search.focus(), 30));
        },
        close() {
            this.visible = false;
        },
        onKey(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                this.close();
            } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const n = this.results.length;
                if (n) this.current = (this.current + (e.key === 'ArrowDown' ? 1 : n - 1)) % n;
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const item = this.results[this.current];
                if (item) {
                    this.close();
                    window.Livewire.navigate(item.url);
                }
            } else {
                this.current = 0;
            }
        },
    }));
});
