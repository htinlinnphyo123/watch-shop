export const normalizeCustomerPhone = value => {
    let phone = String(value || '').replace(/[\s().-]+/g, '');
    if (phone.startsWith('00')) phone = `+${phone.slice(2)}`;
    if (phone.startsWith('+959')) phone = `0${phone.slice(3)}`;
    return phone;
};

export const customerLabel = (customer) => customer
    ? `${customer.name}${customer.phone ? ` - ${customer.phone}` : ''}`
    : 'Walk-in Customer';

export const customerOptions = (customers) => [
    { value: '', label: 'Walk-in Customer' },
    ...customers.map(customer => ({
        value: customer.id,
        label: customerLabel(customer) + (customer.is_guest ? ' [Guest]' : '') + (customer.group ? ` (${customer.group.name} – ${customer.group.percentage}%)` : ''),
        phoneSearch: normalizeCustomerPhone(customer.phone),
        searchText: [customer.name, customer.phone, customer.email].filter(Boolean).join(' '),
    })),
];

export const filterSelectOptions = (options, query) => {
    const text = query.trim().toLowerCase();
    const phone = normalizeCustomerPhone(text);
    return text ? options.filter(option => `${option.label} ${option.searchText || ''}`.toLowerCase().includes(text)
        || (/^\+?[0-9]+$/.test(phone) && option.phoneSearch?.includes(phone))) : options;
};
