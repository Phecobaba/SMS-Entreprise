@php
    $icon = match ($label) {
        'Dashboard' => 'layout-dashboard',
        'Exams', 'My Exams', 'CBT' => 'device-laptop',
        'Results' => 'report',
        'Academics', 'Classes' => 'school',
        'Promotion' => 'arrow-up',
        'Assignments' => 'clipboard-list',
        'Attendance', 'Alerts' => 'calendar-check',
        'Fees', 'Payments', 'Receipts' => 'wallet',
        'E-Notes' => 'notebook',
        'My profile' => 'user',
        'Notifications' => 'bell',
        'Admissions' => 'plus',
        'Staff HR' => 'user',
        'School Profile' => 'school',
        default => 'info-circle',
    };
@endphp
<x-icon :name="$icon" />
