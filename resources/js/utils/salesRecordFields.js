export const recordDefaults = {
    order_date: '', buying_type: '', marketing_channel: '',
    delivery_type: '', delivery_status: '', delivery_fees: '',
};
export const salesDefaults = { ...recordDefaults, order_type: '' };
export const preOrderDefaults = { ...recordDefaults, model_number: '', price: '', discount_amount: '', delivery_code: '', money_transfer_amount: '', paid_by: '', payment_type: '', deposit_amount: '' };
export const recordValues = (record, defaults) => Object.fromEntries(Object.keys(defaults).map(key => [key,
    key === 'order_date' ? (record?.[key] || '').slice(0, 10) : record?.[key] ?? defaults[key],
]));
