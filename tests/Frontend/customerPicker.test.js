import test from 'node:test';
import assert from 'node:assert/strict';
import { customerLabel, customerOptions, filterSelectOptions } from '../../resources/js/utils/customerPicker.js';

const customers = [
    { id: 1, name: 'Mg Mg', phone: '093223', email: 'mg@example.test', group: { name: 'VIP', percentage: 10 } },
    { id: 2, name: 'Aye Aye', phone: null, email: 'aye@example.test' },
];
const options = customerOptions(customers);

test('shows customer name and phone and retains group discount details', () => {
    assert.equal(customerLabel(customers[0]), 'Mg Mg - 093223');
    assert.equal(options[1].label, 'Mg Mg - 093223 (VIP – 10%)');
    assert.equal(options[1].value, 1);
    assert.equal(customerLabel(customers[1]), 'Aye Aye');
    assert.equal(customerLabel(undefined), 'Walk-in Customer');
    assert.equal(options[0].value, '');
});

test('finds customers by partial name, phone or email, ignoring case and surrounding spaces', () => {
    for (const query of [' mg ', '093223', '3223', 'MG@EXAMPLE.TEST']) {
        assert.deepEqual(filterSelectOptions(options, query).map(option => option.value), [1]);
    }
    assert.deepEqual(filterSelectOptions(options, 'aye@example').map(option => option.value), [2]);
    assert.deepEqual(filterSelectOptions(options, 'not found'), []);
    assert.deepEqual(filterSelectOptions(options, '  '), options);
});

test('keeps walk-in searchable and supports existing label-only staff options', () => {
    assert.deepEqual(filterSelectOptions(options, 'walk-in').map(option => option.value), ['']);
    assert.deepEqual(filterSelectOptions([{ value: 4, label: 'Staff Name' }], 'staff'), [{ value: 4, label: 'Staff Name' }]);
});
