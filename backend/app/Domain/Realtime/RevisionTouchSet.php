<?php

namespace App\Domain\Realtime;

/** The scopes one write touches (ids as keys) */
final class RevisionTouchSet
{
    /** @var array<int, true> */
    public array $branches = [];

    /** @var array<int, true> stores whose every branch is touched (not the store token) */
    public array $storeBranches = [];

    /** @var array<int, true> */
    public array $stores = [];

    /** @var array<int, true> */
    public array $users = [];

    public bool $allStores = false;
}
