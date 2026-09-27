export const customerLabel = (customer) => customer
    ? `${customer.name}${customer.phone ? ` - ${customer.phone}` : ''}`
    : 'Walk-in Customer';

export const customerOptions = (customers) => [
    { value: '', label: 'Walk-in Customer' },
    ...customers.map(customer => ({
        value: customer.id,
        label: customerLabel(customer) + (customer.group ? ` (${customer.group.name} – ${customer.group.percentage}%)` : ''),
        searchText: [customer.name, customer.phone, customer.email].filter(Boolean).join(' '),
    })),
];

export const filterSelectOptions = (options, query) => {
    const text = query.trim().toLowerCase();
    return text ? options.filter(option => `${option.label} ${option.searchText || ''}`.toLowerCase().includes(text)) : options;
};
