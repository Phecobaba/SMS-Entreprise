<?php

namespace App\Modules\Core\Services;

use InvalidArgumentException;

class QaDatabasePath
{
    public function resolve(
        string $input,
        string $environment,
        string $databaseDirectory,
        string $developmentDatabase,
    ): string {
        if ($environment === 'production') {
            throw new InvalidArgumentException('QA preparation is disabled in production.');
        }

        $input = trim($input);
        if (! $this->isAbsolute($input)) {
            throw new InvalidArgumentException('The QA database path must be absolute.');
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $input);
        if (basename($normalized) !== 'qa.sqlite') {
            throw new InvalidArgumentException('The QA database file must be named exactly qa.sqlite.');
        }

        $parent = realpath(dirname($normalized));
        if ($parent === false) {
            throw new InvalidArgumentException('The QA database directory does not exist or cannot be resolved.');
        }
        $allowed = realpath($databaseDirectory);
        if ($allowed === false || ! $this->samePath($parent, $allowed)) {
            throw new InvalidArgumentException('The QA database must be the qa.sqlite file in this project database directory.');
        }

        $resolved = $parent.DIRECTORY_SEPARATOR.'qa.sqlite';
        $development = realpath($developmentDatabase) ?: $developmentDatabase;
        if ($this->samePath($resolved, $development)) {
            throw new InvalidArgumentException('The persistent development database cannot be used for QA.');
        }

        return $resolved;
    }

    public function samePath(string $first, string $second): bool
    {
        $normalize = fn (string $path): string => rtrim(
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path),
            DIRECTORY_SEPARATOR,
        );

        return strcasecmp($normalize($first), $normalize($second)) === 0;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
