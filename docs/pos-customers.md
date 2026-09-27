# POS customers

The customer picker shows name and phone, e.g. `Mg Mg - 093223`, with any customer-group discount retained. Search accepts a partial name, phone number or email (case-insensitive). Clearing the selection uses Walk-in Customer. Search does not change the selected customer until an option is chosen.

Customer creation and editing reject duplicate phone strings, including numbers on archived customers. Editing a customer's own unchanged number is allowed; multiple customers may have no phone. Numbers retain leading zeroes. This does not normalize country codes or differently formatted numbers.

Deploy with `php artisan migrate --force` and `npm run build`. The new phone unique index also protects against simultaneous duplicate inserts. If existing duplicate phone strings are found, the migration stops before changing any customer data. Review and correct those records, including archived customers, then rerun the migration; no records are automatically merged or deleted.

POS Order type is a reporting label only. Choosing Preorder still uses ordinary POS payment and inventory rules and does not create a Pre Order record. The separate Pre Order menu tracks items awaiting stock and deposits; its reservation mode holds an existing stock unit. These workflows are not automatically linked.
