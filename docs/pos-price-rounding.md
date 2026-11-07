# POS converted prices

New POS unit prices converted from USD or another foreign currency to MMK are rounded to the nearest 1,000 MMK. Exactly 500 rounds upward: 214,524 → 215,000; 214,232 → 214,000; 214,500 → 215,000.

Rounding happens per unit after currency conversion and before multiplying quantity or applying discounts. The browser and server use the same rule. The rounded unit price is saved on the order line, so the order page, printed invoice, and sales export agree with POS. Payment amounts and any discount-adjusted final total retain their existing precision; they are not rounded a second time.

Prices entered directly in MMK remain unchanged. Existing order lines keep their original agreed prices when edited, even after exchange rates change. New lines added to an existing order use the new conversion rule. Pending orders keep their saved prices on approval. No historical records are rewritten.

## Overall voucher discount

POS and Edit Order support **Overall Discount (%)**, from 0 to 100 with at most two decimal places. It is additional to each watch's own discount. Calculate it on the merchandise subtotal after watch discounts, before final nearest-1,000-Ks rounding. Delivery fees are excluded. Payments, deposit validation, order totals, and revenue summaries use the resulting discounted total. Customer invoices show one Overall Discount line; the existing watch prices and row discounts remain unchanged, and rounding is not printed.

The order stores `overall_discount_percentage` and `overall_discount_amount`. Each new/saved POS line stores its `overall_discount_amount`, `rounding_adjustment`, and `net_total`, preserving its original `price` and `discount_percentage`. Allocate equally per physical watch (quantity two receives two shares), capping the deduction at a watch's post-discount value and redistributing any excess. Cent remainders use stable line order. Allocate final order rounding separately so the sum of line net totals equals the saved merchandise total.

Excel retains its existing Price and Discount columns and adds **Overall Discount**, **Rounding Adjustment**, and **Net Sales** per physical watch. Order-level Total Payment and receipts still appear only on the first row. Net Sales is the merchandise selling value after discounts and rounding, not the cash collected so far. Converted/prepaid order deposit credits continue to be handled separately.

Existing orders receive zero overall discount without changing their amounts. Their new line allocation fields remain null and Excel allocation columns remain blank until edited/saved through POS; invoices keep their existing legacy discount layout. Apply `php artisan migrate` and rebuild assets when deploying.

Invoice item-row Total cells are displayed rounded to the nearest 1,000 Ks (500 rounds up). For example, 2,081,960 displays as 2,082,000 and 397,440 as 397,000. This is display-only: original prices, discounts, saved net allocations, Excel, and grand totals retain their existing calculations. Independently rounded displayed rows may not sum to the saved order total.
