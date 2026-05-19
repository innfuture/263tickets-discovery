<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventLineupArtist;
use App\Models\EventMediaItem;
use App\Services\ImageProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReprocessEventImages extends Command
{
    protected $signature = 'events:reprocess-images {--force : Re-process even if the image is already a .webp}';

    protected $description = 'Convert existing event images to WebP and generate thumbnail variants.';

    public function handle(ImageProcessingService $service): int
    {
        $force = (bool) $this->option('force');

        $this->info('Re-processing event banners…');
        $this->processModelImages(
            Event::whereNotNull('banner_image_path')->cursor(),
            'banner_image_path',
            'events/banners',
            ImageProcessingService::BANNER_SIZES,
            $service,
            $force,
            label: fn (Event $m) => "Event #{$m->id} \"{$m->name}\"",
        );

        $this->info('Re-processing gallery items…');
        $this->processModelImages(
            EventMediaItem::where('type', 'image')->whereNotNull('path')->cursor(),
            'path',
            'events/gallery',
            ImageProcessingService::BANNER_SIZES,
            $service,
            $force,
            label: fn (EventMediaItem $m) => "Media #{$m->id} (event {$m->event_id})",
        );

        $this->info('Re-processing lineup photos…');
        $this->processModelImages(
            EventLineupArtist::whereNotNull('image_path')
                ->where('image_path', 'not like', 'http%')
                ->cursor(),
            'image_path',
            'events/lineup',
            ImageProcessingService::SQUARE_SIZES,
            $service,
            $force,
            label: fn (EventLineupArtist $m) => "Artist #{$m->id} \"{$m->name}\"",
        );

        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  iterable<TModel>  $rows
     * @param  array<string, array{0:int,1:int}>  $sizes
     * @param  callable(TModel): string  $label
     */
    private function processModelImages(
        iterable $rows,
        string $pathColumn,
        string $destination,
        array $sizes,
        ImageProcessingService $service,
        bool $force,
        callable $label,
    ): void {
        $count = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($rows as $model) {
            $oldPath = $model->getAttribute($pathColumn);

            if (! $oldPath) {
                continue;
            }

            if (! $force && str_ends_with(strtolower($oldPath), '.webp')) {
                $skipped++;
                continue;
            }

            if (! Storage::disk('public')->exists($oldPath)) {
                $this->warn('  · Skipped (file missing): '.$label($model));
                $skipped++;
                continue;
            }

            try {
                $absolute = Storage::disk('public')->path($oldPath);
                $newPath = $service->processAndStoreFromPath(
                    $absolute,
                    $destination,
                    $sizes,
                );

                $model->update([$pathColumn => $newPath]);

                if ($newPath !== $oldPath) {
                    $service->delete($oldPath);
                }

                $this->line('  · '.$label($model).' → '.$newPath);
                $count++;
            } catch (Throwable $e) {
                $this->error('  · Failed: '.$label($model).' — '.$e->getMessage());
                $failed++;
            }
        }

        $this->info(sprintf(
            '  %d processed · %d skipped · %d failed',
            $count,
            $skipped,
            $failed,
        ));
    }
}
