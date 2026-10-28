<?php

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Http\Controllers\Api\V1\Admin\AdminStoreApplicationController;
use App\Http\Controllers\Controller;
use App\Services\Admin\Stores\ApplicationPipeline;
use App\Traits\ApiResponse;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4-22: the public "become a partner" form (the website, later the bot): no token, no captcha, rate limited per IP
 * (throttle:store-applications; a phone is bound by the one-application-a-day dedupe, not by a request limit) with a honeypot field. The application lands in the Boshqaruv
 * markazi's pipeline (Ovqatxonalar → Ulash jarayoni, "Yangi arizalar" in Bugun).
 */
#[Group('Ovqatxona arizasi', 'Hamkor bo\'lish uchun ariza (sayt va bot).')]
class StoreApplicationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ApplicationPipeline $pipeline,
    ) {}

    /**
     * Leave an application to join RIZQIM
     *
     * `name` (the place), `format` (restaurant, cafe, fast_food, canteen, coffee_shop, bakery, teahouse, catering,
     * other), `source` (website — default — or telegram), `region_id` (GET /locations: an active region) or `city`, `owner_name`, `phone` (+998 XX XXX XX XX, spaces
     * and dashes allowed), `tin` (STIR, 9 digits, optional), `comment` (optional, up to 1000). `website` must stay
     * empty (a hidden field for bots). The same phone within 24 hours is taken once. Limited to 5 an hour and 20 a day
     * per IP address (429); a phone is taken once a day, however many requests name it. Answers 201 without any data about the application.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{received: true}}')]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            ...AdminStoreApplicationController::fieldRules(true),
            'source' => 'nullable|in:website,telegram',
            'website' => 'nullable|string|max:200',
        ], UzValidation::messages());

        // A bot filled the hidden field: answered like everyone, nothing stored
        if (filled($data['website'] ?? null)) {
            return $this->received();
        }

        $source = $data['source'] ?? 'website';
        unset($data['website'], $data['source']);
        $this->pipeline->submit($data, $source, $request->ip());

        return $this->received();
    }

    private function received(): JsonResponse
    {
        return $this->success(['received' => true], "Arizangiz qabul qilindi. RIZQIM jamoasi 1 ish kuni ichida siz bilan bog'lanadi.", 201);
    }
}
