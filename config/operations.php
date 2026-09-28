<?php

return [
    'alert_email' => env('OPS_ALERT_EMAIL'),
    'alert_cooldown_seconds' => (int) env('OPS_ALERT_COOLDOWN_SECONDS', 900),
    'queue_connection' => env('OPS_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
    'queue_name' => env('OPS_QUEUE_NAME', 'default'),
    'queue_max_jobs' => (int) env('OPS_QUEUE_MAX_JOBS', 100),
    'backup_max_age_hours' => env('OPS_BACKUP_MAX_AGE_HOURS'),
    'backup_status_path' => env('OPS_BACKUP_STATUS_PATH', 'operations/backup-status.json'),
    'rpo_approved' => env('OPS_RPO_APPROVED'),
    'rto_approved' => env('OPS_RTO_APPROVED'),
];
