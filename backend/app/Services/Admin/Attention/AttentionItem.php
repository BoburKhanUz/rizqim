<?php

namespace App\Services\Admin\Attention;

/**
 * One row of "Bugun → Diqqat talab qiladi": something a platform member should look at now. `kind` groups rows
 * (order_unaccepted, device_offline, fiscal_failed, alert...), `severity` orders them (critical first), `href` is the
 * admin app path the row opens, `since` is when the problem started (ISO 8601).
 */
final class AttentionItem
{
    public function __construct(
        public readonly string $kind,
        public readonly string $severity, // critical | warning | info
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly string $href,
        public readonly ?string $since,
        public readonly ?int $storeId = null,
    ) {}

    /**
     * @return array{kind: string, severity: string, title: string, subtitle: string|null, href: string, since: string|null, store_id: int|null}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'severity' => $this->severity,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'href' => $this->href,
            'since' => $this->since,
            'store_id' => $this->storeId,
        ];
    }
}
