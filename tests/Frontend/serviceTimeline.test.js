import test from 'node:test';
import assert from 'node:assert/strict';
import { serviceTimeline } from '../../resources/js/utils/serviceTimeline.js';
const statuses = { received: 'Watch received', repairing: 'Repair in progress' };
const details = { billing_status: 'chargeable', customer_charge: '100.00', repair_provider: 'in_house', fault_type: 'unknown' };
const event = (id, overrides = {}) => ({ id, status: 'received', repair_details: { ...details }, ...overrides });
const timeline = events => serviceTimeline(events, statuses, {}, { mechanical: 'Mechanical fault' });
test('shows changes against the previous event regardless of input order', () => {
    const result = timeline([event(3, { status: 'repairing', repair_details: { ...details, customer_charge: '200.00', diagnosis: 'Replace movement' } }), event(1), event(2)]);
    assert.deepEqual(result.map(item => item.id), [3, 2, 1]);
    assert.deepEqual(result[0].changes, [{ label: 'Progress', value: 'Watch received → Repair in progress' }, { label: 'Customer charge', value: '100 MMK → 200 MMK' }, { label: 'Inspection findings', value: 'Replace movement' }]);
    assert.equal(result[2].title, 'Repair job opened');
    assert.deepEqual(result[1].changes, []);
});
test('handles older records and equivalent numeric charges without false changes', () => {
    assert.doesNotThrow(() => timeline([event(1, { repair_details: null }), event(2)]));
    assert.deepEqual(timeline([event(1), event(2, { repair_details: { ...details, customer_charge: 100 } })])[0].changes, []);
});
test('distinguishes free service, undecided charges, and expense cancellation', () => {
    const result = timeline([event(1), event(2, { repair_details: { ...details, billing_status: 'free', customer_charge: 0 } }), event(3, { repair_details: { ...details, billing_status: 'pending', customer_charge: null, activity: { type: 'expense_cancelled' } } })]);
    assert.equal(result[0].title, 'Incorrect expense cancelled');
    assert.deepEqual(result[0].changes, [{ label: 'Customer charge', value: 'Free of charge → Not decided yet' }]);
    assert.deepEqual(result[1].changes, [{ label: 'Customer charge', value: '100 MMK → Free of charge' }]);
});
