<?php

namespace Tests\Feature\Core;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class SourceTextEncodingTest extends TestCase
{
    public function test_application_source_text_is_utf8_without_bom_or_mojibake(): void
    {
        $roots = [
            resource_path(),
            app_path(),
            base_path('routes'),
            base_path('database/seeders'),
            config_path(),
        ];
        if (is_dir(base_path('lang'))) {
            $roots[] = base_path('lang');
        }

        $bad = [];
        foreach ($roots as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), ['php', 'js', 'css'], true)) {
                    continue;
                }
                $contents = file_get_contents($file->getPathname());
                if (! is_string($contents)
                    || ! mb_check_encoding($contents, 'UTF-8')
                    || str_starts_with($contents, "\xEF\xBB\xBF")
                    || str_contains($contents, "\u{00C3}")
                    || str_contains($contents, "\u{00C2}")
                    || str_contains($contents, "\u{00E2}\u{20AC}")) {
                    $bad[] = str_replace('\\', '/', str($file->getPathname())->after(base_path())->ltrim('/\\')->toString());
                }
            }
        }

        $this->assertSame([], array_values(array_unique($bad)), 'Invalid UTF-8, BOM or mojibake found in: '.implode(', ', $bad));
    }
}
