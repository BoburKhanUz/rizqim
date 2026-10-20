<?php

return [

    // Fallbacks for setting(): a row in the settings table (Superadmin > Tizim Sozlamalari) takes precedence.
    'sms_driver' => env('SMS_DRIVER'),
    'eskiz_email' => env('ESKIZ_EMAIL'),
    'eskiz_password' => env('ESKIZ_PASSWORD'),
    'eskiz_from' => env('ESKIZ_FROM'),

];
