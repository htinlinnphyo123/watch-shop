# Order salespeople

Run `php artisan migrate` before deploying the updated application. The additive migration creates `order_salespeople`, with a unique order/user pair and a reverse lookup index, and copies each existing non-null `orders.user_id` into it. Orders without a creator remain unassigned. Rolling back drops these assignments; the original creator is retained.

POS checkout and Edit Order allow selecting one or more admin, manager, or staff users. The logged-in user is preselected for a new sale. `salesperson_ids` rejects empty, duplicate, invalid, or non-staff selections. For older clients, omitting the field defaults new orders to the operator and preserves assignments on edits. Updates share the existing stock/payment transaction, edit-version checks, and checkout retry protection.

`orders.user_id` remains the creator for ownership, access control, and checkout retries. Assignment does not grant order-edit access. The order list, invoice, Excel sales export, and audit history show all assigned salespeople. User deletion is blocked while sales assignments exist to preserve performance history.

Sales Analytics includes salesperson performance for the selected order-creation date range. Each assigned person receives the full discounted order total and one order credit for every completed order. Pending, processing, and cancelled orders are excluded. Shared sales therefore count for each participant, while shop totals count each order only once. Unassigned orders still count in shop totals but have no salesperson credit. Editing assignments changes attribution in current reports; audit history preserves the previous assignments.
