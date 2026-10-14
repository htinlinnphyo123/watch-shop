# Guest customers in POS

Deploy the new migration (`php artisan migrate`) before using this feature. Existing customers default to regular (`is_guest = false`). Email is now nullable so POS guests do not need a placeholder address. Migration rollback removes the guest flag but deliberately keeps nullable emails to preserve these records.

In POS, use **Add guest customer**. Name and phone are required. **More customer details** reveals optional email, gender, address, source, and source details. Optional fields are submitted only while the toggle is on. New guests automatically receive the default password `password`, stored as a hash and never returned. There is no password input, and submitted password overrides are ignored. IDs, timestamps, archival state, and the guest flag are managed by the system rather than entered by staff.

Saving creates a guest immediately and selects them for checkout. Cancelling the sale does not delete that customer. New guests always have a null customer group; submitted group overrides are ignored. Existing customers selected from matches retain their current group. Saving an order continues to use `customer_id`; no order or payment fields are duplicated.

POS includes both regular and guest customers. Phone matching removes spaces, parentheses, dots and hyphens, treats 00 as an international prefix, and matches +959 mobile numbers to Myanmar local 09 numbers. Legacy phone formatting is also checked. Matching phones or emails require staff to explicitly choose the existing record; nothing is merged or overwritten. Archived matches cannot be selected or promoted.

The Customers page hides guests by default. **Show guest customers** includes them. **Make regular customer** in POS, or **Make regular** in the list, changes only `is_guest` to false. It keeps the same customer ID, so past orders, invoices and reporting remain linked. Guest sales are not excluded from sales analytics. Normal customer creation does not offer or accept the guest flag.
