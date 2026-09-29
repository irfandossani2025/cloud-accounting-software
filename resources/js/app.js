import './bootstrap';

// Tally-style keyboard shortcuts. Links carry data-shortcut="F5", "Ctrl+F8", "Alt+L" etc.
const keyName = (e) => {
    const parts = [];
    if (e.ctrlKey || e.metaKey) parts.push('Ctrl');
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
