<?php

namespace App\Domain\Integrations;

/**
 * P4-31: the RIZQIM records an ExternalRef can map to an outside id.
 */
enum ExternalRefEntity: string
{
    case BRANCH = 'branch';
    case CATEGORY = 'category';
    case PRODUCT = 'product';
    case VARIANT = 'variant';
    case MODIFIER = 'modifier';
    case ORDER = 'order';
}
