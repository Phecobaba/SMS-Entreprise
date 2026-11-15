<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Payment gateway</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
@include('core._shell-start')
<main class="oak-dashboard gw-page">
    <div class="oak-dashboard-heading"><div><h1>Payment gateway</h1><p>Paystack keys for online payments</p></div></div>
    @if($locked)
        <div class="oak-locked"><x-icon name="lock" /><h3>Only an Admin can change the payment gateway</h3><p>The Bursar can record bank payments but cannot see or change the Paystack keys. Ask the school Admin.</p></div>
    @else
        @if(session('status'))<div class="oak-notice oak-notice-success" role="status"><div>{{ session('status') }}</div></div>@endif
        @if(session('connection_result'))<div class="oak-notice {{ session('connection_result.ok') ? 'oak-notice-success' : 'oak-notice-danger' }}" role="{{ session('connection_result.ok') ? 'status' : 'alert' }}"><div>{{ session('connection_result.message') }}</div></div>@endif
        @if($gateway['unreadable'])<div class="oak-notice oak-notice-danger" role="alert"><div><strong>The saved keys can't be read. Please enter them again.</strong><p>Online payment is unavailable until new keys are saved.</p></div></div>
        @elseif($gateway['source'] === 'environment')<div class="oak-notice oak-notice-info"><div><strong>Using environment keys.</strong><p>No keys are saved here. Save keys below to manage them from this page; saved keys are used first.</p></div></div>
        @elseif(!$gateway['configured'])<div class="oak-notice oak-notice-info"><div><strong>Online payment isn't set up yet.</strong><p>Parents and students will see this message instead of Pay buttons. Bank teller payments keep working.</p></div></div>@endif
        @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><div><strong>Nothing was saved.</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif

        @if($gateway['configured'])
        <section class="ad-card"><div class="ad-card-head"><h2>Current keys</h2><span class="oak-status-pill {{ $gateway['mode'] === 'live' ? 'oak-status-success' : 'oak-status-warning' }}">{{ ucfirst($gateway['mode']) }} mode</span></div>
            <dl class="gw-kv">
                <dt>Public key</dt><dd class="gw-code">{{ $gateway['source'] === 'saved' ? $gateway['public_key'] : 'Set on the server' }}</dd>
                <dt>Secret key</dt><dd class="gw-code">{{ $gateway['source'] === 'saved' ? $gateway['secret_mask'] : 'Set on the server, not shown' }}</dd>
                <dt>Last changed</dt><dd>@if($gateway['changed_at']){{ $gateway['changed_by'] ?? 'Unknown' }} · {{ $gateway['changed_at']->timezone('Africa/Lagos')->format('D j M Y, H:i') }}@else Not changed here @endif</dd>
            </dl>
            <form method="post" action="{{ route('payments.gateway.test') }}" class="mt-4">@csrf<button class="oak-btn oak-btn-secondary" type="submit">Test connection</button></form>
            <p class="ad-meta mt-2">Makes a read-only request for one transaction record. No money moves.</p>
        </section>
        @endif

        <section class="ad-card"><div class="ad-card-head"><div><h2>{{ $gateway['configured'] ? 'Change the keys' : 'Add your Paystack keys' }}</h2><p>Use a Test pair or a Live pair, never one of each. Confirm with your current password.</p></div></div>
            <form method="post" action="{{ route('payments.gateway.store') }}" class="gw-form">@csrf
                <div class="oak-field"><label for="public_key">Public key</label><input id="public_key" name="public_key" value="{{ old('public_key', $gateway['public_key']) }}" autocomplete="off" required placeholder="pk_test_… or pk_live_…"><span class="oak-hint">Safe to see. Starts with pk_test_ or pk_live_.</span></div>
                <div class="oak-field"><label for="secret_key">Secret key</label><input id="secret_key" type="password" name="secret_key" value="" autocomplete="new-password" placeholder="{{ $gateway['source'] === 'saved' && !$gateway['unreadable'] ? 'Leave blank to keep the saved key' : 'sk_test_… or sk_live_…' }}"><span class="oak-hint">Write-only. Saved encrypted and never shown again.</span></div>
                <div class="oak-field"><label for="current_password">Your current password</label><input id="current_password" type="password" name="current_password" value="" autocomplete="current-password" required></div>
                <button class="oak-btn oak-btn-primary" type="submit">Save keys</button>
            </form>
        </section>

        <section class="ad-card"><div class="ad-card-head"><div><h2>Paystack URLs</h2><p>Use these exact addresses in the matching Test or Live fields in Paystack.</p></div></div>
            @unless($gatewayUrlsArePublic)<div class="oak-notice oak-notice-warning" role="alert"><div><strong>Paystack's servers cannot reach this address.</strong><p>Use a tunnel such as ngrok or Cloudflare Tunnel and set APP_URL to its https address.</p></div></div>@endunless
            <div class="oak-field mt-4"><label for="paystack-callback-url">Callback URL</label><div class="gw-copy"><code id="paystack-callback-url">{{ $callbackUrl }}</code><button class="oak-btn oak-btn-secondary" type="button" data-copy-text="{{ $callbackUrl }}">Copy</button></div></div>
            <div class="oak-field mt-4"><label for="paystack-webhook-url">Webhook URL</label><div class="gw-copy"><code id="paystack-webhook-url">{{ $webhookUrl }}</code><button class="oak-btn oak-btn-secondary" type="button" data-copy-text="{{ $webhookUrl }}">Copy</button></div></div>
            <p class="ad-meta mt-2">Add both URLs in your Paystack dashboard's API Keys &amp; Webhooks settings, using the same Test or Live mode as these keys.</p>
        </section>
        <div class="ad-impact"><div><x-icon name="info-circle" /><span>Changes are logged with who and when, never the values. Other Admins are notified.</span></div><div><x-icon name="alert-triangle" /><span>Keys are encrypted with the application key. If that key is lost or changed, enter the Paystack keys again.</span></div></div>
    @endif
</main>
@include('core._shell-end')
@livewireScripts
</body></html>
