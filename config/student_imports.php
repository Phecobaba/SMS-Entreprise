<?php

return [
    'row_cap' => (int) env('STUDENT_IMPORT_ROW_CAP', 50),
    'seconds_per_row' => (float) env('STUDENT_IMPORT_SECONDS_PER_ROW', 0.5),
    'max_kilobytes' => (int) env('STUDENT_IMPORT_MAX_KILOBYTES', 1024),
    'retention_hours' => 24,
];
