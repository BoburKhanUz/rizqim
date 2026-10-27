<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Tests\TestCase;

/**
 * Both Filament panels are branded RIZQIM whatever APP_NAME says (a fresh checkout has Laravel's default there).
 */
class FilamentBrandTest extends TestCase
{
    public function test_both_panels_are_named_rizqim(): void
    {
        foreach (['admin', 'superadmin'] as $id) {
            $this->assertSame('RIZQIM', Filament::getPanel($id)->getBrandName());
        }
    }

    public function test_the_login_pages_show_rizqim_in_the_tab_title_and_logo(): void
    {
        config(['app.name' => 'Laravel']);

        foreach (['/admin/login', '/superadmin/login'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('#<title>[^<]*RIZQIM[^<]*</title>#u', $html, $path);
            $this->assertStringNotContainsString('Laravel', $html, $path);
        }
    }
}
