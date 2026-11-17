# M2 runs side by side (node scripts/scale-test/m2summary.mjs; compare.mjs)

## L1 lunch + polling, L3 lunch + market spike, L4 market only, P polling only (off-peak)

| run | req | req/s | POS-side req/store/min | orders | php / pg / pgb / redis / reverb+queue+hb CPU s | (php+pg) CPU per order s | php ms/req | pg+pgb ms/req | stmts/req | stmts/order | Redis cmd/s | kB/POS store/min | err (5xx/429/timeout) |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| L1_m1mode_1 | 7596 | 17.57 | 31.9 | 276 | 212 / 56 / 14 / 3.6 / 0.0 | 0.97 | 27.9 | 9.2 | 27.1 | 745 | 206.5 | 335.9 | 0 |
| L1_m1mode_2 | 7654 | 17.65 | 32.1 | 286 | 211 / 55 / 14 / 3.4 / 0.0 | 0.93 | 27.6 | 9.0 | 28.1 | 751 | 207.9 | 397.0 | 0 |
| L1_m2_1 | 10917 | 25.25 | 45.9 | 274 | 274 / 65 / 17 / 5.3 / 7.3 | 1.24 | 25.1 | 7.5 | 23.9 | 951 | 472.2 | 619.3 | 0 |
| L1_m2_2 | 10055 | 23.71 | 43.1 | 284 | 308 / 73 / 19 / 5.6 / 8.0 | 1.34 | 30.7 | 9.1 | 26.5 | 939 | 467.3 | 765.9 | 0 |
| L1_m2_3 | 9951 | 22.86 | 41.6 | 284 | 363 / 77 / 20 / 5.3 / 8.0 | 1.55 | 36.5 | 9.8 | 28.1 | 984 | 452.3 | 1128.6 | 0 |
| L1_m2_debounce2s | 9230 | 21.16 | 38.5 | 279 | 339 / 73 / 19 / 5.2 / 8.0 | 1.47 | 36.7 | 9.9 | 28.1 | 929 | 426.2 | 978.6 | 0 |
| L1_m2sim | 9393 | 21.47 | 39.0 | 276 | 348 / 76 / 19 / 5.3 / 6.4 | 1.53 | 37.0 | 10.1 | 28.6 | 972 | 429.5 | 1065.7 | 0 |
| L1_m2down_1 | 7620 | 17.91 | 32.6 | 280 | 222 / 57 / 14 / 3.7 / 0.0 | 1.00 | 29.2 | 9.3 | 27.5 | 747 | 220.9 | 422.9 | 0 |
| L1_m2down_2 | 7596 | 17.55 | 31.9 | 276 | 237 / 59 / 15 / 3.9 / 0.0 | 1.08 | 31.3 | 9.8 | 27.2 | 750 | 215.7 | 427.7 | 0 |
| L3_m1mode_1 | 13801 | 32.34 | 32.5 | 280 | 379 / 72 / 20 / 6.4 / 0.0 | 1.61 | 27.5 | 6.7 | 19.2 | 949 | 472 | 512.3 | 5 |
| L3_m1mode_2 | 13694 | 31.71 | 31.9 | 273 | 379 / 71 / 19 / 6.3 / 0.0 | 1.65 | 27.7 | 6.5 | 19.2 | 965 | 462.6 | 559.2 | 0 |
| L3_m2_1 | 15844 | 36.24 | 41.5 | 282 | 437 / 87 / 24 / 8.7 / 8.3 | 1.86 | 27.6 | 7.0 | 19.5 | 1097 | 706.1 | 924.1 | 31 |
| L3_m2_2 | 15749 | 36.38 | 41.4 | 273 | 432 / 86 / 23 / 8.3 / 8.1 | 1.90 | 27.4 | 6.9 | 19.5 | 1124 | 704.7 | 1010.5 | 60 |
| L4_m1mode_1 | 17062 | 54.24 | - | 0 | 400 / 35 / 12 / 9.3 / 0.0 | - | 23.4 | 2.7 | 7.3 | - | 936.7 | 0.0 | 5 |
| L4_m1mode_2 | 17111 | 54.69 | - | 0 | 405 / 35 / 12 / 9.3 / 0.0 | - | 23.7 | 2.8 | 7.2 | - | 943.6 | 0.0 | 6 |
| L4_m2_1 | 15779 | 43.86 | - | 0 | 241 / 24 / 9 / 9.4 / 0.8 | - | 15.3 | 2.1 | 5.7 | - | 803.3 | 0.0 | 388 |
| L4_m2_2 | 15819 | 43.97 | - | 0 | 219 / 22 / 8 / 8.6 / 0.7 | - | 13.8 | 1.9 | 5.7 | - | 804.2 | 0.0 | 295 |
| P_m1mode | 5939 | 18.86 | 34.3 | 0 | 176 / 30 / 7 / 2.5 / 0.0 | - | 29.7 | 6.3 | 14.4 | - | 210.4 | 864.4 | 0 |
| P_m2 | 1815 | 5.05 | 9.2 | 0 | 41 / 6 / 2 / 1.4 / 0.9 | - | 22.7 | 4.5 | 9.4 | - | 75.4 | 110.3 | 0 |

