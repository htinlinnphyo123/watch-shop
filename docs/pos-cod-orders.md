# POS COD orders and cancellation

Apply the additive migration with `php artisan migrate` and build assets with `npm run build`.

At POS checkout, select **Pending / Cash on delivery (COD)** to save an order with zero or partial payment. Completed sales still require full payment. New pending orders saved in POS reserve their selected units immediately. Each unit is linked to its order line and marked reserved, so it is excluded from other sales and cannot be deleted or manually released.

Scanned system codes stay attached to the pending order. Approval marks those exact reserved units sold; it does not substitute other available stock. Quantity-based cart lines reserve the available units selected at save time. Editing an order releases and reallocates its units inside one transaction; a failed edit preserves the original hold.

Older pending orders and online orders without `stock_reserved_at` do not yet have a unit allocation. The migration intentionally does not guess system codes. Open Edit Order, select the intended codes, and save to establish a reservation. Such unallocated orders retain their existing allocate-on-approval behavior until edited. A completed sale is still marked sold; the Preorder reporting label alone does not change order status.

Delivery code, remark, and money transfer amount are optional and editable through Edit Order. Money transfer amount records the transfer/deposit for reference and supplies the Sales export's Deposit column; it does not add a second payment. Record actual receipts in the payment entries to update amount received and balance due. Approval changes the status to completed and marks reserved stock sold (or allocates available stock for an older unallocated order); it does not record payment.

Cancel Order preserves order lines, amounts, attachments, and history. Completed orders return their linked sold units to available inventory. Reserved pending orders release their linked held units. Unallocated older pending orders have no stock to return. Cancellation is safe to repeat and invalidates open edit forms. Incomplete or changed sold/reserved-unit records block editing, approval, and cancellation rather than substituting another unit. Watch models with reserved units also cannot be deleted. Refunds are handled separately.

Customers already have an optional address field in the database, editor, customer list, and order invoice. The editor now explicitly labels it optional.

Regression checks: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter='PendingOrderStockTest|OrderEditTest|OrderAuditTest|ReservationTest' --do-not-cache-result`.
