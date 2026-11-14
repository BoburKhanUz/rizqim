| endpoint | L1_lunch_poll_L p50 / p95 | L2_lunch_reports_L p50 / p95 | L3_market_spike_L p50 / p95 | L3_market_spike_L_after p50 / p95 | L5_sync_burst_L p50 / p95 |
|---|---|---|---|---|---|
| GET /catalog?geo | - | - | 6705 / 9175 | 2897 / 3672 | - |
| GET /catalog?store_id | - | - | 6995 / 9110 | 2987 / 3674 | - |
| GET /notifications | - | - | 7448 / 9253 | 2981 / 3630 | - |
| GET /orders | - | - | 7301 / 9190 | 3048 / 3709 | - |
| GET /owner/analytics?period=month | - | 44 / 179 | - | - | - |
| GET /owner/reports/sales | - | 57 / 82 | - | - | - |
| GET /owner/reports/top-products | - | 72 / 94 | - | - | - |
| GET /pos/display/state | 38 / 55 | 39 / 65 | 44 / 8321 | 45 / 3496 | 36 / 50 |
| GET /pos/floor | 57 / 75 | 59 / 82 | 63 / 8424 | 64 / 3547 | 54 / 73 |
| GET /pos/floor (after sync) | - | - | - | - | 58 / 665 |
| GET /pos/orders (after sync) | - | - | - | - | 43 / 408 |
| GET /pos/orders/:id | 60 / 81 | 62 / 89 | 3204 / 8960 | 78 / 3482 | - |
| GET /staff/kitchen/tickets | 61 / 115 | 77 / 144 | 141 / 8813 | 173 / 3600 | 100 / 204 |
| GET /staff/orders | 132 / 168 | 135 / 181 | 157 / 8583 | 138 / 3522 | 119 / 158 |
| GET /staff/orders?status=new | 40 / 55 | 41 / 65 | 55 / 8687 | 46 / 3387 | 39 / 60 |
| GET /stores?geo | - | - | 6443 / 9082 | 2881 / 3639 | - |
| POST /orders | - | - | 7227 / 8586 | 2877 / 3690 | - |
| POST /pos/bills/:id/payments | 204 / 274 | 212 / 283 | 229 / 8651 | 223 / 3505 | - |
| POST /pos/orders | 150 / 200 | 159 / 212 | 168 / 9162 | 169 / 3695 | - |
| POST /pos/orders/:id/bills | 97 / 139 | 101 / 152 | 109 / 8867 | 108 / 3261 | - |
| POST /pos/orders/:id/close | 67 / 99 | 69 / 102 | 75 / 8388 | 72 / 3400 | - |
| POST /pos/orders/:id/rounds | 129 / 172 | 135 / 185 | 138 / 9112 | 140 / 3569 | - |
| POST /pos/orders/:id/send | 116 / 166 | 121 / 172 | 125 / 8707 | 127 / 3436 | - |
| POST /pos/sync | - | - | - | - | 1742 / 2412 |
| _cpu php/pg s, req, stmts/req_ | 318/195, 7578, 27.1 | 342/236, 7985, 27.4 | 425/374, 9958, 23.4 | 510/255, 11508, 23.5 | 262/154, 6058, 29 |
