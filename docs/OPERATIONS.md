# Rizqim: ekspluatatsiya (monitoring, loglar, zaxira)

Deploy: `docs/DEPLOY.md`. Bu hujjat ishlab turgan tizimni kuzatish va tiklash haqida. Hamma sozlamalar env orqali; qiymat bo'sh bo'lsa tegishli funksiya o'chiq turadi va ilova odatdagidek ishlaydi.

## 1. Sentry (xatolar monitoringi)

| Qayerda | O'zgaruvchi | Fayl |
|---|---|---|
| Backend (web, queue, scheduler) | `SENTRY_LARAVEL_DSN`, `SENTRY_RELEASE`, `SENTRY_ENVIRONMENT`, `SENTRY_TRACES_SAMPLE_RATE` (standart `0.05`) | `backend/.env` |
| Mini App | `VITE_SENTRY_DSN`, `VITE_SENTRY_RELEASE`, `VITE_SENTRY_ENVIRONMENT`, `VITE_SENTRY_TRACES_SAMPLE_RATE` (standart `0`) | ildizdagi `.env` (build argumenti) |

**Ulash.**
1. sentry.io da ikkita loyiha oching: `rizqim-backend` (PHP/Laravel) va `rizqim-mini-app` (React). Har birining DSN'i alohida.
2. DSN'larni yuqoridagi fayllarga yozing. `SENTRY_RELEASE` va `VITE_SENTRY_RELEASE` ga bir xil deploy versiyasini bering (masalan `git rev-parse --short HEAD`): Sentry xato qaysi deploydan boshlanganini ko'rsatadi.
3. Backend: `docker compose up -d --build backend queue queue-reports scheduler`. Tekshirish: `docker compose exec -u www-data backend php artisan sentry:test` (Sentry'da test xato paydo bo'ladi).
4. Mini App: `VITE_*` qiymatlar **build vaqtida** kiradi: `docker compose up -d --build frontend`. DSN bo'sh bo'lsa SDK bundlega ham tushmaydi.

**Nima yuboriladi.** Ushlanmagan istisnolar (backend: web va navbat joblari; Mini App: `window.onerror`, promise xatolari va error boundary ushlagan render xatolari), release, environment, `X-Request-Id` (teg `request_id`). 4xx/HTTP istisnolar, validatsiya va avtorizatsiya xatolari yuborilmaydi (Laravel ularni hisobot qilmaydi).

**Nima yuborilmaydi.** Foydalanuvchi IP/email/cookie, so'rov tanasi, header'lar. Qolgani `before_send` da yana tozalanadi (`backend/app/Support/SentryScrubber.php`, `customer/miniapp/src/lib/sentry.ts`): `Authorization`, tokenlar, Telegram `initData` (URL `#tgWebAppData=` qismi ham), OTP kodlar, telefon raqamlar, to'lov kalitlari (`CLICK_*`, `PAYME_*`), karta raqamlari (Luhn tekshiruvi bilan). Matn ichidagi (istisno xabari, SQL) shunday qiymatlar ham `[Filtered]` bo'ladi.

**Cheklov.** Mini App'da source map yuklanmaydi, shuning uchun brauzer stack trace'lari minifikatsiyalangan ko'rinishda bo'ladi.

**Tavsiya etilgan ogohlantirishlar (Sentry > Alerts):** yangi xato turi paydo bo'lsa; bir soatda 20 dan ko'p xato bo'lsa; `release` almashgandan keyin xato keskin oshsa.

## 2. Salomatlik tekshiruvi: `GET /health`

Autentifikatsiyasiz va rate-limit'siz: limiter Redis keshida turadi, Redis ishlamay qolsa throttle 500 qaytarib, hisobot (`redis: fail`, 503) ko'rinmay qolardi. Backend portida `/health`, Mini App portida (nginx orqali) `/api/v1/health`: ikkalasi bir xil hisobot.

