<?php

namespace Tests\Feature;

use Sentry\State\HubInterface;
use Tests\TestCase;

class SentryDisabledTest extends TestCase
{
    public function test_without_a_dsn_nothing_is_sent_and_the_app_works(): void
    {
        $this->assertNull(config('sentry.dsn'));

        $client = app(HubInterface::class)->getClient();
        $this->assertTrue($client === null || $client->getOptions()->getDsn() === null);

        // An exception is still handled normally (JSON 500, no Sentry error)
        \Illuminate\Support\Facades\Route::get('/api/__boom', fn () => throw new \RuntimeException('boom'));
        $this->getJson('/api/__boom')->assertStatus(500);
    }
}
