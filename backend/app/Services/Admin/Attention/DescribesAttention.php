<?php

namespace App\Services\Admin\Attention;

/**
 * P4-13: optional for an AttentionSource: names its "Diqqat talab qiladi" group itself (kind, label in Uzbek, the
 * severity of the group when it returns a count without rows). Without it AttentionCollector uses the first row's
 * kind and its own label table.
 */
interface DescribesAttention
{
    public function kind(): string;

    public function label(): string;

    /** critical | warning | info */
    public function severity(): string;
}
