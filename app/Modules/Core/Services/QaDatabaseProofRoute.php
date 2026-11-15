<?php

namespace App\Modules\Core\Services;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;

class QaDatabaseProofRoute
{
    public function register(Router $router, string $environment, ?string $marker): ?Route
    {
        if ($environment === 'production' || $marker !== '1') {
            return null;
        }

        return $router->get('/_qa/database-proof', function (QaDatabaseProof $proof) use ($environment, $marker) {
            $expected = (string) env('OAKFIELD_QA_DATABASE', '');
            $actual = (string) DB::connection()->getDatabaseName();

            if (! $proof->passes($environment, $marker, $expected, $actual)) {
                return response("FAIL\n", 409)->header('Content-Type', 'text/plain');
            }

            return response("PASS\n".(realpath($actual) ?: $actual)."\n")
                ->header('Content-Type', 'text/plain');
        })->name('qa.database-proof');
    }
}