Ommaga (tashqi so'rovga) faqat umumiy holat va har bir komponent uchun bitta xulosa (`ok` / `degraded` / `fail`) qaytadi, raqamlarsiz:

```json
{"status":"ok","checks":{
  "database":{"status":"ok"},
  "redis":{"status":"ok"},
  "queue":{"status":"ok"},
  "scheduler":{"status":"ok"}}}
```

Batafsil raqamlar (kechikish, navbat uzunligi, scheduler yurak urishi yoshi) **ichki tarmoqdan** (loopback, `10/8`, `172.16/12`, `192.168/16`: Docker tarmog'i, VPN; mijoz IP'si nginx qo'ygan `X-Forwarded-For` bo'yicha aniqlanadi) yoki to'g'ri `X-Health-Token` header'i bilan so'ralganda qaytadi:

```json
{"status":"ok","checks":{
  "database":{"status":"ok","latency_ms":1.4},
  "redis":{"status":"ok","latency_ms":0.6},
  "queue":{"status":"ok","default_backlog":0,"reports_backlog":0},
  "scheduler":{"status":"ok","last_heartbeat_age_seconds":12}}}
```

Token `backend/.env` dagi `HEALTH_TOKEN` (bo'sh = o'chiq; uzun tasodifiy qiymat qo'ying: `openssl rand -hex 24`). Masalan: `curl -H "X-Health-Token: $HEALTH_TOKEN" https://<domen>/api/v1/health`. HTTP kodi hamma uchun bir xil, shuning uchun Docker healthcheck'lar va oddiy uptime monitor (faqat kod yoki `"status":"ok"` kalit so'zi) o'zgarishsiz ishlayveradi.

| `status` | HTTP | Ma'nosi |
|---|---|---|
| `ok` | 200 | Hammasi joyida |
| `degraded` | 200 | Ilova ishlaydi, lekin fon ishi muammoli: scheduler yurak urishi yo'q yoki 180 s dan eski (`stale`/`missing`), navbat uzun (`backlog`: `default` > 500 yoki `reports` > 50) yoki navbat hajmini o'qib bo'lmadi (`unknown`). Ommaviy javobda bu komponentlar `degraded` deb ko'rinadi, aniq sabab (`stale`, `backlog`...) faqat batafsil javobda |
| `fail` | 503 | Baza yoki Redis (Redis ishlatilsa) javob bermayapti: ilova so'rovga xizmat qila olmaydi |

Eslatmalar:
- Javobda parol, versiya, xost yoki istisno matni yo'q; tafsilot faqat logda (`Health: ... check failed`).
- Scheduler har daqiqada `health-heartbeat` vazifasi bilan kesh'ga vaqt yozadi. Deploy paytidagi `cache:clear` dan keyin 1 daqiqagacha `missing` ko'rinishi normal.
- `scheduler` ishlayaptimi: `docker compose exec -u www-data scheduler php artisan health:scheduler` (0 = sog'lom). Shu buyruq `scheduler` konteynerining healthcheck'i.
- Healthcheck'lar (`docker compose ps` da `healthy/unhealthy`): `backend` > `/health` (503 bo'lsa nosog'lom), `scheduler` > `health:scheduler`, `frontend` > `/api/v1/health` (nginx > backend zanjiri), `postgres`, `redis`. Docker nosog'lom konteynerni o'zi qayta ishga tushirmaydi: `docker compose ps` va monitoringga qarang.

## 3. Loglar

- **Format.** Productionda `backend/.env`: `LOG_CHANNEL=stack`, `LOG_STACK=stderr_json`, `LOG_LEVEL=info`. Har qator bitta JSON (`message`, `level_name`, `datetime`, `context`, `extra.request_id`) stderr'ga yoziladi; Docker uni yig'adi: `docker compose logs -f --tail=100 backend queue queue-reports scheduler`. Fayl logi (`storage/logs/laravel.log`) kerak bo'lsa `LOG_STACK=stderr_json,single`.
- **So'rov raqami.** Har javobda `X-Request-Id` header'i bor. Raqamni doim backend o'zi yaratadi (UUID): mijoz yuborgan `X-Request-Id` e'tiborga olinmaydi, shuning uchun loglarga soxta qiymat yozib bo'lmaydi. Istisno: `TRUST_INCOMING_REQUEST_ID=true` bo'lsa, ishonchli proksi (Mini App nginx, u mijozniki o'rniga o'z `$request_id`'sini qo'yadi) bergan xavfsiz qiymat saqlanadi va nginx logi bilan backend logi bir xil raqamga ega bo'ladi; barcha trafik shu nginx orqali kelsagina yoqing. Foydalanuvchi muammo haqida yozsa shu raqamni so'rang va qidiring: `docker compose logs backend | grep <request-id>`. Navbatga qo'yilgan joblar loglarida ham shu `request_id` bo'ladi, Sentry'da esa `request_id` tegi.
- **Docker log hajmi.** Standart `json-file` drayveri o'sishda davom etadi. `/etc/docker/daemon.json` ga `{"log-driver":"json-file","log-opts":{"max-size":"20m","max-file":"5"}}` qo'shing va Docker'ni qayta ishga tushiring (yangi konteynerlar uchun).
- Loglarda telefon, OTP va token bo'lmasligi kerak; yangi `Log::...` yozganda shuni tekshiring.

## 4. Zaxira nusxa va tiklash

**Nima zaxiralanadi.** PostgreSQL (barcha buyurtmalar, moliya, foydalanuvchilar) `pg_dump --format=custom` bilan (siqilgan, `.dump`), yonida `.sha256`. Yuklangan rasmlar (`backend_storage` volume) alohida (pastda). Redis'dagi narsa (kesh, sessiya, navbat) zaxiralanmaydi: tiklash shart emas.

**Avtomatik zaxira.** `backup` xizmati `docker compose up -d` bilan ishga tushadi: har kuni `BACKUP_TIME` (UTC, standart 03:00) da va xizmat ishga tushganda dump oladi, `BACKUP_HOST_DIR` (standart `./backups`) ga yozadi, `BACKUP_RETENTION_DAYS` (14) kundan eskilarini o'chiradi. Sozlamalar ildizdagi `.env` da. Log: `docker compose logs backup`.

**Qo'lda zaxira.** Deploy/migratsiya oldidan ham bitta oling (`backup` xizmati shu konteynerda, bazaga o'zi ulanadi):
```bash
docker compose exec backup sh /usr/local/bin/backup.sh
```

**Server tashqarisiga nusxa (majburiy deb hisoblang): S3-mos xotira.** Server disk'i yo'qolsa shu serverdagi zaxira ham yo'qoladi. `backup` obrazida (`infra/backup/Dockerfile`: `postgres:17-alpine` + qat'iy versiyali `rclone`) tashqi nusxa tayyor: ildizdagi `.env` ga to'rt qiymatni yozsangiz, har bir muvaffaqiyatli dump (va `.sha256`) S3-mos xotiraga (AWS S3, Cloudflare R2, Backblaze B2, MinIO, ...) yuklanadi. To'rttasi ham bo'sh bo'lsa hech narsa qilinmaydi.

| O'zgaruvchi | Ma'nosi |
|---|---|
| `BACKUP_S3_ENDPOINT` | Provayderning S3 manzili, masalan `https://s3.eu-central-1.amazonaws.com` yoki `https://<account>.r2.cloudflarestorage.com` |
| `BACKUP_S3_BUCKET` | Oldindan yaratilgan bucket (skript bucket yaratmaydi) |
| `BACKUP_S3_ACCESS_KEY`, `BACKUP_S3_SECRET` | Faqat shu bucket'ga kalit (`$` belgisiz) |
| `BACKUP_S3_REGION` | Ixtiyoriy (ba'zi provayderlar talab qiladi: `auto`, `eu-central-1`) |
| `BACKUP_S3_PREFIX` | Ixtiyoriy papka, masalan `rizqim/production` |
| `BACKUP_S3_RETENTION_DAYS` | Ixtiyoriy: S3 dagi shu kundan eskilarini skript o'zi o'chiradi (kalitda o'chirish huquqi kerak) |

Yuklash xato bersa (tarmoq, noto'g'ri kalit) mahalliy zaxira saqlanadi va skript `2` kodi bilan tugaydi (`docker compose logs backup` da `XATO: S3 ga yuklash`); to'rt asosiy qiymatdan faqat bir qismi berilsa ham shunday (S3 o'chiq, ogohlantirish chiqadi). Mahalliy aylanish (`BACKUP_RETENTION_DAYS`) va `sha256` tekshiruvi o'zgarmagan. Yoqqandan keyin tekshiring: `docker compose up -d --build backup`, so'ng `docker compose exec backup sh /usr/local/bin/backup.sh` va `docker compose exec backup sh /usr/local/bin/backup.sh --list-remote`.

**S3 dagi eski nusxalarni o'chirish (retention).** Eng xavfsiz yo'l: bucket'ning **lifecycle qoidasi** (masalan, 30–90 kundan keyin o'chirish; AWS S3: Management > Lifecycle rules, R2: Settings > Object lifecycle rules, B2: Lifecycle Settings). Shunda `BACKUP_S3_ACCESS_KEY` ga o'chirish huquqi kerak emas: server buzilsa ham tajovuzkor tashqi nusxalarni o'chira olmaydi (faqat yozish va o'qish huquqi yetadi; ransomware'dan himoya). Bucket versiyalash (versioning) yoqilgan bo'lsa, eski versiyalar uchun ham qoida qo'ying. `BACKUP_S3_RETENTION_DAYS` faqat lifecycle imkoni bo'lmagan provayderlar uchun; u kalitga o'chirish huquqini talab qiladi. Tashqi saqlash muddati mahalliydan uzun bo'lsin (masalan, mahalliy 14 kun, S3 da 60 kun).

**S3 dan tiklash.** Dump'ni olib, odatdagi tartibda tiklang (`restore.sh` `.sha256` ni tekshiradi):
```bash
docker compose exec backup sh /usr/local/bin/backup.sh --list-remote
docker compose exec backup sh /usr/local/bin/backup.sh --fetch rizqim-rizqimbot-<vaqt>.dump   # /backups ga yuklab oladi
docker compose exec -it backup restore.sh /backups/rizqim-rizqimbot-<vaqt>.dump rizqimbot_restore
```

**Muqobil.** `BACKUP_OFFSITE_CMD` har dump'dan keyin `sh -c` bilan **konteyner ichida** ishga tushadi, fayl yo'li `$BACKUP_FILE` da (konteynerda `rclone` bor, lekin o'zingiz sozlashingiz kerak: `RCLONE_CONFIG_*` env yoki fayl); xato bersa zaxira saqlanadi, skript `2` kodi bilan tugaydi. Yoki `BACKUP_HOST_DIR` papkasini serverdagi cron (`rclone`/`rsync`) ko'chirsin. S3 va `BACKUP_OFFSITE_CMD` birga ishlatilsa ikkalasi ham bajariladi.

**Yuklangan rasmlar (storage).**
```bash
docker compose exec -T backend tar czf - -C /var/www/html/storage/app . > backups/storage-$(date +%F).tar.gz
```

### Tiklash (runbook)

`scripts/restore.sh [--yes] [--allow-live] <fayl.dump> <maqsad-baza>`. Fayl `.sha256` bilan tekshiriladi; mavjud baza ustiga yozishda bazaning nomini yozib tasdiqlash kerak; tiklash bitta tranzaksiyada (xato bo'lsa baza o'zgarmaydi).

1. **Avval boshqa nomli bazaga tiklab ko'ring** (ish bazasiga tegmaydi):
   ```bash
   docker compose exec -it backup restore.sh /backups/rizqim-rizqimbot-<vaqt>.dump rizqimbot_restore
   docker compose exec postgres psql -U rizqimbot -d rizqimbot_restore -c 'select count(*) from orders'
   ```
2. **Ish bazasini almashtirish** (faqat haqiqiy avariyada):
   ```bash
   docker compose stop backend queue queue-reports scheduler frontend
   docker compose exec -it backup restore.sh --allow-live /backups/<fayl>.dump rizqimbot
   docker compose up -d
   ```
   `up` deploy qadamlarini (migratsiya, `queue:restart`) o'zi bajaradi. Zaxira koddan eski bo'lsa migratsiyalar yetishmagan qismini qo'llaydi.
3. **Zaxiradan keyingi yo'qotish.** Dump olingandan keyingi buyurtmalar/to'lovlar tiklanmaydi: Click/Payme kabinetidan shu oraliqdagi to'lovlarni solishtiring, kerak bo'lsa qo'lda kiriting. Telegram webhook qayta o'rnatiladi (`up` da avtomatik).

**Oyiga bir marta mashq qiling:** eng yangi dump'ni `..._restore` bazasiga tiklab, jadvallardagi qatorlar sonini ish bazasi bilan solishtiring, so'ng `drop database` qiling. Tiklanmaydigan zaxira zaxira emas.

## 5. Nimani kuzatish kerak

| Nima | Qanday | Ogohlantirish sharti |
|---|---|---|
| Ilova tirikmi | Tashqi uptime monitor (UptimeRobot, Better Stack) `https://<domen>/api/v1/health` ga har 1 daqiqada (raqamlar kerak bo'lsa `X-Health-Token` header'i bilan) | HTTP `200` emas (503 yoki javob yo'q) bo'lsa darhol |
| Fon ishlari | Shu monitorda kalit so'z `"status":"ok"` | 5 daqiqa `ok` bo'lmasa (scheduler/navbat muammosi) |
| Xatolar | Sentry (1-bo'lim) | Yangi xato turi, xatolar keskin oshishi |
| Navbat | `/health` dagi `default_backlog`, `reports_backlog` (ichki tarmoqdan yoki `X-Health-Token` bilan); `failed_jobs` jadvali: `docker compose exec -u www-data backend php artisan queue:failed` | Backlog o'smoqda; `failed_jobs` bo'sh emas |
| Moliyaviy xato | Settlement xatolari `TELEGRAM_SUPERADMIN_CHAT_ID` ga keladi; `php artisan orders:retry-settlements` | Har bir xabar |
| Zaxira yangiligi | `find backups -name '*.dump' -mmin -1560 \| grep -q .` (26 soat ichida dump bor) cron'da | Dump yo'q yoki `docker compose logs backup` da `XATO` |
| Disk | `df -h`, `docker system df`; Postgres volume va `backups/` hajmi | 80% dan oshsa |
| Redis xotira | `docker compose exec redis redis-cli info memory` (parol bo'lsa `-a`) | `used_memory` konteyner xotirasiga yaqinlashsa. `maxmemory-policy` ni `allkeys-*` qilmang (navbat kalitlari o'chadi) |
| Konteynerlar | `docker compose ps` (`unhealthy`, qayta ishga tushish soni) | Har qanday `unhealthy` |
| Real-time (Reverb) | `docker compose ps reverb` (healthcheck `GET /up`); `failed_jobs` dagi `Illuminate\Broadcasting\BroadcastEvent` | `reverb` `unhealthy`; `BroadcastEvent` joblari muvaffaqiyatsiz (6-bo'lim) |
| Sertifikat | Reverse proxy'dagi TLS muddati (Telegram webhook va Mini App faqat HTTPS) | 14 kun qolganda |

**Birinchi yordam.** Hamma narsa to'xtagan bo'lsa: `curl -i https://<domen>/api/v1/health` > `fail` bo'lsa `database`/`redis` qaysi biri; `docker compose ps`; `docker compose logs --tail=200 backend`. Bazani buzgan deploy bo'lsa: kodni qaytaring (`docs/DEPLOY.md`, 7-bo'lim), kerak bo'lsa 4-bo'limdagi tiklash.

## 6. Real-time (Laravel Reverb)

Sozlash va kanallar: `docs/DEPLOY.md`, 9-bo'lim. Reverb ixtiyoriy qatlam: ishlamay qolsa buyurtmalar, to'lovlar va Telegram xabarlari odatdagidek ishlaydi, Mini App esa so'rov (polling) bilan yangilanadi. Shuning uchun u `/health` ga kiritilmagan (backend'ni nosog'lom qilmaydi).

| Holat | Belgisi | Nima qilish |
|---|---|---|
| `reverb` konteyneri to'xtagan / `unhealthy` | Mini App'da yangilanishlar faqat so'rov bilan; DevTools'da `wss://.../app/...` ulanmaydi (502) | `docker compose logs --tail=200 reverb`; `docker compose up -d reverb` |
| Backend Reverb'ga yoza olmayapti | `failed_jobs` da `BroadcastEvent` (`cURL error`, `401`/`Invalid auth signature`; `broadcasts` navbati, bitta urinish), Sentry'da `BroadcastException`. API so'rovlari baribir muvaffaqiyatli tugaydi | Kalitlar backend va `reverb` da bir xilmi (`REVERB_APP_ID`/`KEY`/`SECRET`), `REVERB_HOST`/`PORT` to'g'rimi. Eski hodisalarni qayta yuborish shart emas (mijozlar baribir qayta so'raydi): `php artisan queue:forget <id>` yoki `queue:flush` |
| Obuna `403` | Mini App ulanadi, lekin kanalga qo'shilmaydi | Foydalanuvchining roli, `store_id`, `branch_id` kanalga mos emas (`routes/channels.php`); do'konsiz xodimni `user:assign-role` bilan biriktiring |
| WebSocket tez-tez uzilyapti | DevTools'da qayta ulanishlar | Tashqi proksi `/app/` uchun `Upgrade`/`Connection` va `proxy_read_timeout` (≥ 120 s) ni o'tkazayotganini tekshiring |
| Ekran eski holatda qolyapti, deb shubha (M2 `ETag`) | Klient `304` olyapti, lekin ma'lumot o'zgargan | `backend/.env` da `CONDITIONAL_GET_ENABLED=false`, `docker compose up -d --force-recreate backend`: har so'rov yana to'liq javob. Sababini (yozuv tokenni almashtirmagan jadval) `App\Domain\Realtime\RevisionMap` da toping. Kuzatish: nginx access logda `304` ulushi (`GET /api/v1/pos/floor` va boshqalar, [API.md](API.md) 7.1) |
| Real-time'ni vaqtincha o'chirish | — | Ildiz `.env` da `BROADCAST_CONNECTION=null`, so'ng `docker compose up -d --force-recreate backend queue queue-reports scheduler` |

**M2: o'zgarish tokenlari (`ChangeRevision`).** Har filial, do'kon va mijoz uchun Redis'da bitta raqamli token (`rev:b:{id}`, `rev:s:{id}`, `rev:u:{id}`, cache prefiksi bilan). Ekran ko'rsatadigan jadvalga har yozuv (Eloquent, ommaviy `update`, `DB::table`, pivot) tranzaksiya commit bo'lgach tokenni +1 qiladi: qoidalar `backend/app/Domain/Realtime/RevisionMap.php` da, **sxemadagi har jadval** ro'yxatda bo'lishi shart (`RevisionMapCoverageTest`: yangi migratsiya jadvalni tasniflamasa test yiqiladi). Menyu, stop-list, do'kon/filial sozlamasi do'kon tokenini va uning hamma filialini o'zgartiradi; buyurtma, schyot, to'lov, stol, smena, bron, navbat — filial tokenini va mijozning tokenini. Qurilmaning `last_seen_at` kabi "tiriklik" belgilari tokenni o'zgartirmaydi. Token yo'q bo'lsa (TTL 7 kun, Redis tozalangan) tasodifiy yangisi beriladi: klient bir marta to'liq o'qiydi. Redis ishlamasa har o'qish yangi token (hamma to'liq o'qiydi, eski ekran yo'q; yuk vaqtincha M2 dan oldingidek). Bitta ehtimoliy bo'shliq: Redis yozuvni qabul qilmagan lahzada (o'qish ishlab, yozish xato bergan) token o'zgarmay qoladi — shuning uchun klientlarning xavfsizlik so'rovi (live: 60 s) `If-None-Match` siz yuboriladi (stage B). Ekran eskirgan deb shubha bo'lsa: `CONDITIONAL_GET_ENABLED=false` (yuqoridagi jadval) va `RevisionMap` dagi qoidani tekshiring.

**Heartbeat va sog'liq.** Scheduler har `REALTIME_HEARTBEAT_SECONDS` (30) da `broadcasts` navbatiga `SendRealtimeHeartbeat` qo'yadi; u Reverb'dan band kanallar ro'yxatini oladi va har `pos-branch.*`, `branch.*`, `store.*` kanaliga `realtime.heartbeat` (`{"at","interval","rev"}`) yuboradi. Bu har event yuradigan yo'lning o'zi (scheduler → navbat → ishchi → Reverb), shuning uchun "soketlar ochiq, eventlar kelmaydi" holati ko'rinadi: klient 3 interval (90 s) jim qolsa tez so'rovga o'tadi. Server tomoni: oxirgi muvaffaqiyatli heartbeat `REALTIME_HEARTBEAT_STALE_SECONDS` (120) dan eski yoki `broadcasts` navbati `REALTIME_QUEUE_BACKLOG_THRESHOLD` (500) dan uzun bo'lsa real-time **degraded**: `GET /api/v1/realtime` `live: false` beradi, `reverb` konteyneri `unhealthy` (`php artisan realtime:health`; `REVERB_SCALING_ENABLED` bilan Redis'ni ham tekshiradi). Qo'lda: `docker compose exec -u www-data reverb php artisan realtime:health`. `/health` ga kiritilmagan: real-time ishlamasa hech narsa to'xtamaydi.

**Kanal obunasi keshi.** `POST /api/broadcasting/auth`: shu token shu kanalga ruxsat olgan bo'lsa, `REALTIME_AUTH_CACHE_SECONDS` (60) davomida qayta obuna Redis'dan imzolanadi (baza va token tekshiruvisiz; faqat `pos-branch`, `branch`, `store`, `user`, `courier` kanallari, `platform.ops` emas, rad javobi keshlanmaydi; daqiqasiga 60 ta cheklovi keshdan ham xuddi shu hisobda). Token o'chirilsa, terminal uzilsa, foydalanuvchining roli/do'koni/filiali yoki ruxsat sozlamasi o'zgarsa, eslab qolingan hamma ruxsat commit bilan darhol bekor bo'ladi; boshqa hollarni TTL (60 s) chegaralaydi (ochiq soket esa, avvalgidek, yopilguncha kanalda qoladi). `0` keshni o'chiradi.

**O'lcham (1 000 do'kon).** Do'konga ~7 ulanish (2 kassa, KDS, 3 ofitsiant, mijoz ekrani) + ochiq panel/Mini App: ~7 000–10 000 soket. Bitta Reverb jarayoni (ext-uv) ~10 000 gacha; har soket bitta fayl deskriptori (`nofile` 65536). Heartbeat: band kanal soni / 10 ta HTTP so'rov har 30 s da (1 500 kanal ≈ 5 so'rov/s, `broadcasts` navbatida bitta job). **Reverb qayta ishga tushishi = qayta ulanish to'lqini:** hamma klient bir vaqtda qayta ulanadi va obunani qayta imzolatadi (kesh shu to'lqin uchun). Klientlar: POS ilovalari (`packages/dart/rizqim_api` `Backoff`) 1 s dan 30 s gacha eksponensial, ±20% jitter — birinchi urinish ~1 s da hammada bir vaqtda; Mini App va panel `pusher-js` ning o'z qayta ulanishi (jitter yo'q). Stage B: birinchi qayta ulanishni 0–10 s oralig'iga tarqatish. Reverb'ni ish vaqtida qayta ishga tushirmang.

Loglar: `docker compose logs -f reverb` (ulanishlar sonini ko'rish uchun vaqtincha `php artisan reverb:start --debug`). Hodisalarda shaxsiy ma'lumot yo'q (faqat buyurtma `uuid`, holat va id'lar), shuning uchun Reverb loglari maxfiy emas. Yangi kod bilan Reverb `docker compose up -d --build` da qayta yaratiladi.

**Ko'p ulanish (ext-uv, nofile).** Reverb ReactPHP event loop'ida ishlaydi. Standart `stream_select()` loop bitta jarayonda ~1000 ulanishdan keyin ishdan chiqadi (PHP'ning `FD_SETSIZE` chegarasi), shuning uchun backend obraziga `ext-uv` (libuv, uv-0.3.0; `redis` bilan birga `backend/docker/ext` dagi PECL arxividan yig'iladi, shuning uchun pecl.php.net ishlamay qolsa ham obraz yig'iladi) o'rnatilgan: ReactPHP uni o'zi tanlaydi, kodni o'zgartirish shart emas. Tekshirish: `docker compose exec reverb php -m | grep -x uv` va `docker compose exec reverb php -r 'require "vendor/autoload.php"; echo get_class(React\EventLoop\Loop::get());'` (`React\EventLoop\ExtUvLoop` chiqishi kerak; `StreamSelectLoop` bo'lsa kengaytma yuklanmagan). Har ulanish bitta fayl deskriptori, shuning uchun `reverb` xizmatida `ulimits.nofile` = 65536 (`docker-compose.yml`); hisob: `docker compose exec reverb sh -c 'ls /proc/1/fd | wc -l'`. Bitta jarayon ~10 000 ulanishgacha mo'ljallangan; undan ko'pi uchun Reverb'ni gorizontal masshtablash kerak (`REVERB_SCALING_ENABLED`, Redis pub/sub: `config/reverb.php`) va tashqi proksida `worker_connections`/`ulimit -n` ni oshirish. Obrazni qayta yig'ing (`docker compose build --no-cache backend`), so'ng `docker compose up -d`.


## 7. PostgreSQL versiyasi (17)

Hamma joyda bir xil major versiya: `docker-compose.yml` (`postgres`, `backup`: `postgres:17-alpine`), `infra/backup/Dockerfile` va CI (`postgres:17`). `pg_dump` server versiyasidan eski bo'lmasligi kerak, shuning uchun `backup` obrazi har doim `postgres` bilan bir xil major versiyada bo'lsin; versiyani oshirsangiz to'rttasini birga o'zgartiring.

**Mavjud deployment (16 yoki undan eski) 17 ga o'tishi.** Docker `postgres_data` volume'idagi fayllar major versiyalar orasida mos emas: eski volume bilan `postgres:17` ishga tushmaydi (`database files are incompatible with server`). `docker compose pull`/`up --build` dan **oldin** quyidagini bajaring (dump/restore; ma'lumot hajmi kichik va o'rta bo'lsa eng sodda va ishonchli yo'l):

```bash
# 1. Eski versiya ishlayotganda: yangi zaxira + to'liq SQL dump (server tashqarisiga ham nusxa oling)
docker compose exec backup sh /usr/local/bin/backup.sh
docker compose stop backend queue queue-reports scheduler reverb frontend      # yozuvlar to'xtasin
docker compose exec -T postgres sh -c 'pg_dumpall -U "$POSTGRES_USER"' > pg-upgrade-$(date +%F).sql
# 2. Eski volume'ni ARXIVLANG (o'chirmang), yangisini 17 bilan yarating
docker compose down
docker volume ls | grep postgres_data                        # nomi: <loyiha>_postgres_data
docker run --rm -v <loyiha>_postgres_data:/v -v "$PWD":/b alpine tar czf /b/postgres_data-16.tar.gz -C /v .
docker volume rm <loyiha>_postgres_data
# 3. Kodni yangilab (postgres:17-alpine), faqat postgres'ni ko'taring va dump'ni yuklang
git pull
docker compose up -d postgres
docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d postgres' < pg-upgrade-<sana>.sql
# 4. Hammasini ko'taring va tekshiring
docker compose up -d --build
curl -fsS http://127.0.0.1:8000/health
```

`pg_dumpall` yuklashda "role ... already exists" va "database ... already exists" xatolari normal (rol va baza `POSTGRES_USER`/`POSTGRES_DB` bilan oldindan yaratilgan, qolgan hammasi yuklanadi); boshqa xatolar bo'lsa to'xtating va dump'ni tekshiring.

Katta bazada `pg_upgrade` (`--link`) tezroq, lekin ikkala versiyaning binarlari bir muhitda kerak (masalan `tianon/postgres-upgrade:16-to-17` obrazi); bunda ham avval to'liq zaxira oling va birinchi marta staging'da sinang. Yuqoridagi `pg_dumpall` yo'li uchun 5–15 daqiqalik to'xtash rejalashtiring (hajmga qarab). 17 dan keyin: `docker compose exec postgres psql -U <user> -d <baza> -c 'ANALYZE'` (statistika `pg_dumpall` bilan ko'chmaydi). Migratsiyalar va testlar versiyaga bog'liq emas (standart SQL va `CREATE INDEX CONCURRENTLY`; CI 17 da yuradi). Orqaga qaytish: arxivlangan `postgres_data-16.tar.gz` ni qaytarib, kodni eski commit'ga o'tkazing.

## 8. Feature flaglar (Laravel Pennant)

Yangi imkoniyatni hamma restoranga birdan emas, avval bitta restoranda yoqish uchun. Flaglar bitta joyda e'lon qilinadi: `backend/app/Features/Features.php` (`FLAGS` ro'yxati: nom, tavsif, standart qiymat). Qiymatlar `features` jadvalida (Pennant `database` drayveri), **restoran (Store) bo'yicha**; restoranga alohida qiymat berilmagan bo'lsa flagning standart qiymati ishlaydi.

- **Yoqish/o'chirish:** boshqaruv markazi, Tizim → Tizim sozlamalari → **Funksiyalar** — restoranlar jadvali, har flag uchun tugma (sabab va 6 xonali kod bilan, jurnalga yoziladi); "Standartga qaytarish" restoranning qo'lda qiymatini o'chiradi.
- **Mijozlar uchun:** `GET /api/v1/features` (kirgan foydalanuvchining restorani bo'yicha) `{"success":true,"data":{"features":{"new-checkout-copy":false}}}` qaytaradi; yo'q flag = o'chiq deb hisoblang.
- **Kodda:** `Feature::active('nom')` (foydalanuvchining restorani bo'yicha) yoki `Feature::for($store)->active('nom')`. Yangi flag: `Features::FLAGS` ga qator qo'shing. Flag to'liq yoyilgach uni ro'yxatdan va kod tekshiruvlaridan olib tashlang, `php artisan pennant:purge nom` bilan jadvaldagi qiymatlarni tozalang.
- Pennant qiymatni birinchi tekshiruvda hisoblab jadvalga yozadi: kodda **standart qiymatni** keyin o'zgartirsangiz, allaqachon yozilgan restoranlarga ta'sir qilmaydi (`pennant:purge nom` yoki markazda "Standartga qaytarish").

## 9. Tokenlarni tozalash

Eski tokenlarni scheduler `auth:prune-tokens` buyrug'i bilan o'zi tozalaydi (u POS/KDS terminal tokenlarini o'chirmaydi). **`php artisan sanctum:prune-expired` ni qo'lda ishga tushirmang:** u `SANCTUM_TOKEN_EXPIRATION` dan eski barcha tokenlarni, shu jumladan terminal tokenlarini o'chiradi va har bir POS/KDS terminal ulanishdan chiqib, qayta ulash kodini talab qiladi. Token muddatlari (`AUTH_*`) `backend/.env.example` va `docs/AUTH.md` da.

## 10. Boshqaruv markazi: birinchi superadmin va yo'qolgan 2FA (P4-11)

**Birinchi superadmin** (yoki boshqa jamoa a'zosi) serverda yaratiladi:

```bash
cd backend
php artisan rizqim:admin:create boss@rizqim.uz "Bosh admin"            # superadmin
php artisan rizqim:admin:create op@rizqim.uz "Navbatchi" --role=operator
```

Buyruq bir martalik taklif havolasini chiqaradi (`{ADMIN_APP_URL}/invite/...`, 72 soat). Uni egasiga xavfsiz
yo'l bilan bering: havolani ochgan odam parol (kamida 12 belgi) qo'yadi va Google Authenticator'da 2FA ni yoqadi
(24 soat ichida, pastda).
Havola faqat bir marta ko'rsatiladi; yo'qolsa, boshqa superadmin Tizim → Jamoa'da "Taklifni qayta berish" qiladi.
Keyingi a'zolarni superadmin markazning o'zidan taklif qiladi. Yaratish jurnalga `admin.cli.member_created` bo'lib
yoziladi. `ADMIN_APP_URL` `.env` da admin ilova manzili bo'lishi kerak.

**2FA o'rnatish oynasi.** 2FA'si yo'q a'zo uni faqat **24 soatlik oyna** ichida o'rnatadi: oynani taklifni qabul
qilish yoki 2FA'ni tiklash ochadi, 2FA yoqilganda yopiladi (`users.two_factor_setup_allowed_until`). Oynadan tashqarida
to'g'ri parolning o'zi hech narsa ochmaydi: markaz `403 setup_not_allowed` qaytaradi. Sabab: parolni topib olgan odam o'z telefonidagi ilovani hisobga ulab
olmasin. Oyna o'tib ketsa — taklifni qayta berish yoki 2FA'ni qayta tiklash.

**Mavjud superadmin'lar (eski `super_admin`) va 2FA'siz a'zolar.** Ular jamoaga avtomatik o'tgan, lekin endi faqat
parol bilan 2FA o'rnatolmaydi. Deploydan keyin ro'yxatni oling va har biriga yangi taklif havolasi bering:

```bash
php artisan rizqim:admin:invite --without-2fa                # 2FA'siz faol a'zolar ro'yxati
php artisan rizqim:admin:invite boss@rizqim.uz --reason="2FA ni yoqish"
```

Havola (72 soat, bir marta ko'rsatiladi) yangi parol qo'ydiradi, a'zoning barcha sessiyalarini yopadi va 2FA
o'rnatishga olib boradi (24 soat ichida). Uni egasiga xavfsiz yo'l bilan bering; jurnalga `admin.cli.member_reinvited`
yoziladi. Boshqa superadmin markazda Tizim → Jamoa → "2FA ni tiklash" ni ham bosishi mumkin: u ham 24 soatlik oynani
ochadi (a'zo eski paroli bilan kiradi). Shu buyruq unutilgan parol uchun ham (2FA'si bor a'zoda 2FA o'zgarmaydi).

**Telefon va zaxira kodlari yo'qolgan bo'lsa:** avval boshqa superadmin Tizim → Jamoa → "2FA ni tiklash" qiladi.
Hech kim qolmagan bo'lsa (yagona superadmin), serverda:

```bash
php artisan rizqim:admin:reset-2fa boss@rizqim.uz --reason="Telefon yo'qoldi"
```

Bu 2FA kaliti va zaxira kodlarini o'chiradi, a'zoning barcha sessiyalarini yopadi, 24 soatlik o'rnatish oynasini ochadi
va jurnalga `admin.cli.2fa_reset` (sabab bilan) yozadi. A'zo shu 24 soat ichida kirib, 2FA ni qaytadan o'rnatadi. Buni
faqat egasining shaxsini boshqa yo'l bilan (qo'ng'iroq, uchrashuv) tekshirgandan keyin qiling.

**Kirishni bloklash.** Bitta hisob + bitta manzil (IPv6 da butun `/64` tarmoq) dan 5 ta noto'g'ri parol yoki kod —
15 daqiqa; hisob bo'yicha barcha manzillardan jami 15 ta noto'g'ri parol (yoki 15 ta kod) — hisob 15 daqiqaga hamma
joydan bloklanadi (to'g'ri parol bilan ham). Qo'lda
ochish kerak bo'lsa: kesh (Redis) dagi `admin-login:*` kalitlari. Jamoa a'zosiga tegishli bo'lmagan email bilan
urinishlar jurnalga emas, ilova logiga yoziladi (daqiqasiga ko'pi bilan 30 qator, emailsiz).

**Backend porti.** `BACKEND_PORT` standart bo'yicha faqat `127.0.0.1` da ochiladi (`BACKEND_BIND`, ildiz `.env`):
`/up` (va kerak bo'lsa `/health`) ni shu xostdagi tashqi proksi oladi; veb-panel backend'da yo'q. Backend ichki tarmoqdan kelgan `X-Forwarded-For`
ga ishonadi, shuning uchun port internetga ochiq bo'lsa (Docker userland proksisi orqali, masalan IPv6 so'rovlar)
kishi IP'sini soxtalashtirib bloklashni aylanib o'tardi. Proksi boshqa xostda bo'lsa `BACKEND_BIND` ni o'zgartiring va
portni firewall bilan yoping.

Jurnal (`audit_logs`) o'zgarmaydi: PostgreSQL trigger yozuvni yangilash va o'chirishni rad etadi. Zaxiradan
tiklash (`pg_restore`) bunga ta'sir qilmaydi.

## 11. Boshqaruv markazi: monitoring va ogohlantirishlar (P4-15)

Boshqaruv markazining **Monitoring** bo'limi (`docs/guides/boshqaruv-markazi.md`, "Monitoring") bu hujjatdagi tekshiruvlarni
bitta joyga yig'adi va ularga ogohlantirish qoidalarini qo'shadi.

- **Ops guruhi.** `backend/.env` da `TELEGRAM_OPS_CHAT_ID` (bo'sh bo'lsa `TELEGRAM_SUPERADMIN_CHAT_ID`) — ogohlantirishlar
  ochilganda va hal bo'lganda xabar boradigan guruh. Botni guruhga qo'shing. Bir xil muammo `MONITORING_TELEGRAM_COOLDOWN`
  (15) daqiqada bir martadan ko'p, jami soatiga `MONITORING_TELEGRAM_HOURLY_LIMIT` (20) tadan ortiq yuborilmaydi.
- **Qoidalar har daqiqada tekshiriladi** (`EvaluateAlertsJob`, scheduler). Chegaralarni markazdan o'zgartirasiz (Monitoring →
  Qoidalar), env kerak emas. Scheduler butunlay to'xtasa qoidalar ham to'xtaydi: shuning uchun 5-bo'limdagi tashqi uptime
  monitor (`/health`) baribir kerak.
- **Zaxira vaqti.** `scripts/backup.sh` tekshirilgan dump'dan va (sozlangan bo'lsa) tashqi nusxadan (`BACKUP_OFFSITE_CMD`, S3)
  **keyin** `monitoring_markers` jadvaliga (`key = backup`) vaqt yozadi (`psql` orqali, `backup` konteynerida bor). Tashqi nusxa
  xato bersa mahalliy dump saqlanadi, skript `2` kodi bilan tugaydi, lekin vaqt **yozilmaydi**: belgi eskirib, 26 soatdan keyin
  "Zaxira nusxa eskirgan" ogohlantirishi chiqadi (log: `tashqi nusxa bajarilmadi`). Vaqtni yozib bo'lmasa zaxira baribir
  saqlanadi, logda "OGOHLANTIRISH" chiqadi. Yangilangandan keyin `docker compose up -d --build backup` qiling; migratsiya bajarilgan bo'lishi kerak. Shundan
  keyin Holat → "Zaxira nusxa" va "Zaxira nusxa eskirgan" qoidasi (26 soat) ishlaydi.
- **Versiya va Sentry havolasi.** Build vaqtida `APP_VERSION` va `APP_COMMIT` (masalan `git rev-parse --short HEAD`), Sentry
  loyihasi manzili `SENTRY_PROJECT_URL`. Bo'sh bo'lsa versiya o'rniga `SENTRY_RELEASE` ko'rsatiladi.
- **Yiqilgan vazifalar** shu yerdan ham ko'riladi, qayta uriladi va o'chiriladi (`php artisan queue:failed` o'rniga); har amal
  harakatlar jurnaliga yoziladi. Maxfiy qiymatlar yashiriladi.
- **Real-time.** Ogohlantirishlar `platform.ops` kanalida (`ops.alert.*`). Reverb ishlamasa sahifa 30 soniyada yangilanadi.

## 12. Bazaga ulanishlar puli (PgBouncer)

**Nima uchun.** Har PHP so'rovi Postgres'ga yangi ulanish ochardi: bitta ulanish ~20 ms va Postgres protsessorining ~90 %
(`docs/scale/RESULTS.md`, 1-topilma). O'lchov (2026-11-15, 4 yadroli umumiy mashina, `pgbench -C`, `select 1`, 4 mijoz):

| Yo'l | Ulanish ochish | tps (ulanish bilan) |
|---|---|---|
| To'g'ridan-to'g'ri Postgres (SCRAM) | 20,3 ms | 194 |
| PgBouncer, mijoz SCRAM bilan | 20,3 ms | 172 |
| **PgBouncer, mijoz md5 bilan (yetkazilgan rejim)** | **0,35 ms** | **7 627** |
| Ochiq ulanishda so'rov: to'g'ridan-to'g'ri / PgBouncer orqali | — | 0,023 ms / 0,15 ms bir so'rov |

Ya'ni 20 ms ning asosiy qismi parolni SCRAM bilan tekshirish (PBKDF2), shuning uchun php-fpm → PgBouncer qismida `md5`
(faqat ichki compose tarmog'ida, port tashqariga ochilmagan), PgBouncer → Postgres esa SCRAM (har server ulanishi uchun
bir marta). Pul har so'rovga ~0,13 ms qo'shadi (Mini App so'rovida 13–27 SQL: +2–4 ms), 20 ms ni olib tashlaydi.

**Kim pul orqali, kim to'g'ridan-to'g'ri.**

| Jarayon | Ulanish |
|---|---|
| `backend` konteyneridagi php-fpm (barcha HTTP so'rovlar), shu konteynerdagi `docker compose exec backend php artisan ...` | PgBouncer (`DB_POOLER=pgbouncer`) |
| `migrate` (entrypoint, `DB_POOLER=direct`), `queue`, `queue-reports`, `scheduler`, `reverb`, `backup` (`pg_dump`) | to'g'ridan-to'g'ri `postgres:5432` |

Tranzaksiya rejimida server ulanishi so'rovga faqat bitta tranzaksiya davomida beriladi. Shuning uchun:
- PHP nomli prepared statement yaratmaydi (`PGSQL_ATTR_DISABLE_PREPARES`, `config/database.php`): har so'rov parametrlari
  bilan bitta `PQexecParams` chaqiruvida ketadi, turlarni server odatdagidek aniqlaydi. `ATTR_EMULATE_PREPARES` tanlanmadi:
  u qiymatlarni SQL matniga qo'yadi va Laravel'ning `where('flag', true)` (→ `boolean = 1`) xato beradi (sinab ko'rildi).
  Nomli prepared statement'lar pul orqali `prepared statement "pdo_stmt_…" does not exist` beradi (8 jarayonda 2 400
  so'rovdan 1 400+ xato; o'chirilgan rejimda 0).
- Sessiya holati ishlatilmaydi: sessiya advisory qulflari (faqat `pg_advisory_xact_lock`), `LISTEN/NOTIFY`, sessiya `SET`,
  vaqtinchalik jadvallar, `WITH HOLD` kursorlar, `PDO::ATTR_PERSISTENT`. Tekshiruv (2026-11-15): kodda yo'q; Laravel'ning
  ulanishdagi `set search_path to public` hamma mijozda bir xil, shuning uchun zararsiz. `tests/Unit/TransactionPoolingSafetyTest.php`
  yangi shunday kod qo'shilsa yiqiladi. Sessiya holati kerak bo'lgan ish (masalan uzoq ma'lumot tuzatish) `queue`
  konteynerida bajariladi: `docker compose exec -u www-data queue php artisan ...`.

**O'lchamlar (env, ildiz `.env`).** `PGBOUNCER_POOL_SIZE` (20) — Postgres'ga doimiy ulanishlar; `PGBOUNCER_RESERVE_POOL_SIZE`
(5) — navbat 3 s dan uzoq kutsa qo'shimcha; `PGBOUNCER_MAX_DB_CONNECTIONS` (30) — Postgres'ga qat'iy yuqori chegara;
`PGBOUNCER_MAX_CLIENT_CONN` (1000) — php-fpm ulanishlari; `PGBOUNCER_QUERY_WAIT_TIMEOUT` (30 s) — bo'sh ulanish kutib
turgan so'rov shundan keyin xato oladi. Staging: 15 / 5 / 20.

Hisob (production, `max_connections = 100`):
- Postgres'ga: `PGBOUNCER_MAX_DB_CONNECTIONS` (30) + to'g'ridan-to'g'ri (~15: queue 1, queue-reports 1, scheduler va u
  ishga tushirgan buyruqlar ≤ 6, reverb 1, backup 1, migratsiya 1, qo'lda `psql`/`artisan` 3–4) + 3 superuser zaxirasi = **48 < 100**.
- php-fpm tomoni: har konteynerda `FPM_CRITICAL_CHILDREN + FPM_MARKET_CHILDREN + FPM_DEFAULT_CHILDREN` (standart 4+4+4 = 12)
  ulanish; konteynerlar soni × 12 ≤ `PGBOUNCER_MAX_CLIENT_CONN` (1000). Ishchilarni ko'paytirish uchun `max_connections` ni
  oshirish shart emas, lekin **ishchilarni erkin qo'shib bo'lmaydi**: uchala php-fpm puli PgBouncer'ning bitta pulini
  (`PGBOUNCER_POOL_SIZE` ta server ulanishi) bo'lishadi. Har ishchiga bir vaqtda ko'pi bilan bitta ulanish kerak, shuning
  uchun **konteynerlar soni × (uchala pul ishchilari yig'indisi) ≤ `PGBOUNCER_POOL_SIZE`** bo'lishi shart: aks holda bazada
  sekinlashgan bozor so'rovlari barcha ulanishlarni olib, kassa so'rovi bo'sh ulanish kutib qoladi (sinovda pul 4, ishchilar
  12: kassaning `/health` i 9 s kutdi; pul 12: 0,05 s). Backend konteyneri ishga tushganda buni o'zi tekshiradi
  (`backend/docker/pools.sh`; compose `PGBOUNCER_POOL_SIZE` ni backend'ga ham beradi) va shart buzilsa ishga tushmaydi. Bir
  nechta backend konteyneri bo'lsa, ko'paytmani o'zingiz hisoblang. `PGBOUNCER_POOL_SIZE` ni Postgres yadrolariga qarab
  (taxminan 2–4 × yadro) tanlang; u `PGBOUNCER_MAX_DB_CONNECTIONS` dan oshmasin (pgbouncer konteyneri ham tekshiradi).
- TLS: PgBouncer'da php-fpm tomonga TLS yo'q (xususiy compose tarmog'i), shuning uchun `DB_POOLER=pgbouncer` bilan
  `DB_SSLMODE=require`/`verify-ca`/`verify-full` ishlamaydi: backend bunday sozlama bilan darhol xato beradi. Bazaga TLS kerak
  bo'lsa (masalan boshqarilgan Postgres): `DB_SSLMODE=prefer` (PgBouncer → Postgres ulanishi alohida) yoki `DB_POOLER=direct`.
- `PGBOUNCER_MAX_DB_CONNECTIONS` ni oshirsangiz: `PGBOUNCER_MAX_DB_CONNECTIONS + 15 + 3 < max_connections` bo'lsin.

**Kuzatish.** `docker compose exec pgbouncer sh -c 'PGPASSWORD="$DB_PASSWORD" psql -h 127.0.0.1 -p 6432 -U "$DB_USERNAME" pgbouncer -c "show pools" -c "show stats"'`:
`cl_waiting` doimiy 0 dan katta yoki `maxwait` bir necha soniya bo'lsa — pul kichik yoki so'rovlar sekin (avval sekin SQL'ni
qidiring, keyin `PGBOUNCER_POOL_SIZE`). Logda `query_wait_timeout` yoki `no more connections allowed` — xuddi shu.
`docker compose logs pgbouncer` da har daqiqalik statistika bor. Postgres tomonida `select count(*) from pg_stat_activity`
`PGBOUNCER_MAX_DB_CONNECTIONS + ~15` dan oshmasligi kerak.

**O'chirish (orqaga qaytarish).** PgBouncer nosoz bo'lsa: ildiz `.env` da `DB_POOLER=direct`, so'ng
`docker compose up -d backend`. php-fpm avvalgidek to'g'ridan-to'g'ri ulanadi; bunda konteynerlar × php-fpm ishchilari +
15 < `max_connections` bo'lishi kerak. Yuk sinovi: `scripts/scale-test/pgbouncer.sh` (`DB_POOLER=pgbouncer ./server.sh start`).

## 13. So'rov va baza gigiyenasi (M1)

`docs/scale/RESULTS.md` dagi 2, 6, 7, 9–11-topilmalar. Hammasining standarti xavfsiz; env berilmasa ham ishlaydi.

**Indekslar** (`2026_11_15_300100_add_scale_hot_path_indexes`). Qo'shildi: `notification_logs (user_id, id)` (bildirishnomalar
lentasi va nishoni: 79 ms → 0,3 ms), `notification_logs (order_id)`, `payments (order_id)`, `product_variants (product_id)`,
`products (store_id, status)`, `order_items (voided_at) WHERE voided_at IS NOT NULL` (smena hisoboti). O'chirildi (faqat yozishni
sekinlatardi): `audit_logs_model_type_model_id_index` (`audit_logs_model_index` ning aynan nusxasi) va `order_items_order_id_index`
(`(order_id, client_line_id)` indeksi shu ishni qiladi). Hammasi `CONCURRENTLY` (tranzaksiyasiz): katta jadvalda ham kassa
yozuvlari to'xtamaydi. Yarim qolgan (INVALID) indeks qolsa, migratsiyani qayta ishga tushirish uni qayta quradi. Kuzatish:
`pg_stat_user_indexes.idx_scan` (yangi indekslar ishlatilyaptimi), `pg_stat_statements` da `notification_logs` so'rovlari.

**Token `last_used_at`.** Har so'rovda yozilmaydi: bitta token uchun ko'pi bilan `SANCTUM_LAST_USED_AT_THROTTLE_MINUTES`
(standart 5) daqiqada bir marta (`sanctum` va `device` guard'lari). `0` = har so'rovda (avvalgidek). Admin sessiyasi o'z
1 daqiqalik qoidasida qoladi (60 daqiqalik harakatsizlik chegarasi shu ustundan hisoblanadi). Terminal "oxirgi ko'rilgan"
(`devices.last_seen_at`) avvalgidek daqiqada bir yangilanadi. Ta'siri: admin kartasidagi "oxirgi faollik" va ovqatxona
sog'lig'idagi panel faolligi 5 daqiqagacha eski bo'lishi mumkin.

**Jadval ustunlarini so'rash.** Issiq modellar (`Order`, `OrderItem`, `OrderBill`, `OrderBillItem`, `Payment`, `FiscalReceipt`,
`IdempotencyKey`, `PosShift`, `OrderStatusHistory`, `PosSyncOperation`, `CashMovement`, `PosTip`, `PosRefund`,
`OrderChargeWaiver`, `PosApproval`, `StaffWorkSession`, `PosQrPayment`) `$guarded` o'rniga `$fillable` bilan: Eloquent har
so'rovda jadval ustunlarini so'ramaydi. Bu jadvallarga ustun qo'shgan migratsiya modelning `$fillable` ro'yxatiga ham yozilishi
shart, aks holda `tests/Feature/ModelFillableTest.php` yiqiladi (yozilmasa ustun ommaviy yozishda jim tashlab ketiladi).

**Oshxona ekrani.** To'langan peshtaxta buyurtmasining **hamma taomi tayyor bo'lib**, raqami mijozga berilmay qolgan
bo'lsa, u `GET /staff/kitchen/tickets` da oxirgi taom tayyor bo'lganidan keyin `ORDERS_KDS_CLOSED_COUNTER_MINUTES`
(standart 240) daqiqa turadi (avval bir kun). Oshpaz hali tayyorlashi kerak bo'lgan taomi bor chipta (yuborilgan va
bosilmagan, qaytarilgan, keyinroqqa qoldirilgan) muddatdan qat'i nazar doskada qoladi. Ochiq buyurtmalarga ta'sir
qilmaydi. Oshxona umuman bosmasa, doska avvalgidek o'sadi (200 ta chegara, `meta.truncated`).

**Ommaviy e'lon (ovqatxona egalariga).** Xabarlar birdaniga emas, to'lqin bilan ketadi: soniyasiga ko'pi bilan
`ADMIN_BROADCAST_RATE_PER_SECOND` (standart 5; Telegram chegarasi ~30, buyurtma xabarlari ham shu bot va shu `queue`
ishchisidan o'tadi), bir to'lqinda `ADMIN_BROADCAST_WAVE_SIZE` (20) ega. 1 000 ega ≈ 3,5 daqiqa; ko'rib chiqishda taxminiy vaqt
chiqadi. Har ega bir marta oladi (`store_broadcast_recipients`), har xabar natijasi `notification_logs` da
(`admin_store_broadcast`). Ishchi o'chib qolsa, navbatdagi to'lqin navbatda saqlanadi va davom etadi. Vazifa butunlay yiqilsa
(holati `failed`): `docker compose exec -u www-data queue php artisan admin:store-broadcast-resume <uuid>` — faqat hali
olmaganlarga yuboradi.

**PIN bilan kirish chegarasi (faqat kuzatish).** `throttle:staff-session`: bir terminaldan daqiqasiga 10, bir IP'dan 60.
Bitta NAT ortidagi katta oziq-ovqat markazida smena boshida 60 dan ko'p kirish bo'lsa 429 qaytadi (bir necha soniyada qayta
urinish o'tadi). Hozircha o'zgartirilmadi; shikoyat bo'lsa IP chegarasini ovqatxona bo'yicha qilish kerak.

## 14. PHP-FPM pullari: kassa bozor yukidan ajratilgan (M1)

**Nima uchun.** Avval kassa ham, Mini App ham bitta php-fpm pulida edi: 250 mijozlik bozor to'lqini kassa so'rovlarining
p95 ini 0,16 s dan 9 s ga chiqardi (`docs/scale/RESULTS.md`, 3-topilma). Endi `backend` konteynerida uchta pul bor va nginx
(`backend/docker/nginx.conf`) so'rovni yo'liga qarab yuboradi:

| Pul | Yo'llar (`/api/v1/...`) | Ishchilar | Cheklov |
|---|---|---|---|
| `critical` | `pos/*`, `staff/*` (buyurtmalar doskasi, oshxona chiptalari), `terminal*`, `devices/pair`, `devices/{uuid}/staff-session` (PIN), `auth/refresh`, `payments/click/webhook`, `payments/payme/webhook`, `health`; `/health`, `/up`; POS ilovasining (`X-Rizqim-App` + Sanctum token) aynan kassa shaklidagi so'rovlari: `catalog?channel=pos_hall` / `pos_takeaway` (+ `branch_id`, `store_id`) va `catalog/availability?store_id=...&branch_id=...` | `FPM_CRITICAL_CHILDREN` (4), `static`: doim tayyor | hech qanday nginx cheklovi yo'q |
| `market` | Mini App mijoz ekranlarining **o'qish** (GET) so'rovlari: `stores` (ro'yxat), `stores/{id}/zones`, `catalog` (mijoz), `catalog/availability` (`branch_id` siz), `orders` (o'z buyurtmalari), `notifications`, `addresses`, `locations`, `geocode/*`, `complaints` | `FPM_MARKET_CHILDREN` (4), `ondemand`, `nice` 10 | ha (pastda) |
| `default` | qolgan hammasi: `owner/*` (hamkor panel), `admin/*`, `auth/*` (Mini App kirishi `auth/telegram` ham), `user`, `me/*`, `features`, `devices`, `couriers`, `reports/*`, `promo/validate`, `store-applications`, `telegram/webhook`, `/api/broadcasting/auth`, yuqoridagi mijoz yo'llarining **yozuvlari** (POST/PUT/DELETE: buyurtma berish `POST /orders`, bekor qilish, baho, manzil, shikoyat); buyurtma berish kutadigan o'qishlar: `delivery/quote`, `stores/{id}/branches/{id}/pickup-slots`, savatning do'koni `stores?for=checkout`; `pos_*` kanal yoki `branch_id` aytilgan, lekin kassa shaklida bo'lmagan `catalog*` so'rovlari | `FPM_DEFAULT_CHILDREN` (4), `ondemand`, `nice` 5 | yo'q |

Fiskal callback yo'q (cheklar tashqariga yuboriladi), shuning uchun alohida qator yo'q. Ro'yxatdagi hech bir yo'lga mos kelmagan
so'rov `default` ga tushadi; `tests/Feature/FpmPoolRoutingTest.php` esa har bir ro'yxatga olingan marshrutni nginx'dagi
xaritaga solib ko'radi: yangi yo'l prefiksi qo'shilsa, u `nginx.conf` dagi `map $rizqim_uri $rizqim_path_class` ga yozilmaguncha
test yiqiladi (kassa yo'li `critical` dan, yozuv `market` dan chiqib ketsa ham).

**Mijozning buyurtma berishi qayerda va nega.** `POST /orders` (va boshqa mijoz yozuvlari) `default` pulida, `market` da emas:
ular trafikning bir necha foizi, pulga tegadi va `Idempotency-Key` bilan qayta yuborilsa ikkinchi buyurtma yaratmaydi. `market`
pul cheklangan, ya'ni to'lqin paytida rad etishi mumkin; buyurtma berish hech qachon rad etilmasin va o'qish navbati ortida
kutmasin. Savat (`customer/miniapp/src/pages/Cart.tsx`) buyurtma tugmasini quyidagilarsiz ishlata olmaydi, shuning uchun ular
ham `default` da: yetkazib berish narxi va minimal summa (`GET /delivery/quote`; xato bo'lsa tugma yopiq), olib ketish vaqtlari
(`.../pickup-slots`), savatning do'koni — olib ketish filiallari, to'lov usullari, buyurtma qulfi (`GET /stores?for=checkout`),
promo-kod (`POST /promo/validate`), keshbek balansi (`GET /user`). Menyu narxlari/qoldiq (`catalog`, `catalog/availability`)
va geokodlash `market` da qoladi: ularsiz ham buyurtma beriladi (server narx va qoldiqni baribir qayta tekshiradi, manzilni
qo'lda yozish mumkin). `useDeliveryQuote` 503/429 da `Retry-After` ni kutib 3 martagacha qayta so'raydi (ko'pi bilan 10 s).
Shunday qilib bozor cheklovi buyurtma berishni hech qachon to'xtatmaydi. `critical` ga ham qo'yilmadi: aksiya paytidagi
buyurtmalar oqimi kassalarni sekinlatmasin. Mini App kirishi (`auth/telegram`) ham shu sababli `default` da.

**Pulni so'rovchi tanlay olmaydi.** Kassa va Mini App bo'lishadigan `catalog`/`catalog/availability` faqat POS ilovasining
so'rovi (`X-Rizqim-App: cashier|waiter|kds|customer_display` + `Bearer <id>|...`) aynan kassa shaklida bo'lsa (`channel` bitta
marta, faqat `branch_id`/`store_id` raqam bilan) `critical` ga boradi; nginx ham, PHP ham so'rov satrining aynan bir xil
baytlarini o'qiydi, shuning uchun takrorlangan `channel`, `CHANNEL` yoki kodlangan nom ularni har xil tushunishga olib kelmaydi.
POS kanali yoki filial aytilgan boshqa har qanday shakl `default` ga boradi (hech qanday cheklov yo'q, kassa yangi parametr
qo'shsa ham rad etilmaydi). Usulni almashtirish (`X-HTTP-Method-Override`, `_method`) GET/HEAD ga ruxsat etilmaydi
(`bootstrap/app.php`), shuning uchun POST hech qachon GET marshruti sifatida xizmat qilmaydi.

**Bozor cheklovlari** (faqat `market` puliga; `critical` va `default` ga hech qachon):
- butun pul: bir vaqtda PHP'dagi bozor so'rovlari `NGINX_MARKET_MAX_INFLIGHT` dan (standart ishchilar × 4 = 16) oshsa, nginx darhol
  **503** + `Retry-After: 2` qaytaradi (soniyalab navbatda kutmaydi);
- bir mijoz (haqiqiy IP + Sanctum token raqami, ya'ni `|` dan oldingi raqamlar; token siri nginx xotirasiga yozilmaydi; IP
  `X-Forwarded-For` dan, ishonchli ichki proksilardan, Laravel'dagi kabi): soniyasiga `NGINX_MARKET_RATE` (5) so'rov +
  `NGINX_MARKET_BURST` (20) birdaniga — oshsa **429** + `Retry-After`; bir vaqtda `NGINX_MARKET_CONN_PER_CLIENT` (standart
  in-flight / 4 = 4) dan ko'p — 503;
- bir IP (bitta NAT ortidagi ko'p mijoz): soniyasiga `NGINX_MARKET_IP_RATE` (50) + `NGINX_MARKET_IP_BURST` (100) — 429; bir
  vaqtda `NGINX_MARKET_CONN_PER_IP` (in-flight / 2 = 8) — 503. Token raqamini almashtirib turgan mijoz shu chegarada qoladi.
- bitta mijoz pulni to'ldira olmaydi: `pools.sh` `NGINX_MARKET_CONN_PER_CLIENT × 2 > NGINX_MARKET_MAX_INFLIGHT`,
  `NGINX_MARKET_CONN_PER_IP ≥ NGINX_MARKET_MAX_INFLIGHT` (yoki mijoz ulushidan kam), `NGINX_MARKET_MAX_INFLIGHT >
  FPM_MARKET_CHILDREN + FPM_MARKET_BACKLOG` bo'lgan sozlamani rad etadi (konteyner ishga tushmaydi);
- sekin mijoz slotni ushlab turolmaydi: tanali (`Content-Length` > 0 yoki `Transfer-Encoding`) bozor GET so'rovi hech qanday
  limit hisoblanmasdan **400** oladi; bozor location'ida `client_body_timeout 5s`, `send_timeout 10s`,
  `fastcgi_read_timeout 60s`. Sarlavhalar slot olinishidan oldin o'qiladi, shuning uchun `client_header_timeout` standartda
  (60 s) qoldi: uni kamaytirish faqat sekin tarmoqdagi kassalarga tegardi.
- javob JSON: `{"success":false,"message":"...","error":"server_busy"}` (429 da `too_many_requests`). Mini App `message` ni ko'rsatadi,
  o'qishni o'zi qayta uradi (react-query 3 marta, so'rov bilan yangilanadigan ekranlar oraliqni ikki baravar oshiradi).
  Kassa, oshxona ekrani va hamkor panel `market` yo'llariga murojaat qilmaydi (hamkor panel `GET /user` ni `default` dan oladi).
- `FPM_MARKET_BACKLOG` (32) — market pulining `listen.backlog` i; `FPM_MARKET_PRIORITY` (10) va `FPM_DEFAULT_PRIORITY` (5) —
  ishchilarning `nice` qiymati: protsessor yetmaganda kassa ishchilari oldin oladi.

**O'lchamlarni tanlash.** Har ishchi ~30–50 MB xotira (opcache umumiy). Standart 4+4+4 = 12 ishchi (avval 5): ~0,5 GB.
`critical` ni filialdagi bir vaqtda ishlaydigan kassa+oshxona ekranlari soniga qarab (har 10–15 qurilmaga ~1 ishchi; ular
ko'pincha 50–150 ms li qisqa so'rovlar), `market` ni protsessor yadrolariga qarab (yadro × 1–2) tanlang. Bazaga ulanishlar
hisobi (konteynerlar × 12 ishchi): 12-bo'lim.

**Kuzatish.** `docker compose logs backend`:
- php-fpm kirish logi har so'rovni pul nomi bilan yozadi: `market 172.18.0.5 - ... "GET /api/v1/stores" 200 85.2 ms`;
- `[pool critical] server reached pm.max_children setting` (yoki `market`, `default`) — o'sha pul to'lgan: `critical` uchun bu ogohlantirish, ishchi
  qo'shing; `market` uchun to'lqin paytida normal;
- nginx'da `limiting connections by zone "rizqim_market_all"` — bozor 503 berdi; `"rizqim_market_client"` / `"rizqim_market_ip"` —
  bitta mijoz / IP o'z ulushidan oshdi; `limiting requests ... "rizqim_market_rate"` (`"rizqim_market_ip_rate"`) — bir mijoz (IP)
  429 oldi. Bular ko'p va doimiy bo'lsa, `FPM_MARKET_CHILDREN`/`NGINX_MARKET_MAX_INFLIGHT` ni (protsessor yetsa) oshiring.
O'zgartirish: `backend/.env` da qiymat, so'ng `docker compose up -d backend`. Qaytarish: qatorni o'chiring (standart qaytadi).
Yuk sinovi haqiqiy konfiguratsiya bilan: `SERVER=fpm scripts/scale-test/server.sh start` (`scripts/scale-test/README.md`).

## 15. Market o'qish modeli (M2)

Mini App'ning ko'rish so'rovlari (`/stores`, `/catalog`, `/catalog/availability` filialsiz) Redis'dagi hujjatlardan
beriladi (`backend/app/Domain/Marketplace`, `config/marketplace.php`); iliq keshda `products`, `product_variants`,
`stores`, `branches` o'qilmaydi. Buyurtma har doim jonli jadvallardan narxlanadi va tekshiriladi.

- **Hujjatlar.** Hudud (geo katak 0,05° ≈ 5,6 × 4,2 km, tuman, viloyat yoki "hammasi") — do'kon kartalari va filial
  doiralari; aniq radius tekshiruvi har so'rovda. Do'kon menyusi (kanal bo'yicha) va do'kon kartasi —
  `ChangeRevision::store` tokeni bilan. Mavjudlik qatlami — 5 s yangi, eng ko'pi 15 s. Lenta puli (hudud × kategoriya,
  120 mahsulot) — 60 s. Kategoriyalar, bannerlar — umumiy token.
- **Yangilanish.** Menyu/stop-list/do'kon jadvaliga har yozuv (M2-1 `RevisionMap`) do'kon tokenini, `stores`/`branches`
  kartadagi ustuni yoki `categories`/`promos`/`payment_providers`/`settings` ga yozuv esa market tokenini
  (`MarketplaceWrites`, SQL matni bo'yicha) commit'dan keyin almashtiradi. Faqat qarz (`debt_balance`) o'zgarsa
  hududlar qayta qurilmaydi (60 s ichida).
- **Bosim.** `Cache::flexible` (eskisi bir marta beriladi, javobdan keyin qayta quriladi) + `Cache::lock`: sovuq
  hujjatni bitta so'rov quradi, qolganlar 3 s gacha kutadi, keyin o'zi quradi. Redis ishlamasa har so'rov bazadan
  (sekin, lekin to'g'ri).
- **Xotira (1 000 do'kon, 60 taomli menyu).** Hujjatlar siqilgan (zlib): menyu ~14 KB (siqilmagan 210 KB), do'kon
  kartasi ~1 KB (+ sharhlar), mavjudlik ~0,5 KB, hudud katagi ~130 B/do'kon (300 do'konli katak ~40 KB), lenta puli
  ~47 KB. Jami: menyular 14–28 MB (1–2 kanal), kataklar ~8 MB (200 faol), lenta pullari 10–55 MB (faol katak ×
  kategoriya), qolgani < 10 MB: **≈ 40–100 MB**. Har yangi versiya oldingisini darhol o'chiradi; hamma kalitda TTL bor
  (≤ 1 soat). Redis `maxmemory` ni shu hisobga qo'shing.
- **Kuzatish.** Redis `used_memory`, `mkt:*` kalitlar soni (`redis-cli --scan --pattern '*mkt:*' | wc -l`), loglarda
  `Marketplace read model: cache unavailable`. nginx'da market pulining `304` ulushi.
- **Qaytarish.** `MARKETPLACE_READ_MODEL=false` (`backend/.env`, so'ng `docker compose up -d backend`): hamma ko'rish
  so'rovi M2 dan oldingidek bazadan.

**Terminal bilan aloqa qoidasi (standart O'CHIQ).** Ikkalasi kerak: `MARKETPLACE_REACHABILITY=true` va do'konda
`stores.marketplace_offline_minutes` (masalan 10). Shunda do'konning faol POS/KDS terminallaridan (mijoz ekrani
hisoblanmaydi) hech biri shuncha daqiqa server bilan bog'lanmagan bo'lsa, market uni "hozir buyurtma qabul qilmayapti"
(`accepting_orders=false`) ko'rsatadi va checkout rad etadi. Terminali yo'q do'konga hech qachon ta'sir qilmaydi.
Signal: `devices.last_seen_at` (terminal har so'rovda, daqiqada ko'pi bilan bir marta yozadi). Noto'g'ri rad etish
xavfi: buyurtmani faqat panel yoki Telegram'dan qabul qiladigan, lekin terminali o'chiq do'kon; planshet uxlab qolgan
yoki ilova yopilgan (so'rov yubormaydi); internet uzilib lokal rejimda ishlayotgan kassa; ilova versiyasi so'rov
yubormay faqat WebSocket'da turgan holat (xavfsizlik so'rovi bor, lekin oralig'i chegaradan uzun bo'lmasin).
Yoqish: `UPDATE stores SET marketplace_offline_minutes = 10 WHERE id = ...;` (o'chirish: `NULL`).

## 16. PHP so'rov narxi: OPcache, preload (M2)

O'lchov va tahlil: [scale/PHP_COST_M2.md](scale/PHP_COST_M2.md). Arzon autentifikatsiyali GET (`/pos/sync/status`) bitta
so'rovda ~12,8 ms → ~9,7 ms PHP CPU, POS yozuvlari 9–18 % kam.

- **Nima o'zgardi.** `backend/docker/php.ini`: OPcache 192 MB, interned strings 32 MB (8 MB standarti 93 % to'lgan edi:
  to'lsa har so'rov satrlarni qayta ko'chiradi), fayl vaqti tekshirilmaydi, preload (`docker/preload.php`). Entrypoint
  `event:cache` qiladi. Scramble (API hujjati) faqat `/docs/api*` va konsolda yuklanadi. Terminal tokeni o'z qurilmasini
  ikkinchi marta so'ramaydi; `GET /pos/floor` jadval borligini so'ramaydi (2 so'rov kam).
- **Kuzatish.** php-fpm ishga tushganda logda `preload: N classes preloaded, M skipped` qatori chiqadi (N ≈ 1 260).
  `M` katta bo'lsa yoki `skipped <klass>: <xato>` qatorlari bo'lsa, ro'yxat eskirgan: tezlik biroz tushadi, xato emas.
  O'lchovda 192 MB OPcache'ning ~70 MB i va 32 MB interned strings'ning ~14 MB i ishlatildi (zaxira yetarli).
- **Ro'yxatni yangilash** (katta o'zgarishlardan keyin): `scripts/scale-test/php/prof/preload-list.php` (README'da buyruqlar),
  natija `backend/docker/preload-classes.txt` commit qilinadi.
- **Qaytarish.** `PHP_PRELOAD=0` (`backend/.env`, so'ng `docker compose up -d backend`). Manba kodi mount qilingan lokal
  konteynerda: `PHP_OPCACHE_VALIDATE_TIMESTAMPS=1` (preload ham o'chadi).
- **JIT** standart o'chiq (`PHP_OPCACHE_JIT_BUFFER_SIZE=0`). `64M` bilan yana ~4 % CPU tejaladi (o'lchangan), lekin PHP
  8.3 dagi tracing JIT eng kam sinalgan qism: avval staging'da, segfault'larni (`php-fpm` logida `SIGSEGV`) kuzating.