| 304 share (n) | L1_m1mode_1 | L1_m1mode_2 | L1_m2_1 | L1_m2_2 | L1_m2_3 | L1_m2_debounce2s | L1_m2sim | L1_m2down_1 | L1_m2down_2 | L3_m1mode_1 | L3_m1mode_2 | L3_m2_1 | L3_m2_2 | L4_m1mode_1 | L4_m1mode_2 | L4_m2_1 | L4_m2_2 | P_m1mode | P_m2 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| GET /catalog/availability | - | - | - | - | - | - | - | - | - | - | - | 0 % (1084) | 0 % (1107) | - | - | 0 % (2984) | 0 % (3002) | - | - |
| GET /catalog?geo | - | - | - | - | - | - | - | - | - | 0 % (1257) | 0 % (1254) | - | - | 0 % (3422) | 0 % (3441) | - | - | - | - |
| GET /catalog?geo&view=cards | - | - | - | - | - | - | - | - | - | - | - | 0 % (703) | 0 % (694) | - | - | 0 % (1746) | 0 % (1729) | - | - |
| GET /catalog?store_id | - | - | - | - | - | - | - | - | - | 0 % (1195) | 0 % (1175) | 4.8 % (1038) | 5.8 % (1054) | 0 % (3363) | 0 % (3369) | 7.3 % (2849) | 7.5 % (2850) | - | - |
| GET /notifications | - | - | - | - | - | - | - | - | - | 0 % (1195) | 0 % (1175) | 84.7 % (1746) | 84 % (1743) | 0 % (3363) | 0 % (3369) | 93.4 % (4953) | 93.4 % (4967) | - | - |
| GET /orders | - | - | - | - | - | - | - | - | - | 0 % (1195) | 0 % (1175) | - | - | 0 % (3363) | 0 % (3369) | - | - | - | - |
| GET /orders?scope=active | - | - | - | - | - | - | - | - | - | - | - | 41.2 % (432) | 38.6 % (430) | - | - | 76 % (1238) | 75.3 % (1245) | - | - |
| GET /pos/display/state | 0 % (1980) | 0 % (1979) | 17.3 % (1469) | 7.1 % (551) | 7.8 % (549) | 9.8 % (570) | 4.5 % (330) | 46.5 % (1980) | 49.9 % (1980) | 0 % (1980) | 0 % (1980) | 9.5 % (577) | 12.1 % (571) | - | - | - | - | 0 % (1980) | 70 % (330) |
| GET /pos/floor | 0 % (1054) | 0 % (1041) | 9.2 % (1710) | 8 % (1709) | 10.4 % (1692) | 13 % (1510) | 10.4 % (1638) | 13.9 % (1015) | 17.1 % (1047) | 0 % (1031) | 0 % (1084) | 8.2 % (1691) | 9.9 % (1799) | - | - | - | - | 0 % (1979) | 40 % (495) |
| GET /pos/orders/:id | 0 % (926) | 0 % (939) | 7.5 % (1637) | 6.5 % (1501) | 7.3 % (1479) | 8 % (1402) | 6 % (1246) | 0.6 % (965) | 0.9 % (933) | 0 % (949) | 0 % (896) | 7.4 % (1501) | 7.3 % (1416) | - | - | - | - | - | - |
| GET /staff/kitchen/tickets | 0 % (660) | 0 % (659) | 30.7 % (1775) | 31 % (1828) | 31.6 % (1811) | 38.1 % (1674) | 33 % (1837) | 7.4 % (660) | 11.4 % (660) | 0 % (660) | 0 % (660) | 31.7 % (1802) | 32.3 % (1781) | - | - | - | - | 0 % (660) | 85 % (660) |
| GET /staff/orders | 0 % (660) | 0 % (660) | 15.1 % (1341) | 15.4 % (1381) | 15.2 % (1358) | 20.9 % (1200) | 13.9 % (1343) | 11.2 % (660) | 13.2 % (660) | 0 % (660) | 0 % (660) | 16.2 % (1362) | 16.1 % (1327) | - | - | - | - | 0 % (660) | 43.6 % (165) |
| GET /staff/orders?status=new | 0 % (660) | 0 % (660) | 14.8 % (1341) | 14.6 % (1381) | 14.9 % (1358) | 19.8 % (1200) | 13.6 % (1343) | 11.2 % (660) | 13.2 % (660) | 0 % (660) | 0 % (660) | 15.4 % (1362) | 15.1 % (1327) | - | - | - | - | 0 % (660) | 40.6 % (165) |
| GET /stores?geo | - | - | - | - | - | - | - | - | - | 0 % (1313) | 0 % (1311) | - | - | 0 % (3485) | 0 % (3494) | - | - | - | - |
| GET /stores?geo&page | - | - | - | - | - | - | - | - | - | - | - | 0 % (843) | 0 % (845) | - | - | 0 % (1949) | 0 % (1962) | - | - |
| all GETs | 0.0 % (5940) | 0.0 % (5938) | 15.9 % (9273) | 15.0 % (8351) | 15.9 % (8247) | 19.7 % (7556) | 16.0 % (7737) | 21.3 % (5940) | 24.0 % (5940) | 0.0 % (12095) | 0.0 % (12030) | 21.3 % (14141) | 21.5 % (14094) | 0.0 % (16996) | 0.0 % (17042) | 36.8 % (15719) | 36.8 % (15755) | 0.0 % (5939) | 62.2 % (1815) |

