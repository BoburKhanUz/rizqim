<?php

return [

    // Scheduler yurak urishi (cache) shu soniyadan eski bo'lsa scheduler "stale" hisoblanadi (har daqiqada yoziladi)
    'scheduler_max_age' => (int) env('HEALTH_SCHEDULER_MAX_AGE', 180),

    // Navbatdagi joblar shu sondan oshsa /health "degraded" (200) qaytaradi
    'queue_backlog_warn' => (int) env('HEALTH_QUEUE_BACKLOG_WARN', 500),
    'reports_backlog_warn' => (int) env('HEALTH_REPORTS_BACKLOG_WARN', 50),

];
