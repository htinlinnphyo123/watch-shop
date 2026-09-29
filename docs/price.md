# Pricing Logic

## POS customer-group discounts

For a customer with a group, each cart line uses the watch's specific group override if present (including 0%), otherwise the group's default percentage. Walk-in sales use each product's discount. POS combines these into a value-weighted order percentage rounded to two decimal places. A salesperson may still override that final percentage; existing order edits keep the saved percentage. The server and browser follow the same precedence.

## Public User / Login User (Internal User)

### Scenario 1: No Web Price
- **Original Price**: `100 Baht`
- **Show Price**: `price` (100 Baht)
- **Discount**: `10%`
- **Final Price**: `90 Baht`

### Scenario 2: Web Price Exists (e.g., 150 Baht)
- **Original Price**: `150 Baht`
- **Show Price**: `web_price ?? price` (150 Baht)
- **Final Price**: `90 Baht`
- **Calculated Discount**: `40%`
  - *Formula (when `web_price` exists)*: `((90 / 150) * 100) = 60%` 
  - *Resulting Discount*: `100% - 60% = 40%`

---

## Login Customer (Customer Model)
*(Note: `web_price` is not considered / စဉ်းစားစရာမလို)*

- **Original Price**: `100 Baht`
- **Discount**: Extracted from the `CustomerGroup` (e.g., `20%`)
- **Final Price**: `80 Baht`
