<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Services\PaystackCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentGatewayController extends Controller
{
    public function show(Request $request, PaystackCredentials $credentials): View
    {
        $canManage = $request->user()->can('manage_payment_gateway');
        abort_unless($canManage || $request->user()->can('manage_payments'), 403);

        $callbackUrl = $this->appRouteUrl('payments.verify');
        $webhookUrl = $this->appRouteUrl('payments.webhook');

        return view('payments.gateway', [
            'locked' => ! $canManage,
            'gateway' => $canManage ? $credentials->state() : null,
            'callbackUrl' => $callbackUrl,
            'webhookUrl' => $webhookUrl,
            'gatewayUrlsArePublic' => $this->isPublicHttpsUrl($callbackUrl) && $this->isPublicHttpsUrl($webhookUrl),
        ]);
    }

    public function store(Request $request, PaystackCredentials $credentials): RedirectResponse
    {
        $request->merge([
            'public_key' => is_string($request->input('public_key')) ? trim($request->input('public_key')) : $request->input('public_key'),
            'secret_key' => is_string($request->input('secret_key')) ? trim($request->input('secret_key')) : $request->input('secret_key'),
        ]);
        $data = $request->validate([
            'public_key' => ['required', 'string', 'max:255', 'regex:/^pk_(test|live)_[A-Za-z0-9]+$/'],
            'secret_key' => ['nullable', 'string', 'max:255', 'regex:/^sk_(test|live)_[A-Za-z0-9]+$/'],
            'current_password' => ['required', 'string', 'max:255'],
        ], [
            'public_key.regex' => 'Enter a Paystack Test or Live public key.',
            'secret_key.regex' => 'Enter a Paystack Test or Live secret key.',
        ]);
        $credentials->save(
            $data['public_key'],
            $data['secret_key'] ?? null,
            $data['current_password'],
            $request->user(),
        );

        return redirect()->route('payments.gateway.show')->with('status', 'Paystack keys saved. The secret key will not be shown again.');
    }

    public function test(PaystackCredentials $credentials): RedirectResponse
    {
        return redirect()->route('payments.gateway.show')
            ->with('connection_result', $credentials->connectionTest());
    }

    private function isPublicHttpsUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return parse_url($url, PHP_URL_SCHEME) === 'https'
            && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    private function appRouteUrl(string $route): string
    {
        return rtrim((string) config('app.url'), '/').route($route, absolute: false);
    }
}
