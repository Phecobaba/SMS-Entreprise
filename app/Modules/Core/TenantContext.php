<?php

namespace App\Modules\Core;

use RuntimeException;

class TenantContext
{
    private ?int $schoolId = null;

    public function set(int $schoolId): void
    {
        if ($schoolId < 1) {
            throw new RuntimeException('A valid school ID is required.');
        }

        $this->schoolId = $schoolId;
    }

    public function id(): int
    {
        return $this->schoolId ?? throw new RuntimeException('School context has not been set.');
    }

    public function clear(): void
    {
        $this->schoolId = null;
    }
}
