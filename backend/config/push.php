<?php

/*
| P1-22: push notifications through Firebase Cloud Messaging (HTTP v1), see docs/PUSH.md. Without credentials the
| feature is simply off: nothing is queued and no request fails because of it.
*/

return [

    // Service account key of the Firebase project: a file path (relative paths are inside the backend folder)...
    'credentials_path' => env('FIREBASE_CREDENTIALS'),

    // ...or the same JSON as base64 (one line, handy for Docker/CI secrets). The JSON wins when both are set
    'credentials_json' => env('FIREBASE_CREDENTIALS_JSON'),

    // Overrides project_id of the key (normally not needed)
    'project_id' => env('FIREBASE_PROJECT_ID'),

    'token_uri' => 'https://oauth2.googleapis.com/token',
    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
    'endpoint' => 'https://fcm.googleapis.com/v1/projects/{project}/messages:send',

    // Seconds; a push never makes anyone wait long (it runs in a queue worker anyway)
    'connect_timeout' => 5,
    'timeout' => 10,

    // Extra attempts inside one job run on 5xx/429/connection errors, and the pause between them in milliseconds
    'http_retries' => 3,
    'http_retry_sleep_ms' => (int) env('PUSH_HTTP_RETRY_SLEEP_MS', 300),

    // Android notification channel the apps create for order messages
    'android_channel_id' => env('PUSH_ANDROID_CHANNEL_ID', 'orders'),

];
