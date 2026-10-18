<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::firstOrCreate(
    ['phone' => '+998901234567'],
    [
        'name' => 'Test Owner',
        'email' => null,
        'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        'role' => 'owner'
    ]
);

$credentials = [
    'phone' => '+998901234567',
    'password' => 'password123',
];

if (auth()->attempt($credentials)) {
    echo "Login via phone successful!\n";
} else {
    echo "Login via phone failed!\n";
}
