<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\File;
use ParseError;
use Tests\TestCase;

class BladeSyntaxTest extends TestCase
{
    public function test_every_blade_view_compiles_to_valid_php(): void
    {
        $compiler = app('blade.compiler');

        foreach (File::allFiles(resource_path('views')) as $view) {
            $relative = str_replace('\\', '/', $view->getRelativePathname());

            try {
                token_get_all($compiler->compileString($view->getContents()), TOKEN_PARSE);
            } catch (ParseError $exception) {
                $this->fail("Blade view [{$relative}] compiles to invalid PHP: {$exception->getMessage()}");
            }
        }

        $this->addToAssertionCount(1);
    }
}
