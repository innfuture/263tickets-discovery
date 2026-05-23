<?php

declare(strict_types=1);

namespace App\Console\Commands\Payments;

use App\Services\Payments\Sandbox\Recorder;
use Illuminate\Console\Command;

/**
 * Import a captured production response into the sandbox recording
 * table. Usage:
 *
 *   php artisan sandbox:record stripe charge success path/to/response.json
 *
 * The file must be a JSON object — what the gateway literally
 * returned. The recording becomes available to the Diff-vs-prod tab
 * for the matching scenario.
 */
class SandboxRecordCommand extends Command
{
    protected $signature = 'sandbox:record
        {emulate : Provider slug, e.g. stripe|adyen|paynow}
        {operation : One of charge|capture|refund|status|void}
        {scenario : Scenario slug; use "default" if not scenario-specific}
        {file : Path to a JSON file containing the response body}
        {--status=200 : HTTP status the gateway returned}
        {--notes= : Free-text notes pinned to the recording}';

    protected $description = 'Import a production response into the sandbox recording catalog.';

    public function handle(Recorder $recorder): int
    {
        $file = (string) $this->argument('file');
        if (! is_readable($file)) {
            $this->error("Cannot read {$file}");

            return self::FAILURE;
        }

        $body = json_decode((string) file_get_contents($file), true);
        if (! is_array($body)) {
            $this->error('File is not valid JSON.');

            return self::FAILURE;
        }

        $recording = $recorder->record(
            emulate: (string) $this->argument('emulate'),
            operation: (string) $this->argument('operation'),
            scenario: $this->argument('scenario') === 'default' ? null : (string) $this->argument('scenario'),
            responseStatus: (int) $this->option('status'),
            responseBody: $body,
            source: 'capture-cli',
            notes: $this->option('notes') ?: null,
        );

        $this->info("Recorded as {$recording->slug} ({$recording->uuid}).");

        return self::SUCCESS;
    }
}
