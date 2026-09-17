<?php

return [
    'enabled' => env('TELEGRAM_BACKUP_ENABLED', false),
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'chat_id' => env('TELEGRAM_BACKUP_CHAT_ID', env('TELEGRAM_CHAT_ID')),
    'time' => env('TELEGRAM_BACKUP_TIME', '02:00'),
    'timezone' => env('TELEGRAM_BACKUP_TIMEZONE', 'Asia/Bangkok'),
    'connection' => env('TELEGRAM_BACKUP_CONNECTION'), // null uses the app's default connection
    'pg_dump_binary' => env('PG_DUMP_BINARY', 'pg_dump'),
    'dump_timeout' => 1800,
    'upload_timeout' => 300,
    'chunk_bytes' => 45 * 1024 * 1024,
    // Deliberately local and private, independent of the public S3 image disk.
    'directory' => storage_path('app/private/database-backups'),
    'retention_days' => (int) env('TELEGRAM_BACKUP_RETENTION_DAYS', 7),
];
