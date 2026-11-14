<?php

namespace App\Domain\Pos\Banquet\Ops;

use App\Domain\Pos\Banquet\BanquetPresenter;
use App\Models\BanquetEvent;

/**
 * P6-54 (E5): the warnings of an event on the production sheet, the contract and the settlement — never a refusal
 * (owner rule). One rule for every screen: the event card's (BanquetPresenter::warnings, P6-51, from the store's
 * BanquetSettings): `guests_over_threshold` — the guests (max of actual, guaranteed, minimum) exceed a threshold
 * (default 200 / 250 / 300; the highest one passed is named); `outside_time_window` — the event starts before the
 * window or ends after it (default 06:00–23:00, the store's local time).
 */
final class BanquetWarnings
{
    public const WARNING = 'array{code: string, message: string}';

    /**
     * @return list<array{code: string, message: string}>
     */
    public function of(BanquetEvent $event): array
    {
        return app(BanquetPresenter::class)->warnings($event);
    }
}
