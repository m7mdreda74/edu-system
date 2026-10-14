<?php

return [
    'live_session_overdue_buffer_hours' => (int) env('LIVE_SESSION_OVERDUE_BUFFER_HOURS', 2),
    'gamification_enabled' => filter_var(env('LEARNING_GAMIFICATION_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'academic_alerts' => [
        'homework_deadline_days' => (int) env('ACADEMIC_ALERT_HOMEWORK_DEADLINE_DAYS', 2),
        'missing_assignment_days' => (int) env('ACADEMIC_ALERT_MISSING_ASSIGNMENT_DAYS', 14),
        'upcoming_class_hours' => (int) env('ACADEMIC_ALERT_UPCOMING_CLASS_HOURS', 24),
        'recent_absence_days' => (int) env('ACADEMIC_ALERT_RECENT_ABSENCE_DAYS', 3),
        'assessment_window_days' => (int) env('ACADEMIC_ALERT_ASSESSMENT_WINDOW_DAYS', 60),
        'assessment_min_attempts_per_period' => (int) env('ACADEMIC_ALERT_MIN_ATTEMPTS_PER_PERIOD', 2),
        'assessment_decline_threshold' => (int) env('ACADEMIC_ALERT_DECLINE_THRESHOLD', 10),
        'subscription_expiration_days' => (int) env('ACADEMIC_ALERT_SUBSCRIPTION_EXPIRATION_DAYS', 7),
        'dedupe_days' => (int) env('ACADEMIC_ALERT_DEDUPE_DAYS', 7),
    ],
];
