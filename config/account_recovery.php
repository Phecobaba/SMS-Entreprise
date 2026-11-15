<?php

return [
    // Empty enables recovery for every active email-backed account.
    'enabled_permission' => env('PASSWORD_RESET_PERMISSION', ''),
    'expire_minutes' => (int) env('PASSWORD_RESET_EXPIRE_MINUTES', 60),
    'identifier_attempts' => (int) env('PASSWORD_RESET_IDENTIFIER_ATTEMPTS', 5),
    'identifier_decay_seconds' => (int) env('PASSWORD_RESET_IDENTIFIER_DECAY_SECONDS', 60),
    'ip_attempts' => (int) env('PASSWORD_RESET_IP_ATTEMPTS', 5),
    'ip_decay_seconds' => (int) env('PASSWORD_RESET_IP_DECAY_SECONDS', 60),
];
