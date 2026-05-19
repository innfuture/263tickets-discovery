<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Throwable;

class ImageProcessingService
{
    /** @var array<string, array{0:int,1:int}> */
    public const BANNER_SIZES = [
        'thumb' => [200, 113],
        'small' => [400, 225],
        'medium' => [800, 450],
        'large' => [1600, 900],
    ];

    /** @var array<string, array{0:int,1:int}> */
    public const SQUARE_SIZES = [
        'thumb' => [80, 80],
        'small' => [200, 200],
        'medium' => [400, 400],
    ];

    private const WEBP_QUALITY = 85;
    private const THUMB_QUALITY = 80;
    private const DISK = 'public';

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new GdDriver);
    }

    /**
     * Convert an UploadedFile to WebP + thumbnail variants.
     *
     * @param  array<string, array{0:int,1:int}>  $sizes
     * @return string The relative path of the converted original.
     */
    public function processAndStore(
        UploadedFile $file,
        string $directory,
        array $sizes = [],
    ): string {
        return $this->processAndStoreFromPath(
            $file->getRealPath(),
            $directory,
            $sizes,
        );
    }

    /**
     * Convert any local file path to WebP + thumbnail variants.
     * Reads the source image once and clones per variant to avoid disk re-reads.
     *
     * @param  array<string, array{0:int,1:int}>  $sizes
     * @return string The relative path of the converted original.
     */
    public function processAndStoreFromPath(
        string $sourcePath,
        string $directory,
        array $sizes = [],
    ): string {
        $filename = Str::random(40).'.webp';
        $relative = trim($directory, '/').'/'.$filename;

        // Read source once. Clone per operation so the source stays pristine.
        $source = $this->manager->decodePath($sourcePath);

        $original = (string) (clone $source)->toWebp(self::WEBP_QUALITY);

        Storage::disk(self::DISK)->put($relative, $original);

        foreach ($sizes as $name => [$width, $height]) {
            try {
                $thumb = (string) (clone $source)
                    ->cover($width, $height)
                    ->toWebp(self::THUMB_QUALITY);

                Storage::disk(self::DISK)->put(
                    trim($directory, '/')."/thumbnails/{$name}/{$filename}",
                    $thumb,
                );
            } catch (Throwable) {
                // One bad thumbnail shouldn't kill the upload — original is saved.
            }
        }

        return $relative;
    }

    /**
     * Delete original + all known thumbnail sizes for a previously stored image.
     */
    public function delete(string $path): void
    {
        if ($path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);

        $directory = dirname($path);
        $filename = basename($path);

        foreach (self::BANNER_SIZES as $name => $_) {
            Storage::disk(self::DISK)->delete("{$directory}/thumbnails/{$name}/{$filename}");
        }
        foreach (self::SQUARE_SIZES as $name => $_) {
            Storage::disk(self::DISK)->delete("{$directory}/thumbnails/{$name}/{$filename}");
        }
    }
}
