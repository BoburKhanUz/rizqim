<?php

namespace Tests\Feature\AdminMarketing;

use App\Models\Promo;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AdminFixtures;
use Tests\Feature\OrderLifecycleFixtures;

/**
 * P4-31: the admin API fixtures (members, sign-in, step-up) over the order lifecycle store (a customer, a product, a
 * store), plus banners, promo codes and multipart calls.
 */
trait AdminMarketingFixtures
{
    use AdminFixtures;
    use OrderLifecycleFixtures;

    /**
     * A multipart/form-data call (a file with its fields) to the admin API.
     *
     * @param  array<string, mixed>  $data
     */
    protected function adminForm(string $uri, string $token, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($token)->post('/api/v1/admin' . $uri, $data, ['Accept' => 'application/json']);
    }

    protected function png(string $name = 'banner.png', int $width = 600, int $height = 300): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makePromo(array $attributes = []): Promo
    {
        static $sequence = 0;
        $sequence++;

        $promo = new Promo();
        $promo->forceFill(array_merge([
            'title' => "Aksiya {$sequence}",
            'image' => "promos/seed-{$sequence}.png",
            'is_active' => true,
            'sort_order' => $sequence,
        ], $attributes))->save();

        return $promo->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeCode(array $attributes = []): PromoCode
    {
        static $sequence = 0;
        $sequence++;

        $code = new PromoCode();
        $code->forceFill(array_merge([
            'code' => "KOD{$sequence}",
            'type' => 'percentage',
            'value' => '10.00',
            'min_order_amount' => '0.00',
            'is_active' => true,
        ], $attributes))->save();

        return $code->refresh();
    }

    protected function otherCustomer(): User
    {
        return $this->makeCustomer();
    }
}
