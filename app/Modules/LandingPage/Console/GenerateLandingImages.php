<?php

namespace App\Modules\LandingPage\Console;

use Illuminate\Console\Command;
use RuntimeException;

class GenerateLandingImages extends Command
{
    protected $signature = 'landing:images';

    protected $description = 'Generate responsive WebP images for the public landing pages';

    public function handle(): int
    {
        if (! function_exists('imagewebp')) {
            $this->error('GD with WebP write support is required.');

            return self::FAILURE;
        }
        $source = base_path('docs/design/landing/assets/photos');
        $target = public_path('images/landing');
        if (! is_dir($source)) {
            throw new RuntimeException('Landing source photo directory was not found.');
        }
        if (! is_dir($target) && ! mkdir($target, 0775, true) && ! is_dir($target)) {
            throw new RuntimeException('Landing image output directory could not be created.');
        }
        $written = 0;
        foreach (glob($source.'/*.jpg') ?: [] as $file) {
            [$width, $height] = getimagesize($file);
            $image = imagecreatefromjpeg($file);
            if (! $image) {
                $this->warn('Skipped '.basename($file));

                continue;
            }
            $widths = [600, 1200];
            if (str_contains(basename($file), 'HERO') && $width >= 1920) {
                $widths[] = 1920;
            }
            foreach ($widths as $wanted) {
                $outWidth = min($wanted, $width);
                $outHeight = (int) round($height * ($outWidth / $width));
                $resized = imagecreatetruecolor($outWidth, $outHeight);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $outWidth, $outHeight, $width, $height);
                imagewebp($resized, $target.'/'.pathinfo($file, PATHINFO_FILENAME).'-'.$wanted.'.webp', 76);
                imagedestroy($resized);
                $written++;
            }
            imagedestroy($image);
        }
        $this->info("Generated {$written} responsive WebP files in public/images/landing.");

        return self::SUCCESS;
    }
}
