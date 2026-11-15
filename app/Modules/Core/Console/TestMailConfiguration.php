<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Services\RecoveryMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestMailConfiguration extends Command
{
    protected $signature = 'oakfield:mail-test {address}';

    protected $description = 'Send a plain delivery test through the configured mailer';

    public function handle(RecoveryMailer $recoveryMailer): int
    {
        $address = trim((string) $this->argument('address'));
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Enter a valid destination email address.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $settings = config("mail.mailers.{$mailer}", []);
        $this->line('Mailer: '.($mailer !== '' ? $mailer : '(not set)'));
        $this->line('Host: '.($settings['host'] ?? '(not set)'));
        $this->line('Port: '.($settings['port'] ?? '(not set)'));
        $this->line('Scheme: '.($settings['scheme'] ?? '(not set)'));

        if (! $recoveryMailer->available()) {
            $this->error('Mail test refused: the configured mailer is missing, invalid, or includes the log transport. Configure a real delivery mailer first.');

            return self::FAILURE;
        }

        try {
            Mail::raw(
                'Oakfield mail configuration test. No action is required.',
                fn ($message) => $message->to($address)->subject('Oakfield mail test'),
            );
        } catch (Throwable $exception) {
            $this->error('Transport error ['.$exception::class.']: '.$recoveryMailer->scrubFailureMessage(
                $exception->getMessage(),
                [$address, config("mail.mailers.{$mailer}.username"), config("mail.mailers.{$mailer}.password")],
            ));

            return self::FAILURE;
        }

        $this->info('Test message sent successfully.');

        return self::SUCCESS;
    }
}
