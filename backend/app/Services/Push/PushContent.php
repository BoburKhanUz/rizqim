<?php

namespace App\Services\Push;

/**
 * What a push says: the notification (title, body), the data the app reads when it is tapped and a collapse key that
 * lets a newer push about the same order replace the older one on the phone.
 */
final class PushContent
{
    /**
     * @param array<string, scalar|null> $data FCM data values are strings; everything is cast to string on the way out
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
        public readonly ?string $collapseKey = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function stringData(): array
    {
        $data = [];

        foreach ($this->data as $key => $value) {
            if ($value !== null) {
                $data[(string) $key] = (string) $value;
            }
        }

        return $data;
    }
}
