# POS COD orders and cancellation

Apply the additive migration with `php artisan migrate` and build assets with `npm run build`.

At POS checkout, select **Pending / Cash on delivery (COD)** to save an order with zero or partial payment. Completed sales still require full payment. Pending orders follow the existing stock policy: units remain available until approval, so approval may fail if stock has sold meanwhile.

Delivery code, remark, and money transfer amount are optional and editable through Edit Order. Money transfer amount records the courier remittance for reference; it does not add a second payment. Record actual receipts in the payment entries to update amount received and balance due. Approval assigns stock and changes the status to completed; it does not record payment.

Cancel Order preserves order lines, amounts, attachments, and history. Completed orders return their linked sold units to available inventory. Pending orders have no stock to return. Cancellation is safe to repeat and invalidates open edit forms. Incomplete or changed sold-unit records block cancellation. Refunds are handled separately.

Customers already have an optional address field in the database, editor, customer list, and order invoice. The editor now explicitly labels it optional.

Regression checks: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter='OrderEditTest|OrderAuditTest' --do-not-cache-result`.
