<?php

return [

    // Scheduler yurak urishi (cache) shu soniyadan eski bo'lsa scheduler "stale" hisoblanadi (har daqiqada yoziladi)
    'scheduler_max_age' => (int) env('HEALTH_SCHEDULER_MAX_AGE', 180),

    // Navbatdagi joblar shu sondan oshsa /health "degraded" (200) qaytaradi
    'queue_backlog_warn' => (int) env('HEALTH_QUEUE_BACKLOG_WARN', 500),
    'reports_backlog_warn' => (int) env('HEALTH_REPORTS_BACKLOG_WARN', 50),

    // Raqamlar (kechikish, navbat uzunligi, yurak urishi yoshi) faqat ichki tarmoqdan yoki shu token bilan
    // (`X-Health-Token` header'i) so'ralganda qaytadi; boshqalarga faqat ok/degraded/fail. Bo'sh = token o'chiq.
    'token' => (string) env('HEALTH_TOKEN', ''),
    // Ichki tarmoq: loopback va xususiy manzillar (Docker tarmog'i, VPN). Mijoz IP'si ishonchli proksilar (bootstrap/app.php)
    // X-Forwarded-For orqali aniqlanadi.
    'internal_networks' => ['127.0.0.0/8', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fc00::/7'],

];
