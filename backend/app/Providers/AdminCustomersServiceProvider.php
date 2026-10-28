<?php

namespace App\Providers;

use App\Services\Admin\Attention\AttentionSource;
use App\Services\Admin\Customers\Attention\WaitingComplaintsSource;
use Illuminate\Support\ServiceProvider;

/**
 * P4-31: Boshqaruv markazi → Mijozlar va yordam. Its "Diqqat talab qiladi" source (complaints waiting for the support
 * team longer than support.complaint_waiting_hours). The routes are routes/api_admin_customers.php.
 */
class AdminCustomersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            WaitingComplaintsSource::class,
        ], AttentionSource::TAG);
    }
}
