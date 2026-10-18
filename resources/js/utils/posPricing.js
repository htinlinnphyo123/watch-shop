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

// Round the final order total once, after all quantities and line prices.
export const orderDiscountAmount = (subtotal, percentage) =>
    Math.round(Number(subtotal) * Number(percentage) / 100 * 100) / 100;

export const posOrderTotal = (subtotal, percentage) => {
    const total = Math.round((Number(subtotal) - orderDiscountAmount(subtotal, percentage)) * 100) / 100;
    return Math.round(total / 1000) * 1000;
};

export const posLinePriceSource = line => line.price_source ?? line.product.saved_pricing?.price_source ?? 'shop';
export const posLinePrices = (line, settings = {}) => line.product.saved_pricing ?? {
    shop_price: posPriceMmk(line.product, settings),
    web_price: Number(line.product.web_price) > 0
        ? posPriceMmk({ ...line.product, pos_price_mmk: undefined, price: line.product.web_price }, settings) : null,
};
export const posLinePrice = (line, settings = {}) => Number(posLinePrices(line, settings)[`${posLinePriceSource(line)}_price`]) || 0;
// Quote the normal shop selling price against the selected original price.
export const posLineAutoDiscountPercentage = (line, group = null, settings = {}) => {
    const original = posLinePrice(line, settings);
    const shop = Number(posLinePrices(line, settings).shop_price) || 0;
    const shopDiscount = posDiscountPercentage(line.product, group);
    if (original <= 0) return 0;
    const sellingPrice = shop - orderDiscountAmount(shop, shopDiscount);
    return Math.round(Math.max(0, Math.min(100, (original - sellingPrice) / original * 100)) * 100) / 100;
};
export const posLineDiscountPercentage = (line, group = null, settings = {}) => {
    const saved = line.product.saved_pricing;
    return Number(line.discount_percentage ?? (saved && posLinePriceSource(line) === saved.price_source ? saved.discount_percentage : null)
        ?? posLineAutoDiscountPercentage(line, group, settings));
};
export const setPosLinePriceSource = (line, source, group = null, settings = {}) => {
    line.price_source = source;
    line.discount_percentage = posLineAutoDiscountPercentage(line, group, settings);
};
// Round the discount per unit, so splitting a quantity into separate units keeps the same total.
export const lineDiscountAmount = (price, quantity, percentage) => orderDiscountAmount(price, percentage) * Number(quantity);