| run | screens | sockets subscribed | events / heartbeats received | reads: poll / event / heartbeat | events skipped (rev == N) | full (no If-None-Match) polls |
|---|---|---|---|---|---|---|
| L1_m2_1 | 198 | 198/198 | 15300 / 2772 | 1650 / 6023 / 259 | 1195 | 330 |
| L1_m2_2 | 198 | 198/198 | 15849 / 2772 | 1650 / 4823 / 497 | 838 | 330 |
| L1_m2_3 | 198 | 198/198 | 15822 / 2772 | 1650 / 4721 / 518 | 809 | 330 |
| L1_m2_debounce2s | 198 | 198/198 | 15543 / 2772 | 1650 / 4263 / 443 | 714 | 330 |
| L1_m2sim | 198 | 0/0 | 10266 / 0 | 1650 / 4744 / 0 | 0 | 330 |
| L1_m2down_1 | 198 | 0/0 | 0 / 0 | 5280 / 0 / 0 | 0 | 330 |
| L1_m2down_2 | 198 | 0/0 | 0 / 0 | 5280 / 0 / 0 | 0 | 330 |
| L3_m2_1 | 198 | 198/198 | 15759 / 2772 | 1649 / 4764 / 520 | 893 | 330 |
| L3_m2_2 | 198 | 198/198 | 15216 / 2772 | 1650 / 4726 / 518 | 847 | 330 |
| L4_m2_1 | 0 | 0/0 | 0 / 0 | 0 / 0 / 0 | 0 | 0 |
| L4_m2_2 | 0 | 0/0 | 0 / 0 | 0 / 0 / 0 | 0 | 0 |
| P_m2 | 198 | 198/198 | 0 / 2376 | 1650 / 0 / 0 | 0 | 330 |

| run | till+poll p95 before / during / after (ms) | POST /pos/orders p95 (all) | market 2xx+304 during | shed 503 / 429 during | shed % | market ok p50 / p95 during (ms) |
|---|---|---|---|---|---|---|
| L3_m1mode_1 | 112 / 137 / 119 | 132 | 6024 | 5 / 0 | 0.1 | 28 / 113 |
| L3_m1mode_2 | 120 / 145 / 120 | 135 | 6001 | 0 / 0 | 0.0 | 30 / 115 |
| L3_m2_1 | 350 / 485 / 411 | 400 | 5824 | 31 / 0 | 0.5 | 49 / 305 |
| L3_m2_2 | 468 / 469 / 437 | 414 | 5830 | 60 / 0 | 1.0 | 47 / 309 |

