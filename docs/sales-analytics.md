# Sales Analytics

Open **Sales & Operations → Sales Analytics**. All authenticated staff can access it, matching access to Orders and Order Summary. No cost prices or profit figures are exposed.

The default period is the last 30 days, including today. Date shortcuts cover today, the last 7 or 30 days, and this month. Custom ranges support up to 366 inclusive dates and cannot end in the future.

## Definitions

- **Total sales:** sum of final `orders.total_amount` for orders currently marked completed, in MMK after discounts. Guest orders are included. This is not payment receipts or profit.
- **Completed orders:** number of matching orders, counted once regardless of how many product lines they contain.
- **Items sold:** sum of order-line quantities on those completed orders. Today's stock availability or product price does not change this count.
- **Average order value:** total sales divided by completed orders, rounded to two decimal places; zero when there are no completed orders.
- **Previous period:** immediately preceding the selected range, with the same number of days. Percentage change is unavailable when previous sales are zero. A period containing today is incomplete until the day ends.
- **Date basis:** order creation timestamps, including both chosen dates in the application's timezone. Existing orders have no completion timestamp; this is not a report by approval date.

Daily chart totals reconcile with total sales. Best-selling products are the top five by units, then completed-order count, then product ID. Product type totals include all units, even if the product is no longer available. Soft-deleted products remain visible historically. Names and types come from current product records. The status panel counts all orders in the range; only its completed category contributes to sales.

Exact daily values can be expanded below the chart. Recent sales link to their invoices, and a link to Order Summary provides the separate payment report with the same date filters. The page uses the existing order reporting index and requires no new migrations.

Verification: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter=SalesAnalyticsTest` (tests explicitly refuse a persistent database).
