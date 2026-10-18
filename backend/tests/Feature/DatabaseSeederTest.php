<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_seeder_runs(): void
    {
        $this->seed();

        $this->assertSame('log', Setting::where('key', 'sms_driver')->value('value'));
        $this->assertSame('4546', Setting::where('key', 'eskiz_from')->value('value'));
    }
}
