<?php

namespace App\Providers;

use App\Services\Admin\Attention\AttentionSource;
use App\Services\Admin\Customers\Attention\WaitingComplaintsSource;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * P4-31: Boshqaruv markazi → Mijozlar va yordam. Its "Diqqat talab qiladi" source (complaints waiting for the support
 * team longer than support.complaint_waiting_hours). The routes are routes/api_admin_customers.php.
 *
 * `admin-complaint-reply` (review W3): a support reply is a Telegram message to the customer; a member sends at most
 * config('support.complaint_replies_per_minute') (20) a minute.
 */
class AdminCustomersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            WaitingComplaintsSource::class,
        ], AttentionSource::TAG);
    }

    public function boot(): void
    {
        RateLimiter::for('admin-complaint-reply', fn (Request $request) => Limit::perMinute(max(1, (int) config('support.complaint_replies_per_minute', 20)))
            ->by('admin-complaint-reply:' . ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
