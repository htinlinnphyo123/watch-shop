# Wallet expense categories and payment types

Wallet create/edit forms now have optional dropdowns:

- Category: Bill, Service, Shop, Delivery.
- Payment type: Cash, KBZ Pay, Card, CB Pay, Aya Pay, Other (matching POS terminology).

PHP enums supply both dropdown options and server validation. These are nullable string columns on wallet transactions; older records remain unclassified instead of being assigned guessed values. Editing can clear either field. These fields are descriptive only and do not change In/Out amounts, wallet balance calculations, or voucher storage.

The wallet records table displays both fields. **Export Excel (applied filters)** downloads all matching records, not only the visible page. It uses the same user/date filters and authorization scope as the table: admins can export all users or one selected user; every other role can export only its own wallet even if it supplies another user ID. Date bounds are inclusive. Both In and Out records are included and labeled.

The XLSX contains ID, User, Date, In / Out, Category, Payment Type, Amount, Currency, Balance After, Description, Recorded By, and Voucher filename. Category/payment values are exported as readable labels; missing values stay blank. Amounts and dates use typed Excel cells, and user-entered strings are literal text, not formulas. Balance After is the historical wallet balance after that record, not a new running balance for the filtered export. Vouchers themselves and private storage paths are not embedded. No queue configuration change is needed.

Deploy with `php artisan migrate --force`, `php artisan optimize:clear`, and `npm run build`. This adds two nullable columns without changing existing wallet records or balances.
