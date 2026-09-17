<?php

namespace App\Console\Commands;

use App\Services\TelegramDatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SendDatabaseBackupToTelegram extends Command
{
    protected $signature = 'backup:telegram';

    protected $description = 'Create a PostgreSQL backup and upload it to the configured Telegram chat';

    public function handle(TelegramDatabaseBackupService $backup): int
    {
        if (! config('database_backup.enabled')) {
            $this->warn('Telegram backups are disabled. Set TELEGRAM_BACKUP_ENABLED=true to enable them.');

            return self::SUCCESS;
        }
        $lock = Cache::lock('database-backup:telegram', 21600);
        if (! $lock->get()) {
            $this->warn('A database backup is already running.');

            return self::SUCCESS;
        }
        $directory = null;
        $completeDump = false;
        $stage = 'configuration';
        $oldMask = umask(0077);
        try {
            $connection = $backup->validateConfiguration();
            $root = config('database_backup.directory');
            File::ensureDirectoryExists($root, 0700);
            chmod($root, 0700);
            // Only remove this command's expired private run directories.
            foreach (File::directories($root) as $old) {
                if (preg_match('/^backup-[0-9a-f-]{36}$/', basename($old)) && ! is_link($old)
                    && filemtime($old) < now()->subDays(max(1, config('database_backup.retention_days')))->timestamp) {
                    File::deleteDirectory($old);
                }
            }
            $directory = $root.'/backup-'.Str::uuid();
            File::makeDirectory($directory, 0700);
            $stage = 'database dump';
            $path = $backup->create($directory, $connection);
            $completeDump = true;
            $stage = 'Telegram upload';
            $backup->deliver($path);
            $stage = 'local cleanup';
            if (! File::deleteDirectory($directory)) {
                throw new \RuntimeException('Could not remove temporary backup files.');
            }
            $this->info('Database backup uploaded to Telegram. Temporary files removed.');
            Log::info('Telegram database backup completed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Do not forward raw process/HTTP exceptions, database credentials or bot tokens.
            $message = "Database backup failed during $stage.";
            if ($directory && ! $completeDump) {
                File::deleteDirectory($directory);
            } elseif ($directory) {
                $message .= ' The complete dump is retained in '.$directory;
            }
            $this->error($message);
            if ($stage === 'configuration' && $exception instanceof \RuntimeException) {
                $this->error($exception->getMessage());
            }
            Log::error($message);

            return self::FAILURE;
        } finally {
            umask($oldMask);
            $lock->release();
        }
    }
}
