# POS converted prices

New POS unit prices converted from USD or another foreign currency to MMK are rounded to the nearest 1,000 MMK. Exactly 500 rounds upward: 214,524 → 215,000; 214,232 → 214,000; 214,500 → 215,000.

Rounding happens per unit after currency conversion and before multiplying quantity or applying discounts. The browser and server use the same rule. The rounded unit price is saved on the order line, so the order page, printed invoice, and sales export agree with POS. Payment amounts and any discount-adjusted final total retain their existing precision; they are not rounded a second time.

Prices entered directly in MMK remain unchanged. Existing order lines keep their original agreed prices when edited, even after exchange rates change. New lines added to an existing order use the new conversion rule. Pending orders keep their saved prices on approval. No historical records are rewritten.
