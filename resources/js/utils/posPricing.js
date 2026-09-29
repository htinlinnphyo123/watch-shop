// Keep conversion/rounding aligned with App\Support\PosPrice.
export const posPriceMmk = (product, settings = {}) => {
    if (!product) return 0;
    // Saved order lines retain their agreed price when the order is edited.
    if (product.pos_price_mmk !== undefined) return Number(product.pos_price_mmk);
    const price = Number(product.price);
    if (!product.currency || product.currency === 'MMK') return price;
    const rate = Number(settings[`${product.currency.toLowerCase()}_rate`] ?? 1);
    return Math.round(price * rate / 1000) * 1000;
};

// Product-specific group override > group default; walk-ins use product discount.
export const posDiscountPercentage = (product, group = null) => {
    if (!group) return Number(product.discount) || 0;
    const override = product.customer_groups?.find(candidate => String(candidate.id) === String(group.id));
    return Number(override?.pivot?.percentage ?? group.percentage) || 0;
};
