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

    if (name === 'Escape' && !document.activeElement?.closest('[data-no-escape]')) {
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
