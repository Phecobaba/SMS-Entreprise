<?php

namespace App\Modules\Payments\Services;

use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Notifications\Services\InAppNotifications;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaystackCredentials
{
    private bool $loaded = false;

    private ?string $publicKey = null;

    private ?string $secretKey = null;

    private string $source = 'none';

    private ?string $mode = null;

    private bool $unreadable = false;

    private ?SchoolSetting $settings = null;

    public function isConfigured(): bool
    {
        $this->load();

        return $this->publicKey !== null && $this->secretKey !== null && $this->mode !== null;
    }

    public function secretKey(): ?string
    {
        $this->load();

        return $this->isConfigured() ? $this->secretKey : null;
    }

    public function secretKeyOrFail(): string
    {
        $secret = $this->secretKey();
        if ($secret === null) {
            throw ValidationException::withMessages([
                'payment' => "Online payment isn't set up yet. Please visit the bursar with your bank teller.",
            ]);
        }

        return $secret;
    }

    public function state(): array
    {
        $this->load();
        $changedBy = $this->settings?->paystackChangedBy;

        return [
            'configured' => $this->isConfigured(),
            'source' => $this->source,
            'mode' => $this->mode,
            'public_key' => $this->source === 'saved' ? $this->publicKey : null,
            'secret_mask' => $this->source === 'saved' && $this->settings?->paystack_secret_last_four
                ? 'sk_'.$this->mode.'_••••'.$this->settings->paystack_secret_last_four : null,
            'changed_by' => $changedBy?->name,
            'changed_at' => $this->settings?->paystack_changed_at,
            'unreadable' => $this->unreadable,
        ];
    }

    public function save(string $publicKey, ?string $newSecretKey, string $password, User $actor): void
    {
        abort_unless($actor->can('manage_payment_gateway'), 403);
        if (! Hash::check($password, $actor->password)) {
            throw ValidationException::withMessages(['current_password' => 'The password is incorrect.']);
        }

        $settings = SchoolSetting::firstOrCreate([]);
        $secretKey = filled($newSecretKey) ? trim((string) $newSecretKey) : $this->savedSecret($settings);
        if ($secretKey === null) {
            throw ValidationException::withMessages(['secret_key' => 'Enter the secret key.']);
        }

        $publicKey = trim($publicKey);
        $mode = $this->matchingMode($publicKey, $secretKey);
        if ($mode === null) {
            throw ValidationException::withMessages([
                'secret_key' => 'The public and secret keys must both be Test keys or both be Live keys.',
            ]);
        }

        $settings->fill([
            'paystack_public_key' => $publicKey,
            'paystack_secret_key' => $secretKey,
            'paystack_secret_last_four' => substr($secretKey, -4),
            'paystack_mode' => $mode,
            'paystack_changed_by' => $actor->id,
            'paystack_changed_at' => now(),
        ])->save();

        app(AuditRecorder::class)->record($actor, 'payment_gateway.changed', $settings);
        $otherAdmins = User::whereKeyNot($actor->id)
            ->whereHas('roles.permissions', fn ($query) => $query->where('permissions.name', 'manage_payment_gateway'))
            ->pluck('id')->all();
        app(InAppNotifications::class)->send(
            $otherAdmins,
            'Payment gateway keys changed',
            'The Paystack gateway keys were changed by '.$actor->name.'.',
            route('payments.gateway.show', absolute: false),
        );

        $this->reset();
    }

    public function testConnection(): bool
    {
        return $this->connectionTest()['ok'];
    }

    public function connectionTest(): array
    {
        $secret = $this->secretKey();
        if ($secret === null) {
            return $this->failedConnectionTest(
                'keys_missing',
                'Paystack keys are missing. Save the public and secret keys, then try again.',
            );
        }

        try {
            $response = Http::withToken($secret)->acceptJson()->connectTimeout(5)->timeout(15)
                ->get(rtrim(config('services.paystack.base_url'), '/').'/transaction', [
                    'perPage' => 1,
                    'page' => 1,
                ]);
        } catch (ConnectionException) {
            return $this->failedConnectionTest(
                'network_error',
                'We could not reach Paystack. Check the internet connection and try again.',
            );
        } catch (\Throwable) {
            return $this->failedConnectionTest(
                'unexpected_error',
                'Paystack returned an unexpected response. Try again later.',
            );
        }

        if ($response->status() === 401) {
            return $this->failedConnectionTest(
                'credentials_rejected',
                'Paystack rejected the configured keys. Check and save them again.',
                401,
                $response->json('message'),
            );
        }
        if (! $response->successful() || $response->json('status') !== true) {
            return $this->failedConnectionTest(
                'http_error',
                'Paystack returned an unexpected response. Try again later.',
                $response->status(),
                $response->json('message'),
            );
        }

        return ['ok' => true, 'category' => 'ok', 'message' => 'Connection OK. Paystack accepted the configured keys. No money moved.'];
    }

    private function failedConnectionTest(
        string $category,
        string $message,
        ?int $httpStatus = null,
        mixed $paystackMessage = null,
    ): array {
        Log::warning('Paystack connection test failed.', [
            'category' => $category,
            'http_status' => $httpStatus,
            'paystack_message' => is_string($paystackMessage) ? $paystackMessage : null,
        ]);

        return ['ok' => false, 'category' => $category, 'message' => $message];
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;
        $this->settings = SchoolSetting::with('paystackChangedBy')->first();
        $rawSavedSecret = $this->settings?->getRawOriginal('paystack_secret_key');
        $hasSaved = filled($this->settings?->paystack_public_key) || filled($rawSavedSecret);

        if ($hasSaved) {
            $this->source = 'saved';
            try {
                $secret = $this->settings?->paystack_secret_key;
            } catch (DecryptException) {
                $this->unreadable = true;

                return;
            }
            $public = $this->settings?->paystack_public_key;
            $mode = is_string($public) && is_string($secret) ? $this->matchingMode($public, $secret) : null;
            if ($mode !== null) {
                $this->publicKey = $public;
                $this->secretKey = $secret;
                $this->mode = $mode;
            }

            return;
        }

        $public = config('services.paystack.public_key');
        $secret = config('services.paystack.secret_key');
        $mode = is_string($public) && is_string($secret) ? $this->matchingMode($public, $secret) : null;
        if ($mode !== null) {
            $this->source = 'environment';
            $this->publicKey = $public;
            $this->secretKey = $secret;
            $this->mode = $mode;
        }
    }

    private function savedSecret(SchoolSetting $settings): ?string
    {
        if (! filled($settings->getRawOriginal('paystack_secret_key'))) {
            return null;
        }

        try {
            return $settings->paystack_secret_key;
        } catch (DecryptException) {
            return null;
        }
    }

    private function matchingMode(string $publicKey, string $secretKey): ?string
    {
        foreach (['test', 'live'] as $mode) {
            if (str_starts_with($publicKey, 'pk_'.$mode.'_') && str_starts_with($secretKey, 'sk_'.$mode.'_')) {
                return $mode;
            }
        }

        return null;
    }

    private function reset(): void
    {
        $this->loaded = false;
        $this->publicKey = null;
        $this->secretKey = null;
        $this->source = 'none';
        $this->mode = null;
        $this->unreadable = false;
        $this->settings = null;
    }
}