| run | market req | req/s | 2xx+304 | 503 / 429 | shed % | php / pg+pgb CPU s | php ms/req | stmts/req | p50 / p95 of each GET (ms) |
|---|---|---|---|---|---|---|---|---|---|
| L4_m1mode_1 | 17062 | 54.24 | 17057 | 5 / 0 | 0.0 | 400 / 47 | 23.4 | 7.3 | /catalog?geo 20/36; /catalog?store_id 26/61; /notifications 18/40; /orders 60/92; /stores?geo 18/43 |
| L4_m1mode_2 | 17111 | 54.69 | 17105 | 6 / 0 | 0.0 | 405 / 47 | 23.7 | 7.2 | /catalog?geo 19/33; /catalog?store_id 26/69; /notifications 18/48; /orders 60/100; /stores?geo 18/45 |
| L4_m2_1 | 15779 | 43.86 | 15391 | 388 / 0 | 2.5 | 241 / 33 | 15.3 | 5.7 | /catalog/availability 27/282; /catalog?geo&view=cards 22/117; /catalog?store_id 64/286; /notifications 15/70; /orders?scope=active 16/69; /stores?geo&page 20/113 |
| L4_m2_2 | 15819 | 43.97 | 15524 | 295 / 0 | 1.9 | 219 / 30 | 13.8 | 5.7 | /catalog/availability 24/274; /catalog?geo&view=cards 20/106; /catalog?store_id 55/283; /notifications 13/51; /orders?scope=active 14/48; /stores?geo&page 17/110 |

## KDS-board adjustment (see RESULTS_M2.md section 1)
```
L1_m1mode_1          kds200=660 cpu=26.0 delta=0.0 php_adj=212 php/order=0.77 (php+pg)/order=0.97 cores/store php=0.01484 pg+pgb=0.00490 rt=0.00000 redis=0.00025 req/store/min=31.9
L1_m1mode_2          kds200=659 cpu=38.7 delta=8.4 php_adj=203 php/order=0.71 (php+pg)/order=0.90 cores/store php=0.01417 pg+pgb=0.00483 rt=0.00000 redis=0.00024 req/store/min=32.1
L1_m2_1              kds200=1230 cpu=31.3 delta=6.5 php_adj=267 php/order=0.98 (php+pg)/order=1.21 cores/store php=0.01873 pg+pgb=0.00575 rt=0.00051 redis=0.00037 req/store/min=45.9
L1_m2_2              kds200=1261 cpu=49.4 delta=29.5 php_adj=279 php/order=0.98 (php+pg)/order=1.24 cores/store php=0.01993 pg+pgb=0.00652 rt=0.00057 redis=0.00040 req/store/min=43.1
L1_m2_3              kds200=1238 cpu=97.6 delta=88.6 php_adj=274 php/order=0.97 (php+pg)/order=1.24 cores/store php=0.01910 pg+pgb=0.00677 rt=0.00055 redis=0.00037 req/store/min=41.6
L1_m2_debounce2s     kds200=1037 cpu=104.6 delta=81.5 php_adj=257 php/order=0.92 (php+pg)/order=1.18 cores/store php=0.01787 pg+pgb=0.00637 rt=0.00056 redis=0.00036 req/store/min=38.5
L1_m2sim             kds200=1231 cpu=91.4 delta=80.5 php_adj=267 php/order=0.97 (php+pg)/order=1.24 cores/store php=0.01850 pg+pgb=0.00657 rt=0.00044 redis=0.00037 req/store/min=39.0
L1_m2down_1          kds200=611 cpu=57.2 delta=19.1 php_adj=203 php/order=0.73 (php+pg)/order=0.93 cores/store php=0.01449 pg+pgb=0.00506 rt=0.00000 redis=0.00026 req/store/min=32.6
L1_m2down_2          kds200=585 cpu=67.1 delta=24.0 php_adj=213 php/order=0.77 (php+pg)/order=0.99 cores/store php=0.01494 pg+pgb=0.00524 rt=0.00000 redis=0.00027 req/store/min=31.9
L3_m1mode_1          kds200=660 cpu=67.7 delta=27.5 php_adj=352 php/order=1.26 (php+pg)/order=1.51 cores/store php=0.02498 pg+pgb=0.00653 rt=0.00000 redis=0.00045 req/store/min=32.5
L3_m1mode_2          kds200=660 cpu=77.7 delta=34.1 php_adj=345 php/order=1.26 (php+pg)/order=1.52 cores/store php=0.02420 pg+pgb=0.00629 rt=0.00000 redis=0.00044 req/store/min=31.9
L3_m2_1              kds200=1230 cpu=77.3 delta=63.1 php_adj=374 php/order=1.32 (php+pg)/order=1.63 cores/store php=0.02590 pg+pgb=0.00769 rt=0.00058 redis=0.00060 req/store/min=41.5
L3_m2_2              kds200=1206 cpu=87.7 delta=74.4 php_adj=357 php/order=1.31 (php+pg)/order=1.62 cores/store php=0.02503 pg+pgb=0.00762 rt=0.00056 redis=0.00058 req/store/min=41.4
P_m1mode             kds200=660 cpu=99.3 delta=48.4 php_adj=128 cores/store php=0.01233 pg+pgb=0.00357 rt=0.00000 redis=0.00024 req/store/min=34.3
P_m2                 kds200=99 cpu=104.2 delta=7.7 php_adj=33 cores/store php=0.00281 pg+pgb=0.00070 rt=0.00007 redis=0.00012 req/store/min=9.2
```

