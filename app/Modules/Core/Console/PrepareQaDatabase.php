<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Services\QaDatabasePath;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;

class PrepareQaDatabase extends Command
{
    protected $signature = 'qa:prepare
        {path : Absolute path to this project database/qa.sqlite}
        {--serve : Start the QA web server after preparation}
        {--host=127.0.0.1 : Host used with --serve}
        {--port=8000 : Port used with --serve}
        {--simulate-proof-mismatch : Deliberately fail the live proof for launcher verification}';

    protected $description = 'Safely prepare and optionally serve an isolated browser-QA SQLite database';

    public function handle(QaDatabasePath $paths): int
    {
        try {
            $path = $paths->resolve(
                (string) $this->argument('path'),
                app()->environment(),
                database_path(),
                database_path('database.sqlite'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! file_exists($path) && ! touch($path)) {
            throw new RuntimeException("Unable to create QA database at {$path}.");
        }
        $path = realpath($path) ?: $path;
        $this->line("Resolved QA database: {$path}");

        $this->useDatabase($path);
        $connected = (string) DB::connection('sqlite')->getDatabaseName();
        if (! $paths->samePath($connected, $path)) {
            $this->error("Connection verification failed: {$connected}");

            return self::FAILURE;
        }
        $this->info("Verified connection: sqlite -> {$connected}");

        if ($this->call('migrate', ['--database' => 'sqlite', '--force' => true]) !== self::SUCCESS
            || $this->call('db:seed', ['--database' => 'sqlite', '--class' => DemoDataSeeder::class, '--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('QA database prepared.');
        if (! $this->option('serve')) {
            return self::SUCCESS;
        }

        return $this->serveQa($path);
    }

    private function useDatabase(string $path): void
    {
        putenv('DB_CONNECTION=sqlite');
        putenv("DB_DATABASE={$path}");
        $_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $path;
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $path);
        DB::purge('sqlite');
    }

    private function serveQa(string $path): int
    {
        $host = (string) $this->option('host');
        $port = (int) $this->option('port');
        $proofPath = $this->option('simulate-proof-mismatch')
            ? database_path('qa-proof-mismatch.sqlite')
            : $path;
        $server = base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
        $process = new Process([
            PHP_BINARY,
            '-d',
            'variables_order=EGPCS',
            '-S',
            "{$host}:{$port}",
            $server,
        ], public_path(), [
            'APP_ENV' => 'local',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $path,
            'OAKFIELD_QA_ACTIVE' => '1',
            'OAKFIELD_QA_DATABASE' => $proofPath,
        ]);
        $process->setTimeout(null);
        $sessionsBefore = DB::connection('sqlite')->table('sessions')->count();
        $hashBefore = hash_file('sha256', $path);
        $process->start();

        try {
            $proofUrl = "http://{$host}:{$port}/_qa/database-proof";
            $proof = $this->waitForProof($process, $proofUrl);
            $proofPath = $this->proofPath($proof['body'] ?? '');
            if (($proof['status'] ?? 0) !== 200
                || $proofPath === null
                || ! (new QaDatabasePath)->samePath($proofPath, $path)) {
                $status = $proof['status'] ?? 0;
                $body = trim($proof['body'] ?? 'missing');
                $this->error("Live HTTP database proof failed: HTTP {$status} {$body}");
                $diagnostic = trim($process->getErrorOutput()."\n".$process->getOutput());
                if ($diagnostic !== '') {
                    $this->line($diagnostic);
                }

                return self::FAILURE;
            }

            DB::purge('sqlite');
            $sessionsAfter = DB::connection('sqlite')->table('sessions')->count();
            $hashAfter = hash_file('sha256', $path);
            if ($sessionsAfter !== $sessionsBefore || $hashAfter !== $hashBefore) {
                $this->error("Live proof wrote to the QA database: sessions {$sessionsBefore} -> {$sessionsAfter}");

                return self::FAILURE;
            }

            $this->info("Live HTTP proof: PASS -> {$proofPath}");
            $this->info("Live proof database writes: none (sessions {$sessionsBefore} -> {$sessionsAfter}; SHA-256 unchanged)");
            $this->info("QA server running on http://{$host}:{$port}");
            $this->comment('Press Ctrl+C to stop the server.');

            return $process->wait(function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });
        } finally {
            $this->stopQaProcess($process);
        }
    }

    /** @return array{status: int, body: string}|null */
    private function waitForProof(Process $process, string $url): ?array
    {
        $deadline = microtime(true) + 45;
        do {
            if (! $process->isRunning()) {
                return null;
            }

            $response = $this->rawHttpGet($url);
            if ($response !== null) {
                return $response;
            }

            usleep(100_000);
        } while (microtime(true) < $deadline);

        return null;
    }

    /** @return array{status: int, body: string}|null */
    private function rawHttpGet(string $url): ?array
    {
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '127.0.0.1');
        $port = (int) ($parts['port'] ?? 80);
        $path = (string) ($parts['path'] ?? '/');
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $error, $message, 1);
        if (! is_resource($socket)) {
            return null;
        }

        stream_set_timeout($socket, 30);
        fwrite($socket, "GET {$path} HTTP/1.1\r\nHost: {$host}:{$port}\r\nConnection: close\r\n\r\n");
        $raw = stream_get_contents($socket);
        fclose($socket);
        if (! is_string($raw) || ! preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})/', $raw, $matches)) {
            return null;
        }

        [, $body] = array_pad(preg_split("/\r?\n\r?\n/", $raw, 2) ?: [], 2, '');

        return ['status' => (int) $matches[1], 'body' => $body];
    }

    private function proofPath(string $body): ?string
    {
        $lines = preg_split('/\R/', trim($body)) ?: [];

        return count($lines) === 2 && $lines[0] === 'PASS' ? $lines[1] : null;
    }

    private function stopQaProcess(Process $process): void
    {
        if (! $process->isRunning()) {
            return;
        }

        $pid = $process->getPid();
        if (PHP_OS_FAMILY === 'Windows' && $pid !== null) {
            $stop = new Process([
                'powershell.exe',
                '-NoProfile',
                '-NonInteractive',
                '-Command',
                "Stop-Process -Id {$pid} -Force -ErrorAction Stop",
            ]);
            $stop->setTimeout(5);
            try {
                $stop->mustRun();
            } catch (\Throwable) {
                $process->stop(0);
            }

            return;
        }

        $process->stop(3);
    }
}
