# Stock barcode labels

Labels show the product name, model (when available), individual stock-unit barcode, and current selling price in MMK before checkout discounts. Foreign-currency prices use the same nearest-1,000 MMK rounding as POS; prices entered in MMK are preserved. This applies to existing individual/page watch labels and accessory labels through the shared label component.

On the Watch list, **Print all the labels** prepares one label per available watch stock unit across all pages and regardless of current filters, then opens the browser print dialog automatically. The browser still requires confirming the destination/printer. The preview remains available for reprinting if automatic printing is blocked by the browser.

Bulk watch printing excludes accessories, sold/reserved/returned/lost/damaged stock, deleted units, and deleted watches. Existing barcode values are retained, including leading zeroes. Available units missing codes receive generated unique codes; no new stock is created and stock statuses are not changed. Repeated printing reuses those codes. Admins and managers can use the bulk endpoint; staff cannot.

Printing uses the existing two-column layout with 60 mm barcode labels and dotted cut lines. Use A4 or Letter at 100% scale with browser headers and footers off. Prices and availability reflect the snapshot taken when labels are prepared; reprint after changing prices or exchange rates. A large inventory produces a correspondingly large print job.