## p50 / p95 per endpoint, L1
| endpoint | L1_m1mode_1 p50 / p95 | L1_m1mode_2 p50 / p95 | L1_m2_2 p50 / p95 | L1_m2_3 p50 / p95 | L1_m2down_1 p50 / p95 | L1_m2down_2 p50 / p95 |
|---|---|---|---|---|---|---|
| GET /pos/display/state | 16 / 24 | 15 / 20 | 48 / 380 | 71 / 528 | 15 / 22 | 16 / 23 |
| GET /pos/floor | 25 / 32 | 24 / 31 | 71 / 263 | 79 / 353 | 25 / 33 | 26 / 35 |
| GET /pos/orders/:id | 30 / 40 | 28 / 38 | 90 / 338 | 99 / 447 | 30 / 41 | 32 / 43 |
| GET /staff/kitchen/tickets | 37 / 64 | 54 / 80 | 118 / 320 | 182 / 458 | 78 / 116 | 93 / 127 |
| GET /staff/orders | 95 / 127 | 85 / 109 | 203 / 371 | 257 / 459 | 84 / 109 | 89 / 118 |
| GET /staff/orders?status=new | 16 / 23 | 15 / 22 | 56 / 253 | 66 / 352 | 16 / 22 | 18 / 24 |
| POST /pos/bills/:id/payments | 130 / 161 | 124 / 154 | 137 / 367 | 139 / 427 | 131 / 163 | 141 / 185 |
| POST /pos/orders | 87 / 112 | 81 / 104 | 90 / 277 | 90 / 398 | 84 / 109 | 90 / 112 |
| POST /pos/orders/:id/bills | 52 / 69 | 49 / 63 | 55 / 289 | 55 / 341 | 51 / 69 | 56 / 72 |
| POST /pos/orders/:id/close | 33 / 44 | 32 / 43 | 34 / 215 | 34 / 216 | 34 / 47 | 36 / 52 |
| POST /pos/orders/:id/rounds | 75 / 100 | 69 / 86 | 79 / 256 | 81 / 382 | 73 / 93 | 77 / 102 |
| POST /pos/orders/:id/send | 67 / 86 | 63 / 81 | 71 / 279 | 75 / 423 | 66 / 91 | 71 / 91 |
| _cpu php/pg s, req, stmts/req_ | 212/56, 7596, 27.1 | 211/55, 7654, 28.1 | 308/73, 10055, 26.5 | 363/77, 9951, 28.1 | 222/57, 7620, 27.5 | 237/59, 7596, 27.2 |

## probe (one client, 15 reps), p50 ms: M1 code (results_m1/probe_fpm-pgb_rep.txt) vs M2 code (probe_m2.txt)

| endpoint | M1 | M2 |
|---|---|---|
| GET /catalog?geo | 71.4 | 19.3 |
| GET /catalog?store_id | 51.9 | 14.8 |
| GET /notifications | 18.6 | 17.6 |
| GET /notifications (owner) | 20.1 | 18.1 |
| GET /orders | 53.5 | 47.8 |
| GET /owner/analytics?period=month | 16.6 | 15 |
| GET /owner/reports/sales | 31.9 | 28.4 |
| GET /owner/reports/top-products | 47.8 | 43.6 |
| GET /pos/display/state | 17.7 | 15.5 |
| GET /pos/floor | 25.9 | 25.3 |
| GET /pos/orders | 21.8 | 21 |
| GET /pos/orders/:id | 24.8 | 25.1 |
| GET /pos/sync/status | 19.4 | 14.1 |
| GET /staff/kitchen/tickets | 142.2 | 42.1 |
| GET /staff/orders | 80.4 | 82.9 |
| GET /staff/orders?status=new | 21.3 | 16.7 |
| GET /stores?geo | 46.5 | 18.8 |
| POST /pos/bills/:id/payments | 105 | 118.9 |
| POST /pos/orders | 72.2 | 78 |
| POST /pos/orders/:id/bills | 48.5 | 49.1 |
| POST /pos/orders/:id/close | 34.8 | 34.8 |
| POST /pos/orders/:id/send | 56.4 | 58 |
