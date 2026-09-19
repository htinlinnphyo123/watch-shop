<?php

namespace App\Jobs;

use App\Models\WatchImport;
use App\Services\WatchSpreadsheetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportWatchesFromSpreadsheet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public function __construct(public int $watchImportId)
    {
        $this->onConnection('watch-imports')->onQueue('watch-imports');
    }

    public function handle(WatchSpreadsheetService $spreadsheets): void
    {
        $import = WatchImport::findOrFail($this->watchImportId);
        $import->update(['status' => 'processing', 'started_at' => now(), 'failure_message' => null]);

        try {
            $path = Storage::disk('local')->path($import->file_path);
            if (! is_file($path)) {
                throw new \RuntimeException('The uploaded spreadsheet could not be found by the queue worker.');
            }

            $summary = $spreadsheets->import($spreadsheets->readRows($path));
            $import->update([
                'status' => 'completed',
                'summary' => $summary,
                'finished_at' => now(),
                'failure_message' => null,
            ]);
        } catch (ValidationException $exception) {
            $import->update([
                'status' => 'failed',
                'errors' => $exception->errors()['file'] ?? ['The spreadsheet contains invalid data.'],
                'failure_message' => 'Please correct the listed spreadsheet errors and import it again.',
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $import->update([
                'status' => 'failed',
                'failure_message' => 'The import could not be completed because of a server or spreadsheet error. Please try again or contact support.',
                'finished_at' => now(),
            ]);
            throw $exception;
        } finally {
            Storage::disk('local')->delete($import->file_path);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $import = WatchImport::find($this->watchImportId);
        if (! $import || $import->status === 'completed' || $import->status === 'failed') return;

        $import->update([
            'status' => 'failed',
            'failure_message' => 'The import worker stopped unexpectedly. Please try again or contact support.',
            'finished_at' => now(),
        ]);
        Storage::disk('local')->delete($import->file_path);
    }
}
