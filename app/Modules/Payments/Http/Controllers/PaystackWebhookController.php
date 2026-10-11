<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Services\PaymentLedger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentLedger $ledger): Response
    {
        $secret = config('services.paystack.secret_key');
        $signature = $request->header('x-paystack-signature', '');
        if (! is_string($secret) || $secret === '' || ! hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $signature)) {
            return response('Invalid signature', 401);
        }
        $payload = $request->json()->all();
        if (($payload['event'] ?? null) === 'charge.success' && is_string($payload['data']['reference'] ?? null)) {
            try {
                $payment = $ledger->verifyPaystack($payload['data']['reference']);
                if ($payment) {
                    Log::info('Paystack webhook payment verification', [
                        'payment_id' => $payment->id,
                        'newly_settled' => $payment->wasRecentlyCreated,
                    ]);
                }
            } catch (ValidationException) {
                return response('Payment verification rejected', 422);
            }
        }

        return response('OK', 200);
    }
}
