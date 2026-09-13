export const paymentMethods = [
    { value: 'cash', label: 'Cash' },
    { value: 'kbz_pay', label: 'KBZ Pay' },
    { value: 'card', label: 'Card' },
    { value: 'cb_pay', label: 'CB Pay' },
    { value: 'aya_pay', label: 'Aya Pay' },
    { value: 'other', label: 'Other' },
];

export const paymentMethodLabel = method =>
    paymentMethods.find(option => option.value === method)?.label || (method === 'split' ? 'Split Payment' : method || 'Cash');

export const paymentCents = amount => Math.round((Number(amount) || 0) * 100);
