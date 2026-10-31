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

        return view('payments.gateway', [
            'locked' => ! $canManage,
            'gateway' => $canManage ? $credentials->state() : null,
            'webhookUrl' => route('payments.webhook'),
        ]);
    }

    public function store(Request $request, PaystackCredentials $credentials): RedirectResponse
    {
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
        return redirect()->route('payments.gateway.show')->with(
            $credentials->testConnection() ? 'connection_ok' : 'connection_failed',
            true,
        );
    }
}
