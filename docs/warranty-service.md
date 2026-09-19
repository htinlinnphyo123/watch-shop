# Warranty eligibility and service history

Admins open **Repairs & Service** in the sidebar, or click **Service** on an individual watch's stock row. Other roles cannot access lookup, intake, case details, or update endpoints.

Search by the full unit barcode, manufacturer serial number, or invoice number. Invoice searches show up to 25 units; use the unit barcode to narrow larger orders. Accessories cannot be registered as watch service cases.

## Date eligibility

The checker uses the unit's linked **completed sales order creation date** as the customer purchase reference. The inventory `purchase_date` is intentionally not used: that date is entered when stock is acquired. Existing orders do not have a separate completed-at timestamp, so invoices approved later still use their original creation date.

Warranty duration and type come from the current watch product settings. The end date is the purchase date plus the warranty period in calendar months, clamped to the last valid day of the month (for example, January 31 + one month = February 28). Coverage includes the end date. A date within the warranty period is only a time-based check, not approval for a particular fault.

Missing or incomplete sales proof, unsold/returned/archived stock, and absent warranty terms require review. A zero warranty period means no warranty. Customer details are taken from the linked order where available; guest cases require a service contact name. The contact phone and extra notes can record purchase-proof details for manual review.

## Service records

At intake, record the contact, service issue date, reported issue and optional notes. The initial status is **Watch Received**. Warranty jobs start with coverage **Needs review**; general repairs start with **Not a warranty claim**. Issue dates cannot be in the future or earlier than a verified purchase date. The automatic check is evaluated on the issue date and saved permanently as an intake snapshot, including watch identity, purchaser, invoice, dates and warranty terms.

Admins can update the status to **Under Inspection**, **Sent to Company**, **Repair in Progress**, **Ready for Collection**, or **Completed**. Changes may skip steps where appropriate; each requires an update note. Warranty approval or rejection also requires a reason, allowing documented exceptions for cases with missing proof or expired date checks.

Every update creates an immutable event recording the status, warranty decision, reason, notes, administrator and timestamp. Intake snapshots remain unchanged when product settings, invoices or customer records later change. The detail page separately shows today's date check.

A watch can have only one open service record at a time. Completed cases can be reopened only when no other case is open. Stale updates are rejected so one admin cannot silently overwrite another admin's work. Service records do not change inventory or sales status and have no deletion endpoint.

Apply the new tables with `php artisan migrate`. No existing customer, watch or sales records are changed by this migration.

Focused verification: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter=WatchServiceTest`.

## General repair support

The sidebar is now **Repairs & Service**. Choose a system watch, or **Outside watch / walk-in repair**. Outside watch descriptions, brand, model and serial are saved on the job without adding inventory products or stock. The service contact name and phone identify the person bringing the watch; they do not create or change a customer master record. Search jobs by contact, phone, watch description, serial or job ID.

New jobs default to general repair, with warranty marked not applicable; warranty review remains available. Existing warranty records are retained. Fault categories cover handling/accidental damage, mechanical/movement faults, manufacturing defects, maintenance, other faults and undetermined faults. Diagnosis is recorded separately from the original reported issue.

Jobs can be repaired in-house or outsourced. External work records the shop/company name, phone, reference and instructions. Additional statuses include awaiting customer approval, sent to another shop, external repair in progress, received back from the shop, and cancelled. External transfer statuses require an external provider with a name. Completed and cancelled jobs are closed. Only linked inventory units have the automatic one-open-job restriction; outside watch serial/contact search can find related visits.

### Charges and expenses (MMK)

- Billing can be undecided, free, or chargeable. Undecided is not treated as zero. Free services store a zero charge. Chargeable jobs require a positive amount and a description. Free service also requires a reason.
- Expenses are individual dated records with an amount, description and optional payee. They can cover parts, other-shop fees, transport and other repair costs. Dates cannot predate the service issue date or be in the future.
- Expense retries reuse a request UUID to prevent double recording. Incorrect expenses can be voided with a reason; they remain visible and are removed from active expense totals. A new corrected entry can then be added.
- The job shows customer charge, active expense total, and expected margin (charge minus expenses). A free job with expenses therefore has a negative expected margin.
- Charges are amounts to bill, not payment collections; no payment receipt is recorded here. These records do not post to the wallet, POS orders, sales analytics or customer purchase leaderboard. This avoids counting quotes or unpaid repair charges as completed product sales.
- Every diagnosis, provider, charge or status update stores its repair-details snapshot in the timeline. Expense additions and voids also create history entries.

Run `php artisan migrate` to apply the additive repair migration. It makes the stock-unit link optional and adds repair details and expense records. Existing service records and their timelines are preserved. The migration refuses rollback while outside-watch jobs exist.

Repair verification: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter='WatchRepairTest|WatchServiceTest'`.
