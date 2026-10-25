# POS COD orders and cancellation

Apply the additive migration with `php artisan migrate` and build assets with `npm run build`.

At POS checkout, select **Pending / Cash on delivery (COD)** to save an order with zero or partial payment. Completed sales still require full payment. New pending orders saved in POS reserve their selected units immediately. Each unit is linked to its order line and marked reserved, so it is excluded from other sales and cannot be deleted or manually released.

Scanned system codes stay attached to the pending order. Approval marks those exact reserved units sold; it does not substitute other available stock. Quantity-based cart lines reserve the available units selected at save time. Editing an order releases and reallocates its units inside one transaction; a failed edit preserves the original hold.

Older pending orders and online orders without `stock_reserved_at` do not yet have a unit allocation. The migration intentionally does not guess system codes. Open Edit Order, select the intended codes, and save to establish a reservation. Such unallocated orders retain their existing allocate-on-approval behavior until edited. A completed sale is still marked sold; the Preorder reporting label alone does not change order status.

Delivery code, remark, and money transfer amount are optional and editable through Edit Order. Money transfer amount records the transfer/deposit for reference and supplies the Sales export's Deposit column; it does not add a second payment. Record actual receipts in the payment entries to update amount received and balance due. Approval changes the status to completed and marks reserved stock sold (or allocates available stock for an older unallocated order); it does not record payment.

Cancel Order preserves order lines, amounts, attachments, and history. Completed orders return their linked sold units to available inventory. Reserved pending orders release their linked held units. Unallocated older pending orders have no stock to return. Cancellation is safe to repeat and invalidates open edit forms. Incomplete or changed sold/reserved-unit records block editing, approval, and cancellation rather than substituting another unit. Watch models with reserved units also cannot be deleted. Refunds are handled separately.

Customers already have an optional address field in the database, editor, customer list, and order invoice. The editor now explicitly labels it optional.

Regression checks: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter='PendingOrderStockTest|OrderEditTest|OrderAuditTest|ReservationTest' --do-not-cache-result`.

## Reserved orders in POS

Add watches and a customer to the normal POS cart. At checkout choose **Order type → Reserved**. This automatically saves a pending order and holds its exact stock units. Enter any payment received, including zero. The server enforces pending status for new Reserved orders even if a client sends completed status.

Manage these records in Orders (shown as Reserved while pending). Edit payments and details through Edit Order, approve to mark the held watches sold, or cancel with a reason to release them. Reserved orders use the normal order invoice and per-watch pricing. Approved reservations keep their order type for history; completed sales cannot be converted into new reservations by changing their type.

The separate POS reservation tab has been removed. Previous records in the reservation register remain accessible from the sidebar under Pre Orders. They are labeled as legacy reservations and are not automatically converted or duplicated into POS orders. New reservations and conversions from pre-orders are blocked in this register; use POS Order type: Reserved.

## Convert an arrived pre-order to POS

Apply `php artisan migrate` and build assets with `npm run build`. In **Pre Orders**, choose **Convert to POS Order** on an active pre-order. POS carries over the customer, delivery details, remarks, and **Total Amount Paid** as the deposit credit. It does not add the optional Deposit or Money Transfer fields to that amount: these may describe the same receipt. Check Total Amount Paid before conversion.

Select one arrived watch using its system code. The linked model must match; if no model was linked, choose a watch from the requested brand. The old quote is displayed for reference; review current POS original price and discount before checkout. The default is Reserved with zero additional payment. Choose Instock and completed status when completing the sale. Payment rows contain additional receipts only; the carried deposit is already deducted from the amount due and appears once on the invoice. A deposit above the new order total blocks conversion for review.

Saving links both records atomically and marks the pre-order Converted to POS. Repeated submission cannot create another order or reapply the deposit. Failed saves leave the original pre-order and stock unchanged. Converted source records become read-only; attachments remain on the original record. Use View POS Order to manage the sale and its payments. Its source customer and deposit credit remain fixed. Cancelling the POS order releases stock but does not reopen the pre-order or automatically refund money. Converted records are excluded from the pre-order workbook export to avoid reporting them again as outstanding pre-orders.
