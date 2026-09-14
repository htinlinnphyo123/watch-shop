import { paymentMethodLabel } from './payments.js';

// Watch identity excludes price, so a payment or price edit never looks like a swap.
export function watchChanges(before, after) {
    const collect = snapshot => {
        const watches = new Map();
        for (const watch of Object.values(snapshot?.watches || {})) {
            const key = JSON.stringify([watch.unit_id ?? null, watch.product_id, watch.system_code ?? null, watch.serial ?? null]);
            const existing = watches.get(key);
            watches.set(key, { ...watch, quantity: Number(watch.quantity) + (existing?.quantity || 0) });
        }
        return watches;
    };
    const old = collect(before);
    const next = collect(after);
    const removed = [], added = [];
    for (const key of new Set([...old.keys(), ...next.keys()])) {
        const delta = (next.get(key)?.quantity || 0) - (old.get(key)?.quantity || 0);
        if (delta < 0) removed.push({ ...old.get(key), quantity: -delta });
        if (delta > 0) added.push({ ...next.get(key), quantity: delta });
    }
    return { removed, added };
}

export const money = value => value == null ? 'Not recorded' : `${Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 })} Ks`;
const paymentName = method => ({ transfer: 'Bank transfer', unrecorded: 'Unspecified payment' }[method] || paymentMethodLabel(method));
const titleCase = value => value ? value.charAt(0).toUpperCase() + value.slice(1) : 'Not recorded';
const equal = (a, b) => {
    if (a === b) return true;
    if (!a || !b || typeof a !== 'object' || typeof b !== 'object') return false;
    return Object.keys(a).length === Object.keys(b).length && Object.keys(a).every(key => equal(a[key], b[key]));
};
const rows = value => [{ label: '', value }];
const kind = (before, after) => before == null ? 'added' : after == null ? 'removed' : 'updated';
const watchFields = {
    name: ['Watch', value => value], model: ['Model', value => value || 'Not recorded'],
    quantity: ['Quantity', value => String(value)], unit_price: ['Price each', money],
    system_code: ['System code', value => value || 'Not assigned'], serial: ['Serial number', value => value || 'Not recorded'],
};
const watchRows = (watch, keys) => keys.map(key => ({ label: watchFields[key][0], value: watchFields[key][1](watch[key]) }));

// Compare saved values directly: display strings from older audit entries remain untouched.
export function orderChangeGroups(before, after) {
    if (!before || !after) return [];
    const groups = { Watches: [], Payments: [], 'Order details': [], Files: [] };
    const fields = [
        ['customer', 'Customer changed', value => value?.name || 'Walk-in Customer'],
        ['discount_percentage', 'Discount changed', value => `${Number(value || 0)}%`],
        ['total_amount', 'Order total changed', money],
        ['amount_paid', 'Amount received changed', money],
        ['change', 'Change returned changed', money],
        ['status', 'Order status changed', titleCase],
        ['order_number', 'Order number changed', value => value],
    ];
    for (const [key, title, format] of fields) {
        if (!equal(before[key], after[key])) {
            const sameName = key === 'customer' && before[key]?.name === after[key]?.name;
            groups['Order details'].push({ title, kind: 'updated', before: rows(format(before[key])), after: rows(format(after[key])),
                note: sameName ? 'A different customer record was selected with the same name.' : null });
        }
    }
    for (const key of new Set([...Object.keys(before.watches || {}), ...Object.keys(after.watches || {})])) {
        const old = before.watches?.[key];
        const next = after.watches?.[key];
        if (equal(old, next)) continue;
        const type = kind(old, next);
        const keys = old && next ? Object.keys(watchFields).filter(field => !equal(old[field], next[field]))
            : ['quantity', 'unit_price', 'system_code', 'serial'].filter(field => !['system_code', 'serial'].includes(field) || (next || old)[field]);
        if (!keys.length) continue;
        groups.Watches.push({ title: type === 'added' ? 'Watch added' : type === 'removed' ? 'Watch removed' : 'Watch details changed',
            kind: type, name: (next || old).name, subtitle: (next || old).model,
            before: old ? watchRows(old, keys) : rows('Not in the order'),
            after: next ? watchRows(next, keys) : rows('Removed from the order') });
    }
    for (const method of new Set([...Object.keys(before.payments || {}), ...Object.keys(after.payments || {})])) {
        const old = before.payments?.[method];
        const next = after.payments?.[method];
        if (old === next) continue;
        const type = kind(old, next);
        groups.Payments.push({ title: `${paymentName(method)} payment ${type === 'updated' ? 'changed' : type}`, kind: type,
            before: rows(old == null ? 'No payment' : money(old)), after: rows(next == null ? 'No payment' : money(next)) });
    }
    for (const key of new Set([...Object.keys(before.attachments || {}), ...Object.keys(after.attachments || {})])) {
        const old = before.attachments?.[key];
        const next = after.attachments?.[key];
        if (equal(old, next)) continue;
        const type = kind(old, next);
        groups.Files.push({ title: `File ${type === 'updated' ? 'changed' : type}`, kind: type,
            before: rows(old?.name || 'No file'), after: rows(next?.name || 'File removed') });
    }
    return Object.entries(groups).filter(([, changes]) => changes.length).map(([title, changes]) => ({ title, changes }));
}

export function historyLabels(versions) {
    let edit = 0;
    return Object.fromEntries([...versions].sort((a, b) => a.version - b.version).map(entry => [entry.version,
        entry.event === 'edited' ? `Edit ${++edit}` : ({ created: 'Original order', baseline: 'First saved record', approved: 'Order approved', attachment_added: 'File uploaded' }[entry.event] || 'Order updated'),
    ]));
}

export function activitySummary(entry) {
    if (entry.event === 'created') return 'The order was first placed.';
    if (entry.event === 'baseline') return 'The earliest order details available.';
    if (entry.event === 'approved') return 'Approved and watch stock assigned.';
    if (entry.event === 'attachment_added') {
        const file = entry.changes.find(change => change.section === 'Attachments');
        return file ? `Uploaded ${file.label}` : 'A file was attached to the order.';
    }
    if (!entry.changes.length) return 'Saved without changing the order.';
    const counts = {};
    for (const change of entry.changes) {
        let label;
        if (change.section === 'Watches') label = change.before == null ? 'watch added' : change.after == null ? 'watch removed' : 'watch changed';
        else if (change.section === 'Payments (Ks)') label = `${paymentName(change.label)} payment changed`;
        else if (change.section === 'Attachments') label = 'file changed';
        else label = ({ 'Total (Ks)': 'Order total changed', 'Discount (%)': 'Discount changed', 'Amount received (Ks)': 'Amount received changed', 'Change (Ks)': 'Change returned changed' }[change.label] || `${change.label} changed`);
        counts[label] = (counts[label] || 0) + 1;
    }
    return Object.entries(counts).map(([label, count]) => count > 1 ? `${count} ${label.replace(/^watch /, 'watches ')}` : titleCase(label)).join(' · ');
}
