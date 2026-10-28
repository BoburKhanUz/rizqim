<?php

namespace App\Domain\Integrations;

/**
 * P4-31: the outside systems an IntegrationConnection can point at (docs/PHASE5_PLAN.md 3, 4.3). Phase 5 adds adapters
 * for them; adding a provider later is adding a case here.
 */
enum IntegrationProvider: string
{
    case IIKO = 'iiko';
    case ALIPOS = 'alipos';
    case R_KEEPER = 'r_keeper';
    case CLOPOS = 'clopos';
    case POSTER = 'poster';
    case MISON = 'mison';
    case REGOS = 'regos';
    case YANDEX_EATS = 'yandex_eats';
    case UZUM_TEZKOR = 'uzum_tezkor';
    case PARTNER_API = 'partner_api';
    // The fake partner of the test stack (e2e/fake-partner)
    case FAKE = 'fake';

    public function label(): string
    {
        return match ($this) {
            self::IIKO => 'iiko',
            self::ALIPOS => 'Alipos',
            self::R_KEEPER => 'R-Keeper',
            self::CLOPOS => 'Clopos',
            self::POSTER => 'Poster',
            self::MISON => 'Mison',
            self::REGOS => 'REGOS',
            self::YANDEX_EATS => 'Yandex Eats',
            self::UZUM_TEZKOR => 'Uzum Tezkor',
            self::PARTNER_API => 'RIZQIM Partner API',
            self::FAKE => 'Sinov hamkori',
        };
    }
}
