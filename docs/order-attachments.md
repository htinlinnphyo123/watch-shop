# Order attachments

Order Details provides a multiple-file `file_upload[]` input for payment slips and supporting documents. Uploads do not change order totals or stock. Attachments are excluded from the printed invoice.

## Storage

The `order_attachments` disk uses the existing AWS credentials, region and endpoint. Set `ORDER_ATTACHMENTS_BUCKET` to use a separate private bucket; otherwise it uses `AWS_BUCKET`. Ensure the bucket policy does not allow public reads of `order-attachments/*`. Product images may remain public under their existing configuration.

The storage bucket must allow browser CORS requests from the deployed shop origin. Merge these permissions into existing bucket CORS rules rather than replacing product-upload rules:

```json
[
  {
    "AllowedOrigins": ["https://your-shop.example", "http://localhost:8000"],
    "AllowedMethods": ["PUT", "GET", "HEAD"],
    "AllowedHeaders": ["*"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3600
  }
]
```

Replace the example origin with the actual shop origin. Only include localhost when needed for development. The storage credentials need PutObject, GetObject and metadata/HeadObject access to the attachment prefix.

## Flow

1. The browser requests a ten-minute `temporaryUploadUrl` with the filename and byte count.
2. Laravel reserves an attachment record and generates a random storage key tied to the order and uploading user.
3. The browser sends a PUT directly to the returned storage URL, using the returned headers. It checks the storage HTTP status before attempting completion.
4. Laravel checks existence and byte count using storage metadata, then marks the attachment uploaded. No file contents pass through PHP.
5. Authenticated staff download through a five-minute temporary URL. Raster-image thumbnails also use temporary URLs. Other files are served as downloads.

Limits: 20 completed/active uploads per order, 20 MB per file. Allowed extensions: JPG, JPEG, PNG, GIF, WebP, HEIC, HEIF, PDF, DOC, DOCX, XLS, XLSX, CSV, TXT, ZIP. Validation uses metadata; it is not a malware or content scan. Incomplete reservations expire after ten minutes and no longer count against the limit. Abandoned objects are not automatically deleted; pending records retain their keys for storage cleanup.

Test with `XDEBUG_MODE=off DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter=OrderAttachmentTest`. A real browser-to-bucket test is still needed to validate deployed bucket CORS and permissions.
