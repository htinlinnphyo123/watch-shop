import { posPriceMmk } from './posPricing.js';

export const stockLabelDetails = (item, product, settings = {}) => {
    const watch = item.product || product;
    const price = item.price_mmk ?? posPriceMmk(watch, settings);
    return {
        ...item,
        name: watch?.name || '',
        model_number: watch?.model_number || '',
        price_mmk: price,
        price_label: `${Number(price).toLocaleString('en-US', { maximumFractionDigits: 2 })} MMK`,
    };
};
