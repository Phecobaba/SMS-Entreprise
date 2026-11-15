<!doctype html><html lang="en"><body style="font-family:Arial,sans-serif;color:#18333a;line-height:1.5">
<h1 style="font-size:22px">Confirm your email address</h1>
<p>Hello {{ $recipientName }},</p>
<p>Confirm this address to use it for password recovery. The link works once and expires in 60 minutes.</p>
<p><a href="{{ $confirmationUrl }}" style="display:inline-block;padding:12px 18px;background:#2e4f56;color:#fff;text-decoration:none;border-radius:7px">Confirm email address</a></p>
<p>If you did not make this change, contact the school office.</p>
</body></html>
