| endpoint | probe_S p50 / p95 | probe_M p50 / p95 | probe_L p50 / p95 | probe_L_after p50 / p95 |
|---|---|---|---|---|
| GET /catalog?geo | 93 / 115 | 90 / 130 | 120 / 148 | 91 / 120 |
| GET /catalog?store_id | 78 / 95 | 92 / 111 | 100 / 143 | 87 / 100 |
| GET /notifications | 51 / 67 | 77 / 96 | 170 / 211 | 42 / 46 |
| GET /notifications (owner) | 52 / 64 | 97 / 109 | 224 / 323 | 37 / 51 |
| GET /orders | 54 / 72 | 77 / 92 | 87 / 112 | 74 / 95 |
| GET /owner/analytics?period=month | 33 / 148 | 32 / 215 | 36 / 156 | 32 / 151 |
| GET /owner/reports/sales | 52 / 66 | 57 / 63 | 63 / 95 | 51 / 72 |
| GET /owner/reports/top-products | 88 / 120 | 133 / 172 | 79 / 109 | 66 / 75 |
| GET /pos/display/state | 35 / 42 | 34 / 43 | 38 / 54 | 33 / 45 |
| GET /pos/floor | 53 / 62 | 54 / 65 | 61 / 74 | 52 / 64 |
| GET /pos/orders | 40 / 51 | 40 / 50 | 50 / 58 | 45 / 50 |
| GET /pos/orders/:id | 47 / 55 | 47 / 58 | 57 / 79 | 48 / 63 |
| GET /pos/sync/status | 37 / 45 | 41 / 57 | 43 / 55 | 39 / 61 |
| GET /staff/kitchen/tickets | 105 / 131 | 125 / 160 | 187 / 243 | 259 / 301 |
| GET /staff/orders | 124 / 142 | 114 / 144 | 122 / 144 | 103 / 121 |
| GET /staff/orders?status=new | 41 / 46 | 43 / 53 | 49 / 68 | 44 / 52 |
| GET /stores?geo | 44 / 53 | 48 / 96 | 84 / 110 | 66 / 89 |
| POST /pos/bills/:id/payments | 159 / 197 | 174 / 194 | 200 / 267 | 170 / 199 |
| POST /pos/orders | 119 / 135 | 128 / 151 | 143 / 161 | 117 / 223 |
| POST /pos/orders/:id/bills | 84 / 93 | 88 / 107 | 91 / 127 | 86 / 103 |
| POST /pos/orders/:id/close | 59 / 69 | 69 / 85 | 71 / 85 | 60 / 79 |
| POST /pos/orders/:id/send | 89 / 111 | 102 / 115 | 102 / 127 | 100 / 137 |
| _cpu php/pg s, req, stmts/req_ | 14/10, 330, 25.8 | 15/13, 330, 26.1 | 17/22, 330, 26.2 | 17/10, 330, 26.3 |
