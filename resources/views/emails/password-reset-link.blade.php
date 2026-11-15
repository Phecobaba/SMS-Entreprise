<!doctype html><html lang="en"><body style="font-family:Arial,sans-serif;color:#18333a;line-height:1.5">
<h1 style="font-size:22px">Reset your password</h1>
<p>Hello {{ $recipientName }},</p>
<p>Use the button below to choose a new password. This link works once and expires in {{ $expiresMinutes }} minutes.</p>
<p><a href="{{ $resetUrl }}" style="display:inline-block;padding:12px 18px;background:#2e4f56;color:#fff;text-decoration:none;border-radius:7px">Reset password</a></p>
<p>If you did not ask for this, you can ignore this email and your password will stay the same.</p>
</body></html>
