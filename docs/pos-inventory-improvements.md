# POS and inventory reliability improvements

## Setup

Apply `php artisan migrate` and build the frontend with `npm run build`. Restart long-running queue workers after deployment so watch imports load the shared validation rules. The checkout retry migration adds two nullable fields and a unique cashier/request index. The reservation migration adds a nullable reservation timestamp; existing orders are preserved.

## Pages to test

### POS — `/pos`

- Paste a full available system barcode without Enter. The result identifies the exact unit. “Add this watch” adds it directly; repeated selection shows “Already in cart.” Enter and camera scans use the same exact-unit path.
- Search a watch name or partial code. The normal quantity/unit picker remains available. A unit with a manufacturer serial can also be found by its system barcode in the picker.
- Open and close the unit picker. If stock changes in another session, reopening the picker fetches the current units. Checkout revalidates stock; a conflict identifies the affected cart line and preserves the cart.
- Choose a customer group with a watch-specific discount. That discount overrides the group default for that watch, including an explicit 0%. Watches without an override use the group default. Walk-ins use product discounts. The order's default discount is weighted by line value and rounded to two decimal places; a cashier's manual percentage remains supported. Existing order edits retain their saved percentage until staff change it.
- Choose Pending / Reserve stock / COD at checkout and save. The selected system codes must become reserved, disappear from normal POS availability, and be protected from deletion. Approval sells the same codes; cancellation releases them. The separate reservation page also continues to hold specific watches.
- Add a cart item, then refresh or click a navigation link away from POS. Confirm that the unsaved-cart warning appears. Successful checkout navigates without that warning.

Checkout sends one UUID per sale attempt. Repeating the same request as the same cashier returns the saved order instead of allocating more stock. A changed payload with an already-used key is rejected with the original order number. A rolled-back attempt can be corrected and retried. This protects retries from the updated POS; legacy requests without a key remain supported. Refreshing starts a new attempt, so review Orders if a response was lost before rebuilding a cart.

### Watches — `/products`

- Search by a unit's system barcode or manufacturer serial as well as watch name/model/product barcode.
- Create or edit a watch: negative prices, negative or excessive warranty months, and duplicate product barcodes must produce field errors. The same price, warranty, and barcode validation is used by spreadsheet imports.
- Set a watch-specific customer-group discount and verify it in POS. The editor explains the precedence.
- Watch “Active on website” and “Public on website” labels describe the existing visibility settings. Available watches remain sellable in POS regardless of website visibility.

### Watch stock — Watches → open a watch (`/products/{id}`)

- “Edit watch” opens the existing editor, including the correct categories and group discounts.
- Add several stock units. All are available and have unique 12-digit system barcodes. A failure rolls back the entire batch rather than leaving a partially-added batch.
- Assigned system barcodes are read-only and cannot be changed through the API. A missing code may be filled with a unique 12-digit numeric value.
- Sold units and units reserved by an order cannot be manually released or deleted. Watch models with reserved units cannot be deleted either. Serial/purchase-date corrections remain possible. Use the sale order for cancellation and the reservation page for reservation changes.
- New stock cannot be created as sold or reserved. Available stock can still be marked damaged/lost/returned and restored to available when it has no sale or active/completed reservation link. Only available units without a sale/reservation link can be removed.

### Orders — `/orders`

Use the existing Edit Order, cancellation, and approval actions. New pending POS orders reserve their linked units; cancellation releases the reserved or sold stock. Edits replace a reservation atomically and approval sells the same unit codes. The order page shows the reserved system codes.

Older pending orders did not retain exact unit codes. For these, Edit Order → choose the intended system codes → save once to reserve them. Orders without a reservation timestamp still allocate on approval, including online orders. No historical codes are guessed or retroactively assigned by the migration.

## Automated checks

`DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter='PendingOrderStockTest|PosInventoryHardeningTest|OrderEditTest|ReservationTest|PosPriceRoundingTest|StockLabelTest|WatchSpreadsheetTest|AccessoryTest|StaffAccessTest' --do-not-cache-result`

`node --test tests/Frontend/*.test.js tests/js/*.test.mjs`

The inventory tests include a deliberately failed second stock insert to verify batch rollback, checkout replay and changed-payload rejection, sold-unit protection, and product-specific discount precedence. In-memory tests do not simulate simultaneous PostgreSQL connections.
