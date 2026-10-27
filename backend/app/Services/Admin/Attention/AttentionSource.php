<?php

namespace App\Services\Admin\Attention;

use App\Enums\AdminPermission;

/**
 * A provider of "Diqqat talab qiladi" rows (P4-13). Each section registers its own sources in its service provider
 * with `$this->app->tag([...], AttentionSource::TAG)`; the Bugun endpoint asks every tagged source the member may see.
 */
interface AttentionSource
{
    public const TAG = 'admin.attention';

    /** The permission a member needs to see these rows. */
    public function permission(): AdminPermission;

    /**
     * Total count and at most $limit rows, most urgent first.
     *
     * @return array{count: int, items: list<AttentionItem>}
     */
    public function collect(int $limit): array;
}
