<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\AccountRecovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PasswordRecoveryController extends Controller
{
    public function requestForm(): Response
    {
        return $this->privateView('core.forgot-password');
    }

    public function send(Request $request, AccountRecovery $recovery): RedirectResponse
    {
        $data = $request->validate(['identifier' => ['required', 'string', 'max:255']]);
        $result = $recovery->request($data['identifier']);

        return redirect()->route('password.request.confirmation')
            ->with('recovery_message', $result['message']);
    }

    public function confirmation(): Response
    {
        return $this->privateView('core.forgot-password-confirmation', [
            'message' => session('recovery_message', AccountRecovery::GENERIC_MESSAGE),
        ]);
    }

    public function resetForm(string $token, AccountRecovery $recovery): Response
    {
        return $this->privateView('core.reset-password', [
            'token' => $token,
            'valid' => $recovery->token($token) !== null,
        ]);
    }

    public function reset(Request $request, string $token, AccountRecovery $recovery): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $recovery->reset($token, $data['password']);

        return redirect()->route('password.reset.success');
    }

    public function success(): Response
    {
        return $this->privateView('core.reset-password-success');
    }

    private function privateView(string $view, array $data = []): Response
    {
        return response()->view($view, $data)
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store');
    }
}
