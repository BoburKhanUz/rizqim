<?php

namespace App\Domain\Integrations;

/**
 * P4-31: a journaled call's state (docs/PHASE5_PLAN.md 4.4): waiting, sent, confirmed by the other side, failed (it will
 * be retried: 1, 2, 4 ... 32 minutes) and dead (out of retries; an alert).
 */
enum IntegrationEventStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case ACKNOWLEDGED = 'acknowledged';
    case FAILED = 'failed';
    case DEAD = 'dead';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Kutmoqda',
            self::SENT => 'Yuborildi',
            self::ACKNOWLEDGED => 'Tasdiqlandi',
            self::FAILED => 'Xato',
            self::DEAD => "O'lik",
        };
    }

    /**
     * Not confirmed by the other side yet: an order push in one of these states for too long is "qotib qolgan".
     *
     * @return list<self>
     */
    public static function unconfirmed(): array
    {
        return [self::PENDING, self::SENT, self::FAILED, self::DEAD];
    }
}
