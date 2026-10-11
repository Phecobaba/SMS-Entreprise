@php
    $icon = match ($label) {
        'Dashboard' => 'layout-dashboard',
        'Exams', 'My Exams', 'CBT' => 'device-laptop',
        'Results' => 'report',
        'Assignments' => 'clipboard-list',
        'Attendance', 'Alerts' => 'calendar-check',
        'Fees', 'Payments' => 'wallet',
        'E-Notes' => 'notebook',
        'My profile' => 'user',
        'Notifications' => 'bell',
        default => 'info-circle',
    };
@endphp
<x-icon :name="$icon" />
