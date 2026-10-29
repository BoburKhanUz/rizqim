<?php

namespace App\Providers;

use App\Services\Admin\Attention\AttentionSource;
use App\Services\Admin\Integrations\Attention\StuckIntegrationOrdersSource;
use Illuminate\Support\ServiceProvider;

/**
 * P4-31: the Tizim and Integratsiyalar sections. Their "Diqqat talab qiladi" source: orders an outside POS has not
 * confirmed (empty until phase 5 adapters write the integration journal).
 */
class AdminSystemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            StuckIntegrationOrdersSource::class,
        ], AttentionSource::TAG);
    }
}
