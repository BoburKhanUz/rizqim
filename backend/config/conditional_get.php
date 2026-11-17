<?php

/*
|--------------------------------------------------------------------------
| Conditional GETs of the polled endpoints (wave M2, App\Http\Middleware\ConditionalGet)
|--------------------------------------------------------------------------
|
| A polled GET answers with an ETag built from the ChangeRevision tokens its body depends on; a repeat request with
| that ETag in If-None-Match is answered 304 without reading the main tables. docs/API.md "Shartli so'rovlar (ETag)",
| docs/OPERATIONS.md.
|
*/

return [

    // false: no ETag is sent and If-None-Match is ignored (every poll is a full read, as before wave M2): the rollback.
    'enabled' => (bool) env('CONDITIONAL_GET_ENABLED', true),

    // Any change of this value invalidates every ETag issued before (clients re-read once). The cached config file's
    // modification time is folded in as well, so a deploy (config:cache at container start) does the same by itself.
    'salt' => (string) env('CONDITIONAL_GET_SALT', ''),

];
