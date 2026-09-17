# Watch accessories

Apply the database migration with `php artisan migrate` and build assets with `npm run build` before using this feature. Existing products default to `watch`. The migration does not modify their stock. It seeds Watch straps and Watch boxes with suggested fields. No HLP/NBL feature restriction is applied; these names represent team assignments.

Administrators use `/admin/accessories` to create and edit accessories, manage type names and suggested fields, upload images, set active/public status, and add or remove available stock. Staff can sell accessories through the POS, with All products / Watches / Accessories filters. The admin inventory view includes name/SKU search, type/status/stock filters, stock totals and a separate accessory types tab. Inactive accessories are excluded from new POS sales; turning public visibility off only hides them from the website.

Each sellable size/color combination is a separate product with its own price, barcode and inventory. For example, Leather strap 20 mm / Black and Leather strap 22 mm / Brown are separate entries. Type fields are suggestions, and individual accessories can have additional name/value attributes. Changing a template does not rewrite existing attributes. Attributes are descriptive text, not typed filter definitions.

Accessories use the existing products, product_items and order_items tables, with a product kind discriminator. This preserves mixed carts, stock locking, order edits, payments and reporting. No serial numbers are required. Stock removal only removes available units; sold/reserved records are preserved. Deactivate an accessory to retire it.

This application is an admin panel and API only; there is no public `/accessories` page. The separately hosted storefront can integrate `GET /api/v1/spa/accessories` (search and type query parameters, paginated) and `GET /api/v1/spa/accessories/{id}`. API responses include descriptions, image URLs, prices, attributes and available stock. The external storefront repository is not included here; its navigation and page integration must be deployed there. The existing public watch catalog excludes accessories.

Accessory uploads and product gallery uploads explicitly use Laravel's `s3` filesystem disk, backed by **DigitalOcean Spaces**. Here, “filesystem disk” means a named storage configuration, not local storage. Image URLs in the accessory API and admin/POS views also use the `s3` disk. Failed Spaces writes throw an error instead of saving an invalid image path. No local image fallback or storage symlink is used for these uploads.

Deployment configuration:

```dotenv
FILESYSTEM_DISK=s3
AWS_DEFAULT_REGION=sgp1
AWS_BUCKET=timeonyouwatchgallery
AWS_ENDPOINT=https://sgp1.digitaloceanspaces.com
AWS_URL=https://timeonyouwatchgallery.sgp1.digitaloceanspaces.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Set `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` to the DigitalOcean Spaces credentials through deployment secrets. The `AWS_*` names are used because Spaces supports the S3 API; the storage provider is DigitalOcean. Refresh cached configuration after deployment with `php artisan config:cache`. Existing stored files are not moved by this change.

Accessory image limits: 10 files, 5 MB each, JPEG/PNG/WebP.

Validation: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter='AccessoryTest|OrderEditTest'` and `npm run build`. The stock/checkout tests use an isolated in-memory database; they do not migrate the configured application database.

## Upgrading an existing installation

The upgrade adds accessory types and three product columns. Existing products receive `kind=watch`; existing IDs, brand links, prices, image keys, stock units, orders and order lines are preserved. Making brand optional allows accessories without a watch brand. Existing Spaces images need no copying if production retains the same S3 endpoint, bucket and public URL. Files previously stored in a different bucket or locally are not migrated automatically.

Back up the production database and test the release against a staging copy first. Deploy the built assets and run `php artisan migrate --force` before serving requests with the new code, using a maintenance window or the existing deployment process. Refresh configuration with `php artisan config:cache`. Do not run `migrate:fresh` or `migrate:refresh` on the running application. Once accessories exist, a rollback requires planning: older code does not understand accessory records, and dropping the new columns removes their type/attribute metadata.

The isolated `AccessoryMigrationTest` creates pre-upgrade watch, stock, order and image-path records, runs the upgrade, and compares their persisted fields. It also checks that the existing watch remains available through its API endpoint. This was verified with SQLite; the live PostgreSQL database has not been migrated or tested. The public watch detail endpoint now enforces the same active/public flags as the listing, so inactive or private watch URLs return 404.


## Barcode labels and POS scanning

Accessory SKU/barcodes are generated when left blank on creation. Editing without a barcode preserves the existing value. Each newly added stock unit also receives its own unique 12-digit system code, using the same generator as watch stock.

Use the printer action beside an accessory in the inventory table to preview and print Code 128 labels for its available units. For older accessory stock, this action generates only missing codes; existing codes stay unchanged. Sold and reserved stock is excluded. This requires no additional database migration.

Attach one label to each matching stock unit. In POS, focus the search/scan input and scan with a barcode reader (Enter suffix), or use Scan with Camera. A unit label adds that exact available unit to the cart. Duplicate scans of that unit do not add it twice. Sold, reserved and inactive accessory units cannot be scanned into a new sale. Scanning the accessory's SKU/barcode instead opens the quantity picker.
