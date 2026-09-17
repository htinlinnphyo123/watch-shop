<?php

namespace Tests\Feature;

use App\Services\TelegramDatabaseBackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelegramDatabaseBackupTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/telegram-backup-test-'.Str::uuid();
        config([
            'cache.default' => 'array', 'database_backup.enabled' => true,
            'database_backup.directory' => $this->directory,
            'database_backup.bot_token' => 'test-secret-token', 'database_backup.chat_id' => '-100123456789',
            'database_backup.connection' => 'backup_test', 'database_backup.chunk_bytes' => 1024,
            'database.connections.backup_test' => ['driver' => 'pgsql', 'host' => 'db.test', 'port' => 5432, 'database' => 'watch_shop', 'username' => 'backup_user', 'password' => 'database-secret', 'sslmode' => 'require'],
        ]);
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Log::spy();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function fakeDump(string $contents = "--\n-- PostgreSQL database dump\n--\nCREATE TABLE watches (id integer);\n"): void
    {
        Process::fake(function ($process) use ($contents) {
            $file = collect($process->command)->first(fn ($arg) => str_starts_with($arg, '--file='));
            file_put_contents(substr($file, 7), gzencode($contents, 6));

            return Process::result(exitCode: 0);
        });
    }

    private function fakeTelegram(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['document' => ['file_id' => 'uploaded-file']]])]);
    }

    public function test_command_uploads_one_sql_gz_file_then_removes_local_files(): void
    {
        $this->fakeDump();
        $uploadedSql = null;
        Http::fake(function ($request) use (&$uploadedSql) {
            $document = collect($request->data())->first(fn ($part) => ($part['name'] ?? null) === 'document');
            $this->assertStringEndsWith('.sql.gz', $document['filename']);
            $uploadedSql = gzdecode(stream_get_contents($document['contents']));

            return Http::response(['ok' => true, 'result' => ['document' => ['file_id' => 'ok']]]);
        });
        $this->artisan('backup:telegram')->assertExitCode(0);
        $this->assertStringContainsString('CREATE TABLE watches', $uploadedSql);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $data = collect($request->data())->pluck('contents', 'name');

            return $request->url() === 'https://api.telegram.org/bottest-secret-token/sendDocument'
                && $data['chat_id'] === '-100123456789'
                && str_contains($data['caption'], 'Backup complete (1 file(s)).');
        });
        Process::assertRan(fn ($process) => $process->environment['PGPASSWORD'] === 'database-secret'
            && $process->environment['PGSSLMODE'] === 'require'
            && ! str_contains(implode(' ', $process->command), 'database-secret')
            && in_array('--format=plain', $process->command, true));
        $this->assertSame([], File::directories($this->directory));
    }

    public function test_disabled_or_missing_credentials_never_dump_or_upload(): void
    {
        Process::fake();
        config(['database_backup.enabled' => false]);
        $this->artisan('backup:telegram')->assertExitCode(0);
        config(['database_backup.enabled' => true, 'database_backup.bot_token' => null]);
        $this->artisan('backup:telegram')->assertExitCode(1);
        Process::assertNothingRan();
        Http::assertNothingSent();
    }

    public function test_failed_dump_is_not_sent_and_incomplete_files_are_removed(): void
    {
        Process::fake(function ($process) {
            $file = collect($process->command)->first(fn ($arg) => str_starts_with($arg, '--file='));
            file_put_contents(substr($file, 7), 'partial dump');

            return Process::result(errorOutput: 'database-secret', exitCode: 1);
        });
        $this->artisan('backup:telegram')->assertExitCode(1);
        Http::assertNothingSent();
        $this->assertSame([], File::directories($this->directory));
        Log::shouldHaveReceived('error')->with('Database backup failed during database dump.')->once();
    }

    public function test_telegram_api_failure_retains_dump_and_does_not_expose_token(): void
    {
        $this->fakeDump();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'test-secret-token'], 403)]);
        $this->artisan('backup:telegram')->assertExitCode(1);
        $runs = File::directories($this->directory);
        $this->assertCount(1, $runs);
        $this->assertCount(1, glob($runs[0].'/*.sql.gz'));
        Http::assertSentCount(1);
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => ! str_contains($message, 'test-secret-token') && str_contains($message, 'retained'))->once();
    }

    public function test_large_sql_gz_parts_reassemble_with_checksum_in_the_caption(): void
    {
        config(['database_backup.chunk_bytes' => 10]);
        File::ensureDirectoryExists($this->directory, 0700);
        $path = $this->directory.'/backup.sql.gz';
        $sql = "-- PostgreSQL database dump\nCREATE TABLE watches (id integer);\n";
        $contents = gzencode($sql, 6);
        file_put_contents($path, $contents);
        $uploads = [];
        $captions = [];
        Http::fake(function ($request) use (&$uploads, &$captions) {
            $multipart = $request->data();
            $document = collect($multipart)->first(fn ($part) => is_array($part) && ($part['name'] ?? null) === 'document');
            $uploads[$document['filename']] = stream_get_contents($document['contents']);
            $captions[] = collect($multipart)->first(fn ($part) => ($part['name'] ?? null) === 'caption')['contents'];

            return Http::response(['ok' => true, 'result' => ['document' => ['file_id' => 'ok']]]);
        });
        app(TelegramDatabaseBackupService::class)->deliver($path);
        $this->assertSame($contents, implode('', $uploads));
        $this->assertSame($sql, gzdecode(implode('', $uploads)));
        $this->assertCount((int) ceil(strlen($contents) / 10), $uploads);
        $this->assertStringContainsString(hash('sha256', $contents), end($captions));
        $this->assertStringContainsString('Backup complete', end($captions));
        foreach (array_slice($captions, 0, -1) as $caption) {
            $this->assertStringNotContainsString('Backup complete', $caption);
        }
        foreach (array_keys($uploads) as $filename) {
            $this->assertMatchesRegularExpression('/^backup\.sql\.gz\.part[0-9]{5}$/', $filename);
        }
    }

    public function test_command_prevents_overlapping_runs_and_prunes_expired_failures(): void
    {
        $this->fakeDump();
        $this->fakeTelegram();
        $lock = Cache::lock('database-backup:telegram', 21600);
        $lock->get();
        $this->artisan('backup:telegram')->assertExitCode(0);
        Process::assertNothingRan();
        $lock->release();
        $old = $this->directory.'/backup-'.Str::uuid();
        File::ensureDirectoryExists($old, 0700);
        file_put_contents($old.'/old.dump', 'old backup');
        touch($old, now()->subDays(8)->timestamp);
        $this->artisan('backup:telegram')->assertExitCode(0);
        $this->assertDirectoryDoesNotExist($old);
    }

    public function test_transport_exception_retains_backup_without_logging_credentials(): void
    {
        $this->fakeDump();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('https://api.telegram.org/bottest-secret-token/sendDocument'));
        $this->artisan('backup:telegram')->assertExitCode(1);
        $runs = File::directories($this->directory);
        $this->assertCount(1, $runs);
        $this->assertCount(1, glob($runs[0].'/*.sql.gz'));
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => ! str_contains($message, 'test-secret-token'))->once();
    }

    public function test_unsupported_connection_and_invalid_archive_are_not_uploaded(): void
    {
        config(['database.connections.backup_test.driver' => 'sqlite']);
        Process::fake();
        $this->artisan('backup:telegram')->assertExitCode(1);
        Process::assertNothingRan();
        Http::assertNothingSent();
        \Illuminate\Support\Facades\DB::purge('backup_test');
        config(['database.connections.backup_test.driver' => 'pgsql']);
        $this->fakeDump('not-a-postgres-archive');
        $this->artisan('backup:telegram')->assertExitCode(1);
        Http::assertNothingSent();
    }

    public function test_schedule_registers_daily_time_and_timezone(): void
    {
        config(['database_backup.time' => '02:00', 'database_backup.timezone' => 'Asia/Bangkok']);
        $this->app->forgetInstance(Schedule::class);
        $events = collect($this->app->make(Schedule::class)->events());
        $event = $events->first(fn ($event) => str_contains($event->command ?? '', 'backup:telegram'));
        $this->assertNotNull($event);
        $this->assertSame('0 2 * * *', $event->expression);
        $this->assertSame('Asia/Bangkok', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }
}
