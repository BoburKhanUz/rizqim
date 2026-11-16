<?php

/*
|--------------------------------------------------------------------------
| Marketplace read model (wave M2, App\Domain\Marketplace)
|--------------------------------------------------------------------------
|
| The Mini App's browse reads (GET /stores, GET /catalog, GET /catalog/availability without a branch) are answered from
| documents in the cache, not from the transactional tables: one document per area (geo cell, district, region or
| "everywhere") with the store cards, one versioned menu per store and channel, and a short-lived availability
| overlay. Orders never read them: CreateOrderAction prices and checks every line against the live tables.
| docs/OPERATIONS.md, "Market o'qish modeli".
|
*/

return [

    // false: every browse read goes to the database as before wave M2 (the rollback switch)
    'read_model' => (bool) env('MARKETPLACE_READ_MODEL', true),

    // The cache store of the documents; null: the application's default (Redis in production). If it is unreachable,
    // each read is built from the database (slower, never wrong).
    'cache_store' => env('MARKETPLACE_CACHE_STORE'),

    // Geo cell edge in degrees (0.05 = about 5.6 km north-south, 4.2 km east-west in Uzbekistan). A cell holds every
    // store with a branch that may reach some point of it; the exact radius check runs per request.
    'cell_degrees' => (float) env('MARKETPLACE_CELL_DEGREES', 0.05),

    // [fresh, stale] seconds. A document is rebuilt at once when its revision changes (a hook after commit); the time
    // limits only bound how long a change that no hook saw can stay on screen. Inside "fresh" a document is served as
    // is; between fresh and stale it is served once more and rebuilt after the response (one request rebuilds, under
    // a lock); after "stale" it is gone.
    'ttl' => [
        'area' => [60, 600],      // store cards of an area (GET /stores, the feed's store filter)
        'menu' => [300, 3600],    // one store's menu on one channel, and its store card with branches and reviews
        'feed' => [60, 300],      // the product pool of an area (GET /catalog without store_id)
        'global' => [60, 600],    // categories and promo banners
        'availability' => [5, 15], // stop-list, sold out, batches (GET /catalog?store_id, /catalog/availability)
    ],

    // Products kept in an area's feed pool; each request shuffles the pool and shows `feed_limit` of them.
    'feed_pool' => (int) env('MARKETPLACE_FEED_POOL', 120),
    'feed_limit' => 50,

    // GET /stores?page=..: the page size when per_page is not sent, and the most a client may ask for
    'page_size' => 20,
    'page_size_max' => 50,

    // How long one request may hold the rebuild lock of a document, and how long others wait for it on a cold start
    // before building it themselves.
    'lock_seconds' => 10,
    'lock_wait_seconds' => 3,

    /*
    | Store reachability (Toast's rule). OFF unless both this switch is on and the store has its own limit
    | (stores.marketplace_offline_minutes). Then a store with active POS/KDS terminals none of which contacted the
    | server for that many minutes is shown as "not accepting orders now" and checkout refuses its orders. A store
    | without paired terminals is never affected.
    */
    'reachability' => [
        'enabled' => (bool) env('MARKETPLACE_REACHABILITY', false),
        // How long the marketplace remembers a store's terminal contact (checkout always reads it live)
        'cache_seconds' => 30,
    ],

];
