# Sales and Strap Order workbook columns

Reference: `Sample Excel (2).xlsx`, Sales!A1:AB1 (28 columns) and 'Strap Order'!A1:V1 (22 columns). The Expense and Service sheets are outside this change.

## Comparison and implementation

| Sample column | Sales mapping | Strap Order / pre-order mapping |
| --- | --- | --- |
| No | Existing order ID | Existing pre-order ID |
| Fb Acc | Existing customer.source_details (profile name/link or source notes) | Same customer field |
| Customer Name | Existing linked customer name | Existing linked customer name |
| Customer Gender | **New** customer.gender | Not in Strap Order template |
| Address / Phone | Existing customer fields | Existing customer fields |
| Seller | Existing order creator | Not in Strap Order template |
| Brand | Existing product brand | Existing selected brand |
| Model No. | Existing product model | **New** optional model_number, with linked product model fallback |
| Watch Gender | Existing product gender | Not in Strap Order template |
| Order Date | **New** optional order_date, defaulting to creation date when blank | Same |
| Order Type | **New** order_type: Instock / Preorder | Existing type: Preorder / Reservation |
| Buying Type | **New** buying_type: Online / Inperson | Same |
| Paid by | Derived from existing split-payment methods (legacy payment_method fallback) | paid_by; pre-orders have no split-payment entries |
| Payment Type | Derived Cash / Mbanking / Card / Other / Split; zero-total orders are FOC | Separate classification for pre-orders |
| Pre-Order Payment | Blank: existing orders do not identify an earlier pre-order payment separately | Not in Strap Order template |
| Price | Existing saved line prices × quantities, summed once per order | **New** optional quoted price |
| Discount | Existing percentage, exported as an Excel percentage (5% = 0.05) | **New** discount_amount in MMK, matching the sample's 8,000 discount |
| Deposit | Existing money_transfer_amount, used as the transfer/deposit reference | deposit_amount (initial deposit, separate from cumulative amount_paid) |
| Opening | Calculated final price minus actual payments retained, minimum 0 | Calculated price minus discount minus deposit, minimum 0 |
| Total Payment | Existing total_amount (price after discount) | Calculated price minus discount |
| Delivery Type | **New** delivery_type | Same |
| Delivery ID | Existing delivery_code | **New** delivery_code |
| Delivery Status | **New** delivery_status | Same |
| Delivery Fees | **New** delivery_fees | Same |
| Received Money | Existing payment entries / amount_paid, excluding returned cash change | money_transfer_amount |
| Marketing Channel | Selected order.marketing_channel; no customer fallback | Stored on pre-order; not in Strap Order template |
| Remark | Existing remark | Existing remark, not included because it is absent from the Strap Order template |

## Meaning of amounts

Total Payment is the agreed price after discount. Sales uses the existing payment entries for Received Money and the remaining balance (Opening), deducting any cash change. Deposit reuses money_transfer_amount as a reference and is never added to receipts or subtracted from the balance a second time. Missing receipts leave Received Money and Opening blank; an explicit zero remains numeric zero. No extra order payment fields are stored.

Pre-orders retain their own payment description and initial deposit because they have no split-payment records. Their sample row with price 83,000 and deposit 30,000 exports Opening 53,000 and Total Payment 83,000. Actual cumulative receipts remain in amount_paid. These reference fields never automatically add payments or stock. Delivery fees remain separate from the invoice total. Existing data cannot reliably identify COD from a payment method alone, so Sales derives payment type from actual methods rather than guessing COD.

## Using the fields and exports

- Delivery type: orders and pre-orders share the `DeliveryType` PHP enum (Courier, Shop Pickup), with Not specified available. Dropdown options and server validation use the enum; storage remains a nullable string. Older custom values remain readable/exportable, but must be replaced with a listed type or cleared when editing. No automatic delivery-fee or stock changes are applied.
- Delivery status: orders and pre-orders share the `DeliveryStatus` PHP enum (Pending, Preparing, Dispatched, Delivered, Returned, Cancelled). Dropdown options and server validation use that enum; the database stays a nullable string. Older free-text values remain readable/exportable, but must be replaced with a listed status or cleared when editing. Delivery status does not automatically change order status, payments, or stock.
- Customers: use the existing Source and Source Details fields for profile information. No separate Facebook account field is needed. Customer gender is optional. Customer profile fields are current values, not historical order snapshots. The template's Fb Acc header is retained and displays Source Details for any source, not only Facebook.
- POS checkout/Edit Order: expand **Sales & delivery details (optional)** for date, order type, buying type, marketing channel, and delivery information. No Paid by, Payment type, Deposit, or Pre-order payment inputs are added to orders. Existing payment entries and money transfer amount supply payment columns in the export.
- Marketing channel uses the shared `MarketingChannel` PHP enum and dropdown on orders and pre-orders: Walk-in, Facebook, Instagram, TikTok, Google/Search, Website, Referral, Other, or Not specified. Storage remains a nullable string. Older custom values remain readable/exportable but must be replaced with a listed option or cleared when editing. It is never copied or backfilled from customer source. Blank values stay blank in the Sales export. The Strap Order export keeps the template's 22 columns, which do not include Marketing Channel.
- Pre Orders: expand the same section when creating/editing a record. An unlinked strap can use a selected brand and manually entered model number.
- Orders: **Export completed sales (Excel)** downloads a Sales worksheet with the exact 28 headers. It includes completed orders only and respects date/payment filters. Pending COD and cancelled orders are excluded. Date filters use order_date when present, otherwise creation date. Staff can export only their own orders.
- Pre Orders: **Export Strap Orders (Excel)** downloads the exact 22 headers for all records matching the applied filters, including reservations/cancelled records when the filters include them. Pre-orders remain shared among operational staff.

Sales exports use one row per order so monetary totals are never repeated for multi-item baskets. Brand, model and watch gender list the products in that basket; repeated quantities are shown beside the model, e.g. `AR11119 (x2)`. Price is the basket subtotal, not a single unit's price. Pre-order exports use one row per record.

Exports are XLSX downloads streamed by the web request, with frozen header rows, numeric amounts, percentage/date formatting, and text identifiers preserving leading zeros. Empty results still include headers. No additional queue worker is required. Existing records are not backfilled with guessed Facebook identities, genders, deposits, delivery status, or quoted prices.

## Deployment

Run `php artisan migrate --force`, `php artisan optimize:clear`, and `npm run build` after deploying. Restart long-running queue workers with `php artisan queue:restart` as usual. The reference workbook is unchanged.

Validation covers header ordering, saved-field round trips, partial/COD payments, exported calculations, multi-item totals, leading-zero IDs, literal spreadsheet text, empty exports, filter behavior, staff ownership, and stock preservation.
