<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class TelegramDatabaseBackupService
{
    public function validateConfiguration(): array
    {
        if (! config('database_backup.bot_token') || ! config('database_backup.chat_id')) {
            throw new RuntimeException('Set TELEGRAM_BOT_TOKEN and TELEGRAM_BACKUP_CHAT_ID in the environment.');
        }
        if (! function_exists('gzopen')) {
            throw new RuntimeException('Enable the PHP zlib extension to validate compressed SQL backups.');
        }
        $connection = DB::connection(config('database_backup.connection'))->getConfig();
        if (($connection['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException('This backup command supports PostgreSQL connections only.');
        }
        foreach (['host', 'port', 'database', 'username'] as $key) {
            if (! is_scalar($connection[$key] ?? null) || (string) $connection[$key] === '') {
                throw new RuntimeException('The PostgreSQL backup connection needs a host, port, database and username.');
            }
        }

        return $connection;
    }

    public function create(string $directory, array $connection): string
    {
        $path = $directory.'/database-'.now('UTC')->format('Y-m-d_H-i-s').'.sql.gz';
        $environment = ['PGPASSWORD' => (string) ($connection['password'] ?? ''), 'PGSSLMODE' => $connection['sslmode'] ?? 'prefer', 'PGCONNECT_TIMEOUT' => '30'];
        foreach (['sslrootcert' => 'PGSSLROOTCERT', 'sslcert' => 'PGSSLCERT', 'sslkey' => 'PGSSLKEY'] as $key => $variable) {
            if (! empty($connection[$key])) {
                $environment[$variable] = $connection[$key];
            }
        }
        // Argument arrays avoid shell interpolation; the password is never a CLI argument.
        $result = Process::timeout(config('database_backup.dump_timeout'))->env($environment)->run([
            config('database_backup.pg_dump_binary'), '--no-password', '--format=plain', '--compress=6',
            '--no-owner', '--no-acl', '--host='.$connection['host'], '--port='.$connection['port'],
            '--username='.$connection['username'], '--file='.$path, '--dbname='.$connection['database'],
        ]);
        if (! $result->successful() || ! is_file($path) || filesize($path) < 5) {
            throw new RuntimeException('pg_dump failed. Check the client installation/version, database connection and read permissions.');
        }
        $handle = fopen($path, 'rb');
        try {
            if (fread($handle, 2) !== "\x1f\x8b") {
                throw new RuntimeException('pg_dump did not create a gzip file.');
            }
        } finally {
            fclose($handle);
        }
        $sql = gzopen($path, 'rb');
        if ($sql === false) {
            throw new RuntimeException('Could not read the compressed SQL backup.');
        }
        try {
            $header = gzread($sql, 1024);
            if ($header === false || ! str_contains($header, '-- PostgreSQL database dump')) {
                throw new RuntimeException('The gzip file does not contain a PostgreSQL SQL dump.');
            }
        } finally {
            gzclose($sql);
        }
        chmod($path, 0600);

        return $path;
    }

    public function deliver(string $path): void
    {
        $bytes = filesize($path);
        $chunkBytes = max(1, min(45 * 1024 * 1024, (int) config('database_backup.chunk_bytes')));
        $parts = (int) ceil($bytes / $chunkBytes);
        $label = Str::limit(config('app.name').' ['.app()->environment().']', 100, '');
        $input = fopen($path, 'rb');
        try {
            for ($part = 1; $part <= $parts; $part++) {
                $uploadPath = $path;
                if ($parts > 1) {
                    $uploadPath = $path.'.part'.str_pad((string) $part, 5, '0', STR_PAD_LEFT);
                    $output = fopen($uploadPath, 'xb');
                    try {
                        $copied = stream_copy_to_stream($input, $output, $chunkBytes);
                        if ($copied !== min($chunkBytes, $bytes - ($part - 1) * $chunkBytes)) {
                            throw new RuntimeException('Could not write a complete backup part. Check local disk space.');
                        }
                    } finally {
                        fclose($output);
                    }
                    chmod($uploadPath, 0600);
                }
                $caption = "$label\n • part $part of $parts\n".basename($path);
                if ($parts > 1) {
                    $caption .= "\nJoin all $parts numbered parts before decompressing.";
                }
                // No separate manifest: the final document acknowledges that all
                // preceding uploads have succeeded and carries the full checksum.
                if ($part === $parts) {
                    $caption .= "\nBackup complete ($parts file(s)).";
                }
                $this->sendDocument($uploadPath, $caption);
                if ($parts > 1) {
                    unlink($uploadPath);
                }
            }
        } finally {
            fclose($input);
        }

    }

    private function sendDocument(string $path, string $caption): void
    {
        $stream = fopen($path, 'rb');
        try {
            // Never log request URLs/exceptions: Telegram includes the bot token in the URL.
            $response = Http::connectTimeout(30)->timeout(config('database_backup.upload_timeout'))
                ->withOptions(['allow_redirects' => false])
                ->attach('document', $stream, basename($path))
                ->post('https://api.telegram.org/bot'.config('database_backup.bot_token').'/sendDocument', [
                    'chat_id' => (string) config('database_backup.chat_id'),
                    'caption' => $caption,
                ]);
            if (! $response->successful() || $response->json('ok') !== true || ! $response->json('result.document.file_id')) {
                throw new RuntimeException('Telegram did not confirm the backup upload. Check bot permissions, chat ID and connectivity.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
