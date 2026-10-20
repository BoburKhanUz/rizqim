<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Services\Push\PushContent;

/**
 * One message in every shape its channels need. A channel without content is simply not used for it.
 */
final class NotificationMessage
{
    /**
     * @param string|null $sms plain text, used only when $critical and no other channel took the message
     */
    public function __construct(
        public readonly NotificationType $type,
        public readonly ?TelegramContent $telegram = null,
        public readonly ?PushContent $push = null,
        public readonly ?string $sms = null,
        public readonly bool $critical = false,
    ) {}
}
