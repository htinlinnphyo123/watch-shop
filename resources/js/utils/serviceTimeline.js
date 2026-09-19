export const money = value => Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 2 });
export const chargeLabel = details => details?.billing_status === 'free' ? 'Free of charge' : details?.billing_status === 'chargeable' ? `${money(details.customer_charge)} MMK` : 'Not decided yet';

// Compare with the previous saved update, even when the display is newest first.
export function serviceTimeline(events, statuses, decisions, faults, warranty = false) {
    return [...events].sort((a, b) => a.id - b.id).map((event, index, ordered) => {
        const previous = ordered[index - 1];
        const current = event.repair_details;
        const before = previous?.repair_details;
        const changes = [];
        const changed = key => (current?.[key] ?? '') !== (before?.[key] ?? '');
        if (previous && event.status !== previous.status) changes.push({ label: 'Progress', value: `${statuses[previous.status]} → ${statuses[event.status]}` });
        if (current && before) {
            if (chargeLabel(current) !== chargeLabel(before)) changes.push({ label: 'Customer charge', value: `${chargeLabel(before)} → ${chargeLabel(current)}` });
            if (changed('charge_notes')) changes.push({ label: 'Charge explanation', value: current.charge_notes || 'Removed' });
            if (changed('fault_type')) changes.push({ label: 'Cause of the problem', value: faults[current.fault_type] || 'Not determined yet' });
            if (changed('diagnosis')) changes.push({ label: 'Inspection findings', value: current.diagnosis || 'Removed' });
            if (changed('repair_provider') || (current.repair_provider === 'external' && changed('external_shop_name'))) changes.push({ label: 'Repair location', value: current.repair_provider === 'external' ? current.external_shop_name : 'Time On You' });
            if (current.repair_provider === 'external') {
                for (const [key, label] of [['external_shop_phone', 'Shop phone'], ['external_reference', 'Shop reference'], ['provider_notes', 'Instructions for the shop']]) {
                    if (changed(key)) changes.push({ label, value: current[key] || 'Removed' });
                }
            }
        }
        if (warranty && previous) {
            if (event.coverage_decision !== previous.coverage_decision) changes.push({ label: 'Warranty decision', value: decisions[event.coverage_decision] });
            if ((event.decision_notes || '') !== (previous.decision_notes || '')) changes.push({ label: 'Warranty explanation', value: event.decision_notes || 'Removed' });
        }
        const activityTitle = { expense_added: 'Expense added', expense_cancelled: 'Incorrect expense cancelled' }[current?.activity?.type];
        return { ...event, changes, title: activityTitle || (!previous ? 'Repair job opened' : event.status !== previous.status ? statuses[event.status] : 'Update added') };
    }).reverse();
}
