# Watch spreadsheet imports

Watch imports run on Laravel's database queue, even when `QUEUE_CONNECTION=sync` is configured. The upload request saves a private temporary copy, creates a visible import status, and enqueues `ImportWatchesFromSpreadsheet`. The product page polls the status and displays completion or spreadsheet/server errors. A spreadsheet with row errors is rolled back as a whole.

After deploying, apply migrations and run a long-lived queue worker on the same application host as the web process (the temporary upload is stored on Laravel's private local disk):

```sh
php artisan migrate --force
php artisan queue:work watch-imports --tries=1 --timeout=900 --memory=256
```

Keep that worker running under the server's process manager (for example Supervisor or systemd) and restart it after deployments with `php artisan queue:restart`. The dedicated queue connection's `retry_after` is 960 seconds, longer than the job's 900-second timeout, so a slow import is not reserved by two workers at once.

## Supervisor production setup

The repository includes a ready-to-copy config at `deploy/supervisor/watch-shop-import-worker.conf`. Edit the PHP binary, application directory, and worker user if your server uses different paths, then install and start it:

```sh
sudo mkdir -p /var/log/supervisor
sudo cp deploy/supervisor/watch-shop-import-worker.conf /etc/supervisor/conf.d/watch-shop-import-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start watch-shop-import-worker:*
sudo supervisorctl status watch-shop-import-worker:*
```

After each deployment, from the application directory run:

```sh
php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart
sudo supervisorctl restart watch-shop-import-worker:*
```

The worker must run on the same host as the web process because the uploaded spreadsheet is stored on Laravel's private local disk. The current Excel export is streamed directly by the web request and does not create a queued job, so Supervisor is required for imports only. If exports later become queued, use a second queue and Supervisor program with a larger timeout appropriate to the export size.

The importer reads XLSX/CSV rows as a stream and accepts files up to 10 MB, 5,000 data rows, and 2,000 added stock units. If there is no running worker, the page clearly stays at “Queued”; failed validation and unexpected worker errors are recorded against the import for the admin to see. Failed jobs also go into Laravel's `failed_jobs` table for operator review.

If the app runs multiple web/worker instances, configure a shared private upload disk for these temporary files before running workers on separate hosts. The current private local disk is appropriate when web and worker processes share a host.
