<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        if (auth()->user()->role === \App\Enums\UserRole::SUPER_ADMIN) {
            return redirect('/superadmin');
        }
        return redirect('/admin');
    }
    return redirect('/admin/login');
});
