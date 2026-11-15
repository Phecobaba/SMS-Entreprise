<?php

namespace App\Modules\Core\Services;

class QaDatabaseProof
{
    public function passes(string $environment, ?string $marker, string $expected, string $actual): bool
    {
        return $environment !== 'production'
            && $marker === '1'
            && $expected !== ''
            && $actual !== ''
            && (new QaDatabasePath)->samePath($expected, $actual);
    }
}
