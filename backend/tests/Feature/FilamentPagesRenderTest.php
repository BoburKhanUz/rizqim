<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Complaint;
use App\Models\District;
use App\Models\PayoutRequest;
use App\Models\PromoCode;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A wrong class or method in a panel resource (e.g. a Filament v3 namespace) only fails once its page is opened.
 */
class FilamentPagesRenderTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $order = $this->makeOrder(OrderStatus::NEW);
        $this->makeOrder(OrderStatus::DELIVERED);

        $region = Region::create(['name_uz' => 'Toshkent shahri', 'is_active' => true]);
        District::create(['region_id' => $region->id, 'name_uz' => 'Chilonzor tumani', 'is_active' => true]);
        Setting::create(['key' => 'sms_driver', 'value' => 'log']);
        PromoCode::create(['code' => 'SMOKE10', 'type' => 'percentage', 'value' => 10, 'is_active' => true]);
        PayoutRequest::create(['store_id' => $this->store->id, 'amount' => 100000, 'card_number' => '8600123412341234', 'status' => 'pending']);
        Complaint::create(['user_id' => $this->customer->id, 'order_id' => $order->id, 'type' => 'OTHER', 'description' => 'Kech keldi', 'status' => 'pending']);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::SUPER_ADMIN->value,
        ]);
        // P4-11: the superadmin panel requires 2FA (without it every page redirects to its set-up)
        $this->superAdmin->saveAppAuthenticationSecret(app(\App\Services\Admin\TwoFactorService::class)->generateSecret());
    }

    public function test_superadmin_districts_list_renders(): void
    {
        $this->actingAs($this->superAdmin)->get('/superadmin/districts')
            ->assertOk()
            ->assertSee('Chilonzor tumani');
    }

    public function test_every_panel_page_renders(): void
    {
        $rendered = [];
        $failures = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (!in_array('GET', $route->methods(), true) || !preg_match('/^filament\.(admin|superadmin)\./', $name)
                || preg_match('/\.auth\.|\.logout$|tenant\.registration/', $name)) {
                continue;
            }

            $parameters = $this->parametersFor($route);
            if ($parameters === null) {
                continue;
            }

            $url = route($name, $parameters);
            $user = str_starts_with($name, 'filament.admin.') ? $this->owner : $this->superAdmin;
            // Each request starts signed in afresh: AuthenticateSession logs out a user whose session belonged to another
            $this->app['auth']->forgetGuards();
            $this->flushSession();
            $response = $this->actingAs($user)->get($url);
            $rendered[] = $url;

            // The tenant panel's root only redirects to the store's dashboard
            $redirectsInside = $response->isRedirect() && !str_contains((string) $response->headers->get('Location'), '/login')
                && !str_contains((string) $response->headers->get('Location'), 'multi-factor-authentication');
            if ($response->getStatusCode() !== 200 && !$redirectsInside) {
                $failures[] = "{$url} => {$response->getStatusCode()} " . ($response->exception?->getMessage() ?? '');
            }
        }

        $this->assertSame([], $failures);
        $this->assertGreaterThanOrEqual(40, count($rendered), 'Too few panel pages were found to render');
    }

    /**
     * Route parameters for a page, or null when it needs a record this test does not have.
     */
    private function parametersFor(RoutingRoute $route): ?array
    {
        $parameters = [];

        foreach ($route->parameterNames() as $parameter) {
            if ($parameter === 'tenant') {
                $parameters['tenant'] = $this->store->uuid;
                continue;
            }

            $page = explode('@', (string) $route->getAction('uses'))[0];
            $model = $parameter === 'record' && method_exists($page, 'getResource') ? $page::getResource()::getModel() : null;
            $record = $model ? $model::query()->first() : null;
            if (!$record) {
                return null;
            }

            $parameters['record'] = $record->getRouteKey();
        }

        return $parameters;
    }
}
