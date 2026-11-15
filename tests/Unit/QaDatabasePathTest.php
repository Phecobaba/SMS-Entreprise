<?php

namespace Tests\Unit;

use App\Modules\Core\Services\QaDatabasePath;
use App\Modules\Core\Services\QaDatabaseProof;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class QaDatabasePathTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oakfield-qa-path-'.bin2hex(random_bytes(5));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $database = $this->directory.DIRECTORY_SEPARATOR.'qa.sqlite';
        if (is_file($database)) {
            unlink($database);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function test_guard_accepts_only_an_absolute_resolvable_non_production_qa_path(): void
    {
        $guard = new QaDatabasePath;
        $development = $this->directory.DIRECTORY_SEPARATOR.'database.sqlite';
        $valid = $this->directory.DIRECTORY_SEPARATOR.'qa.sqlite';

        $this->assertSame($valid, $guard->resolve($valid, 'local', $this->directory, $development));
        $this->assertRejected(fn () => $guard->resolve('database/qa.sqlite', 'local', $this->directory, $development));
        $this->assertRejected(fn () => $guard->resolve($development, 'local', $this->directory, $development));
        $this->assertRejected(fn () => $guard->resolve($this->directory.DIRECTORY_SEPARATOR.'other.sqlite', 'local', $this->directory, $development));
        $this->assertRejected(fn () => $guard->resolve($this->directory.DIRECTORY_SEPARATOR.'missing'.DIRECTORY_SEPARATOR.'qa.sqlite', 'local', $this->directory, $development));
        $this->assertRejected(fn () => $guard->resolve($valid, 'production', $this->directory, $development));
    }

    public function test_live_proof_requires_the_qa_marker_and_an_exact_path_match(): void
    {
        $proof = new QaDatabaseProof;
        $expected = $this->directory.DIRECTORY_SEPARATOR.'qa.sqlite';
        $other = $this->directory.DIRECTORY_SEPARATOR.'other.sqlite';

        $this->assertTrue($proof->passes('local', '1', $expected, $expected));
        $this->assertFalse($proof->passes('local', null, $expected, $expected));
        $this->assertFalse($proof->passes('local', '0', $expected, $expected));
        $this->assertFalse($proof->passes('local', '1', $expected, $other));
        $this->assertFalse($proof->passes('production', '1', $expected, $expected));
    }

    private function assertRejected(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Unsafe QA path was accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }
}
