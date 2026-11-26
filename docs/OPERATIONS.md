# Rizqim: ekspluatatsiya (monitoring, loglar, zaxira)

Deploy: `docs/DEPLOY.md`. Bu hujjat ishlab turgan tizimni kuzatish va tiklash haqida. Hamma sozlamalar env orqali; qiymat bo'sh bo'lsa tegishli funksiya o'chiq turadi va ilova odatdagidek ishlaydi.

M3 («Ishonchlilik») bo'limlari: 17 (uzluksiz zaxira, PITR), 18 (vaqtga qaytarib tiklash), 19 (tiklash mashqi), 20 (jurnallar arxivi), 21 (ogohlantirishlar va tashqi kuzatuvchilar). Zaxira ikki qatlamli: uzluksiz zaxira (17) va kunlik `pg_dump` (4-bo'lim, ikkinchi himoya qatori).

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

**Nima zaxiralanadi.** Bu bo'lim kunlik `pg_dump` haqida: u M3 dan keyin ham ikkinchi himoya qatori bo'lib qoladi (jismoniy buzilish, PITR ishlamay qolgan holat, Postgres versiyasi almashganda). Bazani istalgan daqiqaga qaytarish uchun 17–18-bo'limlarga qarang. PostgreSQL (barcha buyurtmalar, moliya, foydalanuvchilar) `pg_dump --format=custom` bilan (siqilgan, `.dump`), yonida `.sha256`. Yuklangan rasmlar (`backend_storage` volume) alohida (pastda). Redis'dagi narsa (kesh, sessiya, navbat) zaxiralanmaydi: tiklash shart emas.

**Avtomatik zaxira.** `backup` xizmati `docker compose up -d` bilan ishga tushadi: har kuni `BACKUP_TIME` (UTC, standart 21:30 = Toshkent 02:30; M3 gacha 03:00 edi) da va xizmat ishga tushganda dump oladi, `BACKUP_HOST_DIR` (standart `./backups`) ga yozadi, `BACKUP_RETENTION_DAYS` (14) kundan eskilarini o'chiradi. Sozlamalar ildizdagi `.env` da. Log: `docker compose logs backup`. **Mavjud o'rnatishlar:** ildizdagi `.env` eski `.env.example` dan nusxalangan bo'lsa unda `BACKUP_TIME=03:00` yozilgan va u o'zgarmaydi (03:00 UTC = Toshkent 08:00: savdo boshlanayotgan payt). Ko'chirish: `.env` da `BACKUP_TIME=21:30` qiling va `docker compose up -d backup`. Shu vaqtda ketma-ket: uzluksiz zaxira (yoqilgan bo'lsa), `pg_dump` (endi `--load-via-partition-root` bilan: oylik jurnallar istalgan bo'linma tuzilmasiga tiklanadi), jurnallar arxivi (20-bo'lim).

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

**S3 dagi eski nusxalarni o'chirish (retention).** *M3 dan keyin muhim:* kunlik dump'lar, jurnallar arxivi (`archive/`, 20-bo'lim) va uzluksiz zaxira (`pitr/`, 17-bo'lim) bitta `BACKUP_S3_PREFIX` ostida turishi mumkin. **Butun bucket yoki prefiksga lifecycle o'chirish qoidasi qo'ymang**: u `archive/` (hech qachon o'chirilmasligi kerak) va `pitr/` (pgBackRest o'zi boshqaradi) fayllarini ham o'chirib yuboradi. Uzluksiz zaxira uchun alohida bucket oching (`PITR_S3_*`, kalitda o'chirish huquqi bilan: pgBackRest eski nusxalarni o'zi o'chiradi). Kunlik dump'lar uchun lifecycle qoidasini faqat `archive/` papkasini qamramaydigan qilib qo'yish mumkin bo'lsa qo'ying; bo'lmasa `BACKUP_S3_RETENTION_DAYS` ishlating (u faqat `rizqim-*.dump` fayllarini o'chiradi, lekin kalitga o'chirish huquqini talab qiladi). Quyidagi tavsiya shu shartlar bajarilganda amal qiladi: eng xavfsiz yo'l bucket'ning **lifecycle qoidasi** (masalan, 30–90 kundan keyin o'chirish; AWS S3: Management > Lifecycle rules, R2: Settings > Object lifecycle rules, B2: Lifecycle Settings). Shunda `BACKUP_S3_ACCESS_KEY` ga o'chirish huquqi kerak emas: server buzilsa ham tajovuzkor tashqi nusxalarni o'chira olmaydi (faqat yozish va o'qish huquqi yetadi; ransomware'dan himoya). Bucket versiyalash (versioning) yoqilgan bo'lsa, eski versiyalar uchun ham qoida qo'ying. `BACKUP_S3_RETENTION_DAYS` faqat lifecycle imkoni bo'lmagan provayderlar uchun; u kalitga o'chirish huquqini talab qiladi. Tashqi saqlash muddati mahalliydan uzun bo'lsin (masalan, mahalliy 14 kun, S3 da 60 kun).

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
3. **Zaxiradan keyingi yo'qotish.** (Uzluksiz zaxira yoqilgan bo'lsa yo'qotish ~1–2 daqiqa va aniq vaqtga qaytarish mumkin: 18-bo'lim.) Dump olingandan keyingi buyurtmalar/to'lovlar tiklanmaydi: Click/Payme kabinetidan shu oraliqdagi to'lovlarni solishtiring, kerak bo'lsa qo'lda kiriting. Telegram webhook qayta o'rnatiladi (`up` da avtomatik).

**Oyiga bir marta mashq qiling:** uzluksiz zaxira mashqi o'zi ishlaydi (19-bo'lim); kunlik dump'ni esa qo'lda sinang: eng yangi dump'ni `..._restore` bazasiga tiklab, jadvallardagi qatorlar sonini ish bazasi bilan solishtiring, so'ng `drop database` qiling. Tiklanmaydigan zaxira zaxira emas.

## 5. Nimani kuzatish kerak

| Nima | Qanday | Ogohlantirish sharti |
|---|---|---|
| Ilova tirikmi | Tashqi uptime monitor (UptimeRobot, Better Stack) `https://<domen>/api/v1/health` ga har 1 daqiqada (raqamlar kerak bo'lsa `X-Health-Token` header'i bilan) | HTTP `200` emas (503 yoki javob yo'q) bo'lsa darhol |
| Fon ishlari | Shu monitorda kalit so'z `"status":"ok"` | 5 daqiqa `ok` bo'lmasa (scheduler/navbat muammosi) |
| Xatolar | Sentry (1-bo'lim) | Yangi xato turi, xatolar keskin oshishi |
| Navbat | `/health` dagi `default_backlog`, `reports_backlog` (ichki tarmoqdan yoki `X-Health-Token` bilan); `failed_jobs` jadvali: `docker compose exec -u www-data backend php artisan queue:failed` | Backlog o'smoqda; `failed_jobs` bo'sh emas |
| Moliyaviy xato | Settlement xatolari `TELEGRAM_SUPERADMIN_CHAT_ID` ga keladi; `php artisan orders:retry-settlements` (belgisiz buyurtma — avval moliya tuzatishlarini tekshirib, `--confirm-unbooked` bilan; «Kechikkan to'lovning hisob-kitobi yiqilsa») | Har bir xabar |
| Zaxira yangiligi | `find backups -name '*.dump' -mmin -1560 \| grep -q .` (26 soat ichida dump bor) cron'da | Dump yo'q yoki `docker compose logs backup` da `XATO` |
| Disk | `df -h`, `docker system df`; Postgres volume va `backups/` hajmi | 80% dan oshsa |
| Redis xotira | `docker compose exec redis redis-cli info memory` (parol bo'lsa `-a`) | `used_memory` konteyner xotirasiga yaqinlashsa. `maxmemory-policy` ni `allkeys-*` qilmang (navbat kalitlari o'chadi) |
| Konteynerlar | `docker compose ps` (`unhealthy`, qayta ishga tushish soni) | Har qanday `unhealthy` |
| Real-time (Reverb) | `docker compose ps reverb` (healthcheck `GET /up`); `failed_jobs` dagi `Illuminate\Broadcasting\BroadcastEvent` | `reverb` `unhealthy`; `BroadcastEvent` joblari muvaffaqiyatsiz (6-bo'lim) |
| Uzluksiz zaxira (PITR) | Markaz: Monitoring → Holat → «Uzluksiz zaxira»; `scripts/pitr-restore.sh --info` (17-bo'lim) | Oxirgi o'zgarishlar omborga 5 daqiqadan eski; `wal_archive` ogohlantirishi |
| Tiklash mashqi | Markaz: «Tiklash mashqi» kartasi; oyiga bir marta o'zi ishlaydi (19-bo'lim) | Mashq 35 kundan beri o'tmagan yoki muvaffaqiyatsiz |
| Jurnallar bo'limlari | `php artisan db:partitions:status` (20-bo'lim) | Oldinda 2 oydan kam; `Hammasi joyida.` emas |
| Server butunlay o'chgan | Tashqi kuzatuvchilar: UptimeRobot va healthchecks.io (21-bo'lim) | Ichkaridan hech narsa ogohlantirmaydi: ikkalasini ulang |
| Sertifikat | Reverse proxy'dagi TLS muddati (Telegram webhook va Mini App faqat HTTPS) | 14 kun qolganda |

**Birinchi yordam.** Hamma narsa to'xtagan bo'lsa: `curl -i https://<domen>/api/v1/health` > `fail` bo'lsa `database`/`redis` qaysi biri; `docker compose ps`; `docker compose logs --tail=200 backend`. Bazani buzgan deploy bo'lsa: kodni qaytaring (`docs/DEPLOY.md`, 7-bo'lim), kerak bo'lsa 4-bo'limdagi tiklash. Ma'lumot yo'qolgan yoki buzilgan bo'lsa: 18-bo'lim (istalgan daqiqaga qaytarib tiklash).

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

**M2: o'zgarish tokenlari (`ChangeRevision`).** Har filial, do'kon va mijoz uchun Redis'da bitta raqamli token (`rev:b:{id}`, `rev:s:{id}`, `rev:u:{id}`, cache prefiksi bilan). Ekran ko'rsatadigan jadvalga har yozuv (Eloquent, ommaviy `update`, `DB::table`, pivot) tranzaksiya commit bo'lgach tokenni +1 qiladi: qoidalar `backend/app/Domain/Realtime/RevisionMap.php` da, **sxemadagi har jadval** ro'yxatda bo'lishi shart (`RevisionMapCoverageTest`: yangi migratsiya jadvalni tasniflamasa test yiqiladi). Menyu, stop-list, do'kon/filial sozlamasi do'kon tokenini va uning hamma filialini o'zgartiradi; buyurtma, schyot, to'lov, stol, smena, bron, navbat — filial tokenini va mijozning tokenini. Qurilmaning `last_seen_at` kabi "tiriklik" belgilari tokenni o'zgartirmaydi.

- **Oddiy savdo faqat sotgan filialni o'zgartiradi** (review M2 R3-1, `RevisionMap::NARROW_COLUMNS` da har ekran bo'yicha sabab). Hisob-kitob ustunlari (`stores.debt_balance`, `wallet_balance`, `subscription_debt`) va `store_transactions` / `store_wallet_transactions` qatorlari hech qaysi tokenni o'zgartirmaydi; faqat qarz qulfi (`is_order_locked`, market do'kon hujjatida) almashsa do'kon va hamma filial o'zgaradi (status o'zgarishi — doim). Qoldiq (`product_variants.stock`) hech qaysi filial ekranida ko'rinmaydi (POS menyusida yo'q): qoldiq oldin yoki keyin `REALTIME_EXACT_STOCK_BELOW` (20) yoki undan kam bo'lsa faqat do'kon tokeni o'zgaradi (Mini App menyusidagi qoldiq shu chegaragacha aniq, "tugadi" va "yana bor" darhol); undan yuqorida menyu ko'rsatgan qoldiq haqiqiydan katta bo'lishi mumkin (savat chegarasi; checkout jonli qatorni tekshiradi). Qozon (`menu_batches.quantity_left`): sotgan filial, chegarada esa do'kon ham. Natija: 3 filialli do'konda sotuv boshqa filiallarning 304 ulushiga ta'sir qilmaydi; sotuv boshiga Redis'ga 1–2 skript chaqiruv (oldin 5–10 INCR).
- **Arzon yozish.** Tranzaksiyaga bitta after-commit chaqiruv; kutilayotgan doiralar takrorsiz to'plam; filiallar ro'yxati flush'da bitta SQL; inkrementlar Redis'ga bitta Lua skriptda (5 000 kalitgacha). Platforma ma'lumoti (`categories`, `regions`, `settings` ...) 1 000 do'kon × 2 filialda: yozuv boshiga 1 SQL va 1 Redis chaqiruv (oldin 2 006 SQL, 6 003 buyruq); 300 ta yozuvli tranzaksiya xotirada bitta bayroq saqlaydi.
- **Redis yozuvni rad etsa ham eski token qaytmaydi** (review M2 R1-2). INCR rad etilsa (`maxmemory` + `noeviction`, timeout) kalitlar o'chiriladi (`DEL` to'la Redis'da ham ishlaydi): keyingi o'qish yangi tasodifiy token, klient to'liq o'qiydi. `DEL` ham o'tmasa doira shu jarayonda "hal qilinmagan" bo'lib qoladi: har o'qish yangi token beradi, keyingi flush, o'qish yoki so'rov oxiri kalitni o'chiradi. Qoplanmaydigan holat: qisqa PHP-FPM so'rovi INCR ham, DEL ham o'tmay tugasa va Redis keyin eski kalit bilan qaytsa — token shu doiraga keyingi yozuvgacha yoki TTL gacha qoladi; buni klientlarning `If-None-Match` siz xavfsizlik so'rovi (live: 60 s) va ETag umri chegaralaydi.
- Token yo'q bo'lsa (TTL 7 kun, Redis tozalangan) tasodifiy yangisi beriladi: klient bir marta to'liq o'qiydi. Redis ishlamasa har o'qish yangi token (hamma to'liq o'qiydi, eski ekran yo'q; yuk vaqtincha M2 dan oldingidek). Ekran eskirgan deb shubha bo'lsa: `CONDITIONAL_GET_ENABLED=false` (yuqoridagi jadval) va `RevisionMap` dagi qoidani tekshiring.
- **Do'kon tokeni nimani oshkor qiladi** (review M2 R2-2). Buyurtma va hisob-kitob uni o'zgartirmaydi. Hali o'zgartiradi: menyu/stop-list/sozlama tahriri, har sharh (`store_ratings`, sharhlar soni baribir ochiq), qarz qulfi almashishi, chegaradagi qoldiq va qozon sotuvlari (variant/qozon boshiga to'ldirishgacha eng ko'pi ~20 ta) va qozon tugashi. Mijozga do'kon doirasining xom tokeni berilmaydi ([API.md](API.md) 7.1); menyu hujjatidagi `stock` raqamining o'zi esa sotuvni ko'rsatadi (mijoz kontrakti, o'zgartirilmagan).

**Heartbeat va sog'liq.** Scheduler har `REALTIME_HEARTBEAT_SECONDS` (30) da `broadcasts` navbatiga `SendRealtimeHeartbeat` qo'yadi; u Reverb'dan band kanallar ro'yxatini oladi va har `pos-branch.*`, `branch.*`, `store.*` kanaliga `realtime.heartbeat` (`{"at","interval","rev"}`) yuboradi. Bu har event yuradigan yo'lning o'zi (scheduler → navbat → ishchi → Reverb), shuning uchun "soketlar ochiq, eventlar kelmaydi" holati ko'rinadi: klient 3 interval (90 s) jim qolsa tez so'rovga o'tadi. Server tomoni: oxirgi muvaffaqiyatli heartbeat `REALTIME_HEARTBEAT_STALE_SECONDS` (120) dan eski yoki `broadcasts` navbati `REALTIME_QUEUE_BACKLOG_THRESHOLD` (500) dan uzun bo'lsa real-time **degraded**: `GET /api/v1/realtime` `live: false` beradi, `reverb` konteyneri `unhealthy` (`php artisan realtime:health`; `REVERB_SCALING_ENABLED` bilan Redis'ni ham tekshiradi). Qo'lda: `docker compose exec -u www-data reverb php artisan realtime:health`. `/health` ga kiritilmagan: real-time ishlamasa hech narsa to'xtamaydi.

**Kanal obunasi keshi.** `POST /api/broadcasting/auth`: shu token shu kanalga ruxsat olgan bo'lsa, `REALTIME_AUTH_CACHE_SECONDS` (60) davomida qayta obuna Redis'dan imzolanadi (baza va token tekshiruvisiz, 2 ta Redis o'qishi; faqat `pos-branch`, `branch`, `store`, `user`, `courier` kanallari, `platform.ops` emas, rad javobi keshlanmaydi; daqiqasiga 60 ta cheklovi keshdan ham xuddi shu hisobda). Har ruxsat o'z "epoxa"larini eslaydi: token, foydalanuvchi yoki terminal, PIN sessiya bog'langan terminal, do'kon (ruxsat sozlamalari) va umumiy epoxa. Yozuv faqat o'zgargan qatorlarnikini commit bilan almashtiradi: token yangilansa yoki o'chirilsa — faqat shu token, rol/do'kon/filial o'zgarsa — shu foydalanuvchi, terminal uzilsa — shu terminal va uning PIN sessiyalari, ruxsat sozlamasi (qo'shish ham) — shu do'kon; filial boshqa do'konga o'tsa yoki qatorlar aniqlanmasa — hammasi (review M2 R2-4: oldin har 15 daqiqalik token yangilanishi butun platformadagi ruxsatlarni bekor qilardi). Muddati o'tgan token (`expires_at`, `SANCTUM_TOKEN_EXPIRATION`) keshdan imzolanmaydi (R2-5). Kanal qarori faqat shu jadvallarga bog'liq (tarif moduli, do'kon statusi emas), boshqa hollarni TTL (60 s) chegaralaydi (ochiq soket esa, avvalgidek, yopilguncha kanalda qoladi). `0` keshni o'chiradi.

**O'lcham (1 000 do'kon).** Do'konga ~7 ulanish (2 kassa, KDS, 3 ofitsiant, mijoz ekrani) + ochiq panel/Mini App: ~7 000–10 000 soket. Bitta Reverb jarayoni (ext-uv) ~10 000 gacha; har soket bitta fayl deskriptori (`nofile` 65536). Heartbeat: band kanal soni / 10 ta HTTP so'rov har 30 s da (1 500 kanal ≈ 5 so'rov/s, `broadcasts` navbatida bitta job). **Reverb qayta ishga tushishi = qayta ulanish to'lqini:** hamma klient bir vaqtda qayta ulanadi va obunani qayta imzolatadi (kesh shu to'lqin uchun). Klientlar: POS ilovalari (`packages/dart/rizqim_api` `Backoff`) 1 s dan 30 s gacha eksponensial, ±20% jitter — birinchi urinish ~1 s da hammada bir vaqtda; Mini App va panel `pusher-js` ning o'z qayta ulanishi (jitter yo'q). Stage B: birinchi qayta ulanishni 0–10 s oralig'iga tarqatish. Reverb'ni ish vaqtida qayta ishga tushirmang.

Loglar: `docker compose logs -f reverb` (ulanishlar sonini ko'rish uchun vaqtincha `php artisan reverb:start --debug`). Hodisalarda shaxsiy ma'lumot yo'q (faqat buyurtma `uuid`, holat va id'lar), shuning uchun Reverb loglari maxfiy emas. Yangi kod bilan Reverb `docker compose up -d --build` da qayta yaratiladi.

**Ko'p ulanish (ext-uv, nofile).** Reverb ReactPHP event loop'ida ishlaydi. Standart `stream_select()` loop bitta jarayonda ~1000 ulanishdan keyin ishdan chiqadi (PHP'ning `FD_SETSIZE` chegarasi), shuning uchun backend obraziga `ext-uv` (libuv, uv-0.3.0; `redis` bilan birga `backend/docker/ext` dagi PECL arxividan yig'iladi, shuning uchun pecl.php.net ishlamay qolsa ham obraz yig'iladi) o'rnatilgan: ReactPHP uni o'zi tanlaydi, kodni o'zgartirish shart emas. Tekshirish: `docker compose exec reverb php -m | grep -x uv` va `docker compose exec reverb php -r 'require "vendor/autoload.php"; echo get_class(React\EventLoop\Loop::get());'` (`React\EventLoop\ExtUvLoop` chiqishi kerak; `StreamSelectLoop` bo'lsa kengaytma yuklanmagan). Har ulanish bitta fayl deskriptori, shuning uchun `reverb` xizmatida `ulimits.nofile` = 65536 (`docker-compose.yml`); hisob: `docker compose exec reverb sh -c 'ls /proc/1/fd | wc -l'`. Bitta jarayon ~10 000 ulanishgacha mo'ljallangan; undan ko'pi uchun Reverb'ni gorizontal masshtablash kerak (`REVERB_SCALING_ENABLED`, Redis pub/sub: `config/reverb.php`) va tashqi proksida `worker_connections`/`ulimit -n` ni oshirish. Obrazni qayta yig'ing (`docker compose build --no-cache backend`), so'ng `docker compose up -d`.


## 7. PostgreSQL versiyasi (17)

Hamma joyda bir xil major versiya: `docker-compose.yml` (`postgres` obrazi `infra/postgres/Dockerfile` da `FROM postgres:17-alpine`, `backup`: `postgres:17-alpine`), `infra/backup/Dockerfile` va CI (`postgres:17`). `pg_dump` server versiyasidan eski bo'lmasligi kerak, shuning uchun `backup` obrazi har doim `postgres` bilan bir xil major versiyada bo'lsin; versiyani oshirsangiz to'rttasini birga o'zgartiring. 16 dan 17 ga o'tish (pastda) uzluksiz zaxirani (17-bo'lim) yoqishdan **oldin** bajariladi: PITR yoqilgandan keyin major versiya almashsa pgBackRest'da `stanza-upgrade` kerak bo'ladi: bu yo'l M3 da mashq qilinmagan, texnik yordamchi bilan rejalashtiring.

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
php artisan rizqim:admin:create ali@rizqim.uz "Ali" --role=operator,sales   # bir nechta rol: ruxsatlari qo'shiladi
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

**Bir nechta rol: migratsiya va orqaga qaytarish (Wave P).** `2026_12_05_100000_add_platform_roles_to_users_table`
migratsiyasi har bir a'zoning rolini bitta rolli to'plamga (`users.platform_roles`) ko'chiradi. Undan oldin
`users.role` va `platform_role` bir-biriga mos kelmagan qatorlarni tuzatadi va har birini logga yozadi
(`platform_roles migration: role and platform role brought into line`, `user_id`, oldin/keyin): jamoa hisobida
`users.role` ni platforma roli belgilaydi; do'kon darajasidagi hisob (`owner`, `cashier`...) do'kon rolini saqlab,
platforma rolini yo'qotadi; platforma rolisiz `super_admin` — superadmin. Deploydan keyin logni ko'zdan kechiring.

Migratsiyani orqaga qaytarish (`php artisan migrate:rollback`) har bir a'zoga **faqat asosiy rolini** qoldiradi
(`platform_role`, to'plamning birinchisi): qolgan rollar **yo'qoladi**, ularning ruxsatlari bilan. Yo'qolgan rollar
logga yoziladi (`platform_roles rollback: secondary roles dropped`, `user_id`, `kept`, `dropped`) va konsolga
chiqariladi; kerak bo'lsa ularni shu logdan qo'lda qaytaring. Orqaga qaytarishdan oldin ro'yxatni saqlab qo'ying:

```bash
psql -c "SELECT id, email, platform_roles FROM users WHERE jsonb_array_length(platform_roles) > 1"
```

## 11. Boshqaruv markazi: monitoring va ogohlantirishlar (P4-15)

Boshqaruv markazining **Monitoring** bo'limi (`docs/guides/boshqaruv-markazi.md`, "Monitoring") bu hujjatdagi tekshiruvlarni
bitta joyga yig'adi va ularga ogohlantirish qoidalarini qo'shadi.

- **Ops guruhi.** `backend/.env` da `TELEGRAM_OPS_CHAT_ID` (bo'sh bo'lsa `TELEGRAM_SUPERADMIN_CHAT_ID`) — ogohlantirishlar
  ochilganda va hal bo'lganda xabar boradigan guruh. Botni guruhga qo'shing. Bir xil muammo `MONITORING_TELEGRAM_COOLDOWN`
  (15) daqiqada bir martadan ko'p, jami soatiga `MONITORING_TELEGRAM_HOURLY_LIMIT` (20) tadan ortiq yuborilmaydi. Hamma qoidalar, chegaralar va har ogohlantirishda nima qilish: 21-bo'lim.
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
  konteynerida bajariladi: `docker compose exec -u www-data queue php artisan ...`; migratsiya va `db:partitions:*` esa
  `scheduler` da (jadvallar egasining kalitlari faqat unda va `backend` da; 22-bo'lim).

**O'lchamlar (env, ildiz `.env`).** `PGBOUNCER_POOL_SIZE` (20) — Postgres'ga doimiy ulanishlar; `PGBOUNCER_RESERVE_POOL_SIZE`
(5) — navbat 3 s dan uzoq kutsa qo'shimcha; `PGBOUNCER_MAX_DB_CONNECTIONS` (30) — Postgres'ga qat'iy yuqori chegara;
`PGBOUNCER_MAX_CLIENT_CONN` (1000) — php-fpm ulanishlari; `PGBOUNCER_QUERY_WAIT_TIMEOUT` (30 s) — bo'sh ulanish kutib
turgan so'rov shundan keyin xato oladi. Staging: 15 / 5 / 20.

Hisob (production, `max_connections = 100`):
- Postgres'ga: `PGBOUNCER_MAX_DB_CONNECTIONS` (30) + to'g'ridan-to'g'ri (~15: queue 1, queue-reports 1, scheduler va u
  ishga tushirgan buyruqlar ≤ 6, reverb 1, backup 1, migratsiya 1, qo'lda `psql`/`artisan` 3–4) + 3 superuser zaxirasi = **48 < 100**.
- php-fpm tomoni: har konteynerda `FPM_CRITICAL_CHILDREN + FPM_MARKET_CHILDREN + FPM_DEFAULT_CHILDREN + FPM_ANALYTICS_CHILDREN`
  (standart 4+4+4+2 = 14) ulanish; konteynerlar soni × 14 ≤ `PGBOUNCER_MAX_CLIENT_CONN` (1000). Ishchilarni ko'paytirish uchun `max_connections` ni
  oshirish shart emas, lekin **ishchilarni erkin qo'shib bo'lmaydi**: to'rttala php-fpm puli PgBouncer'ning bitta pulini
  (`PGBOUNCER_POOL_SIZE` ta server ulanishi) bo'lishadi. Har ishchiga bir vaqtda ko'pi bilan bitta ulanish kerak, shuning
  uchun **konteynerlar soni × (to'rttala pul ishchilari yig'indisi) ≤ `PGBOUNCER_POOL_SIZE`** bo'lishi shart: aks holda bazada
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
p95 ini 0,16 s dan 9 s ga chiqardi (`docs/scale/RESULTS.md`, 3-topilma). Endi `backend` konteynerida to'rtta pul bor va nginx
(`backend/docker/nginx.conf`) so'rovni yo'liga qarab yuboradi:

| Pul | Yo'llar (`/api/v1/...`) | Ishchilar | Cheklov |
|---|---|---|---|
| `critical` | `pos/*`, `staff/*` (buyurtmalar doskasi, oshxona chiptalari), `terminal*`, `devices/pair`, `devices/{uuid}/staff-session` (PIN), `auth/refresh`, `payments/click/webhook`, `payments/payme/webhook`, `health`; `/health`, `/up`; POS ilovasining (`X-Rizqim-App` + Sanctum token) aynan kassa shaklidagi so'rovlari: `catalog?channel=pos_hall` / `pos_takeaway` (+ `branch_id`, `store_id`) va `catalog/availability?store_id=...&branch_id=...` | `FPM_CRITICAL_CHILDREN` (4), `static`: doim tayyor | hech qanday nginx cheklovi yo'q |
| `market` | Mini App mijoz ekranlarining **o'qish** (GET) so'rovlari: `stores` (ro'yxat), `stores/{id}/zones`, `catalog` (mijoz), `catalog/availability` (`branch_id` siz), `orders` (o'z buyurtmalari), `notifications`, `addresses`, `locations`, `geocode/*`, `complaints` | `FPM_MARKET_CHILDREN` (4), `ondemand`, `nice` 10 | ha (pastda) |
| `default` | qolgan hammasi: `owner/*` (hamkor panel), `admin/*`, `auth/*` (Mini App kirishi `auth/telegram` ham), `user`, `me/*`, `features`, `devices`, `couriers`, `reports/*`, `promo/validate`, `store-applications`, `telegram/webhook`, `/api/broadcasting/auth`, yuqoridagi mijoz yo'llarining **yozuvlari** (POST/PUT/DELETE: buyurtma berish `POST /orders`, bekor qilish, baho, manzil, shikoyat); buyurtma berish kutadigan o'qishlar: `delivery/quote`, `stores/{id}/branches/{id}/pickup-slots`, savatning do'koni `stores?for=checkout`; `pos_*` kanal yoki `branch_id` aytilgan, lekin kassa shaklida bo'lmagan `catalog*` so'rovlari | `FPM_DEFAULT_CHILDREN` (4), `ondemand`, `nice` 5 | yo'q |
| `analytics` | faqat Mini App statistikasi `POST analytics/events` (oxiridagi `/` bilan ham; P to'lqini, 2-qadam): nginx'da o'z `location`i | `FPM_ANALYTICS_CHILDREN` (2), `ondemand`, `nice` 15, navbat `FPM_ANALYTICS_BACKLOG` (8) | ha (pastda): tana ≤ 32 KB, limitlar, qisqa kutish |

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

**Statistika puli (`analytics`) nima uchun alohida.** Avval `analytics/events` `default` pulida, buyurtma berish bilan bir
joyda edi: statistika paketlari oqimi (yoki bitta yomon niyatli mijoz) buyurtma beradigan ishchilarni egallashi mumkin edi,
nginx esa 16 MB gacha tanani o'tkazib yuborardi va Laravel JSON'ni kontrollerning 413 tekshiruvidan oldin o'qib chiqardi.
Endi bu yo'l nginx'ning alohida `location`ida va o'z kichik pulida: (1) tana 32 KB dan katta bo'lsa (`Content-Length` yoki
`chunked`) nginx PHP'ga yetkazmasdan **413** qaytaradi (tana kontrollerdagi bilan bir xil JSON: `payload_too_large`); (2) tanani
nginx o'zi to'liq o'qiydi, keyin ishchi oladi (sekin mijoz ishchini ushlab turmaydi; `client_body_timeout 5s`); (3) limitlar:
bir vaqtda `NGINX_ANALYTICS_MAX_INFLIGHT` (ishchilar × 4 = 8; bir IP yarmi, bir mijoz chorak qismi) dan ko'p bo'lsa **503**, bir
mijoz (IP + token raqami) daqiqasiga `NGINX_ANALYTICS_RATE` (30) + `NGINX_ANALYTICS_BURST` (10), bir IP daqiqasiga
`NGINX_ANALYTICS_IP_RATE` (600) + `NGINX_ANALYTICS_IP_BURST` (100) dan oshsa **429**, ikkalasida `Retry-After`; (4)
`fastcgi_connect_timeout 2s`, `fastcgi_read_timeout 10s`. Pul to'lsa statistika paketi yo'qoladi (ilova qayta yuboradi yoki
tashlaydi) — bu qabul qilingan narx; buyurtma berish sekinlashmaydi. Laravel'ning o'z limitlari (foydalanuvchi va sessiya
20/daq, IP 600/daq, kunlik byudjet; `docs/API.md`, «Analitika hodisalari») shundan keyin ishlaydi. `pools.sh` bozordagi
qoidalarni bu pulga ham qo'llaydi (bitta mijoz/IP pulni to'ldira olmaydi; in-flight ≤ ishchilar + navbat).

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

**O'lchamlarni tanlash.** Har ishchi ~30–50 MB xotira (opcache umumiy). Standart 4+4+4+2 = 14 ishchi (avval 5): ~0,5–0,7 GB.
`critical` ni filialdagi bir vaqtda ishlaydigan kassa+oshxona ekranlari soniga qarab (har 10–15 qurilmaga ~1 ishchi; ular
ko'pincha 50–150 ms li qisqa so'rovlar), `market` ni protsessor yadrolariga qarab (yadro × 1–2) tanlang. Bazaga ulanishlar
hisobi (konteynerlar × 14 ishchi): 12-bo'lim. `analytics` ni oshirishga odatda hojat yo'q (har paket bitta `INSERT`).

**Kuzatish.** `docker compose logs backend`:
- php-fpm kirish logi har so'rovni pul nomi bilan yozadi: `market 172.18.0.5 - ... "GET /api/v1/stores" 200 85.2 ms`;
- `[pool critical] server reached pm.max_children setting` (yoki `market`, `default`) — o'sha pul to'lgan: `critical` uchun bu ogohlantirish, ishchi
  qo'shing; `market` uchun to'lqin paytida normal;
- nginx'da `limiting connections by zone "rizqim_market_all"` — bozor 503 berdi; `"rizqim_market_client"` / `"rizqim_market_ip"` —
  bitta mijoz / IP o'z ulushidan oshdi; `limiting requests ... "rizqim_market_rate"` (`"rizqim_market_ip_rate"`) — bir mijoz (IP)
  429 oldi. Bular ko'p va doimiy bo'lsa, `FPM_MARKET_CHILDREN`/`NGINX_MARKET_MAX_INFLIGHT` ni (protsessor yetsa) oshiring.
- `"rizqim_analytics_*"` zonalari — statistika paketlari rad etildi (yo'qotilsa ham bo'ladi); `client intended to send too
  large body` — 32 KB dan katta statistika tanasi (413).
O'zgartirish: `backend/.env` da qiymat, so'ng `docker compose up -d backend`. Qaytarish: qatorni o'chiring (standart qaytadi).
Yuk sinovi haqiqiy konfiguratsiya bilan: `SERVER=fpm scripts/scale-test/server.sh start` (`scripts/scale-test/README.md`).

## 15. Market o'qish modeli (M2)

Mini App'ning ko'rish so'rovlari (`/stores`, `/catalog`, `/catalog/availability` filialsiz) Redis'dagi hujjatlardan
beriladi (`backend/app/Domain/Marketplace`, `config/marketplace.php`); iliq keshda `products`, `product_variants`,
`stores`, `branches` o'qilmaydi. Buyurtma har doim jonli jadvallardan narxlanadi va tekshiriladi.

- **Hujjatlar.** Hudud (geo katak 0,05° ≈ 5,6 × 4,2 km, tuman, viloyat yoki "hammasi") — do'kon kartalari va filial
  doiralari; aniq radius tekshiruvi har so'rovda. Do'kon menyusi (kanal bo'yicha) va do'kon kartasi —
  `ChangeRevision::store` tokeni bilan. Mavjudlik qatlami — 5 s yangi, eng ko'pi 15 s. Lenta puli (hudud × kategoriya,
  120 mahsulot) — 60 s yangi, eng ko'pi 120 s. Kategoriyalar, bannerlar — umumiy token. Hudud hujjatining har do'koni va
  lenta pulining har do'koni o'zi qurilgan do'kon tokenini yozib qo'yadi (ma'lumotdan oldin o'qilgan); har o'qishda
  joriy tokenlar bitta `MGET` bilan solishtiriladi: hududda faqat o'zgargan do'konlar qayta o'qiladi, pul to'liq
  qayta quriladi (bitta quruvchi, lock ostida). Eskirish jadvali: `docs/API.md` "Market o'qish modeli".
- **Yangilanish.** Menyu/stop-list/do'kon jadvaliga har yozuv (M2-1 `RevisionMap`) do'kon tokenini, `stores`/`branches`
  kartadagi ustuni yoki `categories`/`promos`/`payment_providers`/`settings` ga yozuv esa market tokenini
  (`MarketplaceWrites`, SQL matni bo'yicha) commit'dan keyin almashtiradi. Faqat qarz (`debt_balance`) o'zgarsa
  hududlar qayta qurilmaydi: kartadagi xom balans 120 s gacha eski, `is_order_locked` esa chegaradan o'tishda do'kon
  tokeni bilan darhol. Narxi: do'kon tokeni o'zgarishi (menyu tahriri, qarz qulfi) o'sha do'kon bor hudud kataklarida
  bitta kichik `whereIn` so'rov va o'sha do'konli lenta pullarini qayta quradi (sotuv tokenni o'zgartirmaydi).
- **Bosim.** `Cache::flexible` (eskisi bir marta beriladi, javobdan keyin qayta quriladi) + `Cache::lock`: sovuq
  hujjatni bitta so'rov quradi, qolganlar 3 s gacha kutadi, keyin o'zi quradi. Redis ishlamasa har so'rov bazadan
  (sekin, lekin to'g'ri).
- **Xotira (1 000 do'kon, 60 taomli menyu).** Hujjatlar siqilgan (zlib): menyu ~14 KB (siqilmagan 210 KB), do'kon
  kartasi ~1 KB (+ sharhlar), mavjudlik ~0,5 KB, hudud katagi ~130 B/do'kon (300 do'konli katak ~40 KB), lenta puli
  ~47 KB. Jami: menyular 14–28 MB (1–2 kanal), kataklar ~8 MB (200 faol), lenta pullari 10–55 MB (faol katak ×
  kategoriya), qolgani < 10 MB: **≈ 40–100 MB**. Har yangi versiya oldingisini darhol o'chiradi; hamma kalitda TTL bor
  (≤ 1 soat). Redis `maxmemory` ni shu hisobga qo'shing.
- **Redis `maxmemory`.** `docker-compose.yml` dagi `redis` `--maxmemory ${REDIS_MAXMEMORY:-1gb}` bilan ishlaydi
  (staging'da konteyner chegarasi 256 MB, shuning uchun `.env` da `REDIS_MAXMEMORY=200mb`); siyosat standart `noeviction` qolsin (yuqoridagi izoh: navbat va
  sessiya kalitlari o'chmasin). Ogohlantirish: `used_memory` > 80 % `maxmemory`. To'lib qolsa Redis yozuvni rad etadi
  (navbat, rate limit) — shuning uchun so'rovdagi hech narsa (masalan soxta `If-None-Match`) imzosiz Redis kaliti
  yarata olmaydi (`ConditionalGet`, review M2 R2-1).
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

## 17. Uzluksiz zaxira (PITR)

Dizayn va sabablar: [scale/M3_DESIGN.md](scale/M3_DESIGN.md), 6-bo'lim. Sozlamalar ro'yxati: [DEPLOY.md](DEPLOY.md), 2-bo'lim.

**Nimadan himoya qiladi.** Kunlik `pg_dump` (4-bo'lim) bazani kechasi bir marta oladi, shuning uchun u o'chib qolsa yoki buzilsa, oxirgi zaxiradan keyingi bir kungacha ma'lumot yo'qolishi mumkin. Uzluksiz zaxira (PITR) bazadagi har o'zgarishni taxminan bir daqiqada tashqariga saqlab boradi: server o'chsa ~1–2 daqiqalik ma'lumot yo'qoladi, noto'g'ri o'chirish bo'lsa bazani kerakli daqiqaga (masalan, 14:04 ga) qaytarib tiklash mumkin (18-bo'lim).

| Himoya | Yo'qotish oynasi | Qancha orqaga | Qachon kerak |
|---|---|---|---|
| Uzluksiz zaxira (PITR) | ~1–2 daqiqa | shu serverda ~14 kun (2 ta haftalik to'liq nusxa), S3 da ~28 kun (4 ta) | ma'lumot buzildi yoki xato o'chirildi; server yoki disk yo'qoldi (S3 dan) |
| Kunlik `pg_dump` (4-bo'lim) | 24 soatgacha | `BACKUP_RETENTION_DAYS` (14) kun, S3 da ham | PITR ishlamasa; jismoniy buzilish; Postgres versiyasi almashganda. **Ikkinchi himoya qatori: o'chirmang** |

**Yoqish (bir marta, kechasi, texnik yordamchi bilan).**

1. **Shifrlash parolini yarating va server tashqarisida saqlang.** Serverda: `openssl rand -base64 48`. Chiqqan qatorni parol menejeriga (yoki ikki joyga: egasining va texnik yordamchining) yozing. **Parol yo'qolsa S3 dagi PITR nusxalarini hech kim o'qiy olmaydi**; server o'chib, mahalliy nusxa ham yo'qolsa, uzluksiz zaxiradan tiklab bo'lmaydi. Parolni keyin o'zgartirmang (eski nusxalar eski parol bilan shifrlangan). Parolda `$` bo'lmasin. `.env` fayliga faqat egasi va texnik yordamchi kira olsin: `chmod 600 .env`.
2. Ildizdagi `.env` ga yozing:
   ```bash
   PITR_ENABLED=1
   PITR_CIPHER_PASS=<1-qadamdagi parol>
   ```
3. S3-mos xotira (AWS S3, Cloudflare R2, Backblaze B2, MinIO). 4-bo'limdagi `BACKUP_S3_ENDPOINT/BUCKET/ACCESS_KEY/SECRET` allaqachon to'ldirilgan bo'lsa PITR ham shu bucket'ga yozadi (`<BACKUP_S3_PREFIX>/pitr` papkasiga). Alohida bucket kerak bo'lsa `PITR_S3_ENDPOINT`, `PITR_S3_BUCKET`, `PITR_S3_ACCESS_KEY`, `PITR_S3_SECRET` (ixtiyoriy `PITR_S3_REGION`, `PITR_S3_PREFIX`) ni yozing. Manzil faqat `https://` bilan boshlansin: pgBackRest `http://` ga yozmaydi. Bucket oldindan yaratilgan bo'lsin. **Kalitda o'chirish huquqi ham bo'lishi shart** (pgBackRest eski nusxalarni o'zi o'chiradi), `pitr/` papkasiga bucket lifecycle qoidasi yoki Object Lock qo'ymang: saqlash muddatini `PITR_REPO1_RETENTION_FULL` va `PITR_S3_RETENTION_FULL` boshqaradi. Shuning uchun eng toza yo'l: PITR uchun alohida bucket va alohida kalit (`PITR_S3_*`), jurnallar arxivi uchun esa o'chirish huquqi **yo'q** boshqa kalit (`ARCHIVE_S3_*`, 20-bo'lim): bitta kalit bilan yozilsa, `backup` logida `DIQQAT: PITR (pgBackRest) va arxiv bir xil S3 kaliti bilan` chiqadi.
4. Ishga tushiring (obrazlar qayta yig'iladi):
   ```bash
   docker compose up -d --build
   ```

**Birinchi ishga tushishda nima bo'ladi.**
- `postgres` obrazi qayta yig'iladi: asosi o'sha `postgres:17-alpine`, ichiga pgBackRest qo'shilgan. Ma'lumotlar (`postgres_data` volume) o'zgarmaydi.
- **Postgres bir marta qayta ishga tushadi** (WAL arxivlashni yoqish uchun). Shu paytda ilova bazaga bir necha soniyadan 1–2 daqiqagacha ulana olmaydi (xato javoblari bo'lishi mumkin; taxmin, haqiqiy serverda o'lchanmagan), keyin o'zi tiklanadi. Shuning uchun kechasi bajaring. Keyingi deploy'larda bu qayta ishga tushish takrorlanmaydi.
- `docker-compose.yml` da `postgres` xizmatida `init: true` turadi: tegmang. Uzluksiz arxivlash yordamchi jarayon ochadi, `init` bo'lmasa Postgres uni xato deb o'ylab butun bazani o'zi qayta ishga tushirishi mumkin (CI da shunday ko'rilgan va tuzatilgan).
- `backup` xizmati ishga tushganda zaxira omborini yaratadi, tekshiradi (`check`) va to'liq nusxa hali yo'q bo'lsa **darhol birinchi to'liq nusxani oladi**. Baza hajmiga qarab bir necha daqiqadan soatgacha davom etadi, ilova shu paytda ishlayveradi (diskka va protsessorga yuk tushadi). **Birinchi to'liq nusxa tugamaguncha istalgan daqiqaga tiklab bo'lmaydi.** Tugaganini logdan ko'ring: `docker compose logs -f backup` (`PITR: zaxira tayyor`).
- Shundan keyin: har kuni `BACKUP_TIME` da (standart 21:30 UTC = Toshkent 02:30) UTC bo'yicha yakshanba kuni (Toshkentda dushanba 02:30) to'liq, boshqa kunlari farq nusxa; so'ng kunlik `pg_dump`; so'ng jurnallar arxivi (20-bo'lim).

*Tekshirilmaganlar:* bu zanjir (`backup` xizmati `postgres_data` ni faqat o'qish uchun ochib, umumiy soket orqali nusxa oladi, S3 ga yozadi) CI da kichik bazada va MinIO bilan sinalgan; haqiqiy serverda va boshqa S3 provayderlarda hali sinalmagan. Birinchi yoqishni texnik yordamchi bilan, kechasi bajaring.

**Ishlayotganini tekshirish.**
```bash
docker compose logs --tail=30 backup                                   # "PITR: zaxira tayyor" qatori
scripts/pitr-restore.sh --info                                         # nusxalar ro'yxati va tiklash mumkin bo'lgan oraliq (faqat o'qiydi)
docker compose exec backup sh /usr/local/bin/backup.sh --pitr-init     # zaxira omborini va WAL yuborilishini qayta tekshiradi
```
Bir necha soniya davom etadi; muvaffaqiyatli bo'lsa xato chiqmaydi, `--info` da `status: ok` va kamida bitta `full backup` ko'rinadi. Xato bo'lsa, matni xatoning sababini aytadi (ko'pincha S3 manzili, kalit yoki `PITR_CIPHER_PASS`).

Boshqaruv markazida: **Monitoring → Holat → «Uzluksiz zaxira (istalgan daqiqaga tiklash)»**. Normal ko'rinish: «Oxirgi nusxa: … oldin», «Oxirgi o'zgarishlar omborga … oldin yuborildi» (bir-ikki daqiqadan oshmasligi kerak), «Tashqi nusxa (S3): yoqiq». «O'chiq» chiqsa `PITR_ENABLED=1` yozilmagan; «Yoqilgan, lekin zaxira xizmati hali holatini yozmagan» chiqsa `docker compose up -d --build backup` qiling. Holatni darhol yangilash: `docker compose exec backup sh /usr/local/bin/backup.sh --markers` (aks holda har 5 daqiqada yangilanadi).

**Qo'lda nusxa olish** (masalan, katta o'zgarishdan oldin): `docker compose exec backup sh /usr/local/bin/backup.sh --pitr-backup full` (to'liq; `diff` ham mumkin). Bazaning hajmiga qarab daqiqalar; tugagach logda `PITR: zaxira tayyor`.

**S3 bo'lmasa.** Hammasi baribir ishlaydi, lekin nusxalar faqat shu serverda turadi: server yoki disk yo'qolsa, ular ham yo'qoladi. Markazda «Tashqi nusxa (S3): yo'q. Server yo'qolsa, nusxalar ham yo'qoladi» yozuvi doimiy turadi (Telegram ogohlantirishi faqat S3 sozlamasi chala, parol yo'q yoki manzil noto'g'ri bo'lsa keladi). Bazadan hech narsa o'chirilmaydi: jurnallar arxivi ham S3 siz bo'laklarni o'chirmaydi (20-bo'lim). S3 ni imkon qadar tez ulang. `PITR_CIPHER_PASS` bo'lmasa S3 ga PITR yozilmaydi (`BACKUP_S3_*` dagi kunlik dump esa yuklanaveradi).

**Disk qoidalari.**
- Mahalliy nusxalar (`pgbackrest_repo` volume'i) bazaning o'zi turgan diskda bo'ladi: 2 ta to'liq nusxa (siqilgan, har biri bazaning taxminan 0,3 qismi: taxmin, o'lchanmagan) va ~14 kunlik WAL. Pilotda (10 ta ovqatxona) WAL kuniga 100 MB dan kam bo'lishi kutiladi (taxmin); 1000 ovqatxonada kuniga bir necha GB. Haqiqiy hajmni birinchi haftada `docker system df -v` va markazdagi «Disk» kartasidan o'lchang.
- Disk 80 % to'lsa markaz «DIQQAT», 90 % da «KRITIK» ogohlantirish beradi (21-bo'lim). Tiklash mashqi (19-bo'lim) bazaning 1,2 baravari bo'sh joy talab qiladi, aks holda to'xtaydi.
- **S3 yoki tarmoq ishlamasa** hosil bo'lgan WAL fayllari navbatda kutadi. Navbat `PITR_ARCHIVE_QUEUE_MAX` (4 GiB) dan oshsa pgBackRest WAL ni **tashlaydi**: disk to'lib, baza to'xtab qolmasin deb. Bunda uzluksiz zaxira zanjirida uzilish bo'ladi (ish bazasi zarar ko'rmaydi), va Postgres arxivlash «ishlayapti» deb ko'rsatadi. Shuning uchun `backup` xizmati har 5 daqiqada zaxira omborining o'zini tekshiradi (arxivlangan WAL fayllari uzluksizmi): uzilish (10 daqiqadan eski bo'lsa: kechikib kelayotgan fayllar uzilish hisoblanmaydi) topilsa markazda PITR holati **xato** bo'ladi, matni `Zaxira zanjiri uzilgan (repo1): <vaqt> (UTC) dan keyingi daqiqalarga qaytarib bo'lmaydi`, va **darhol shu omborga yangi to'liq nusxa olinadi** (u eski nusxalarni o'chirmaydi; olinmasa 6 soatda bir qayta uriniladi). Ikkala ombor (shu server va S3) alohida tekshiriladi; S3 ro'yxati soatiga bir marta o'qiladi (28 kunlik WAL da oyiga ~30 ming ro'yxat so'rovi, bir necha sent). Shundan keyin tiklash oralig'i ikkiga bo'linadi: uzilishgacha (eski nusxalardan) va yangi to'liq nusxa tugagan vaqtdan hozirgacha; orasidagi daqiqalarga qaytarib bo'lmaydi (`scripts/pitr-restore.sh --info` ikkala oraliqni ham ko'rsatadi).

**Ogohlantirish: «WAL … arxivga yuborilmayapti» (`wal_archive`).**
1. Nima bo'lgan: bazaning o'zgarishlari zaxira omboriga ketmayapti, uzluksiz zaxira eskirib bormoqda. Ilova ishlayveradi.
2. Sababni toping: `docker compose logs --tail=100 postgres` (arxiv xatosi shu yerda) va `docker compose logs --tail=100 backup`. Odatiy sabablar: S3 ga ulanib bo'lmadi, kalit yoki bucket noto'g'ri/o'chirilgan, `PITR_CIPHER_PASS` o'zgargan, disk to'lgan.
3. Sababni tuzating (`.env` ni o'zgartirgan bo'lsangiz: `docker compose up -d`). Arxivlash o'zi davom etadi: navbatdagi WAL ketadi.
4. 5–10 daqiqadan keyin Monitoring → Holat da «Oxirgi o'zgarishlar omborga … oldin yuborildi» qayta yangilanganini ko'ring; ogohlantirish o'zi yopiladi. WAL tashlangan bo'lsa (navbat to'lib) markaz «Zaxira zanjiri uzilgan» deydi va yangi to'liq nusxa o'zi olinadi: tugaguncha kuting (`scripts/pitr-restore.sh --info` da yangi oraliq; qo'lda: `docker compose exec backup sh /usr/local/bin/backup.sh --pitr-heal`).
5. «PITR yoqilgan, lekin Postgres'da archive_mode o'chiq» chiqsa: `docker compose up -d --build postgres` (Postgres qayta ishga tushadi, kechasi).
6. Bir necha soatda tuzatib bo'lmasa texnik yordamchiga qo'ng'iroq qiling va navbat 4 GiB ga yetmasidan oldin `docker compose exec backup sh /usr/local/bin/backup.sh` (kunlik dump) olib qo'ying.

## 18. Vaqtga qaytarib tiklash

Bu bo'lim «14:05 da ma'lumot yo'qoldi yoki buzildi» holati uchun. Shartlar: uzluksiz zaxira yoqilgan (17-bo'lim) va birinchi to'liq nusxa olingan. Yoqilmagan bo'lsa: 4-bo'limdagi `pg_dump` tiklash yo'li.

**Asosiy qoida: `scripts/pitr-restore.sh` ish bazasining ustiga HECH QACHON o'zi yozmaydi.** U har doim bazaning yangi nusxasini alohida joyga tiklaydi va ish bazasining papkasiga tiklashni rad etadi. Ish bazasini almashtirish alohida, qo'lda, ongli qadam (pastda, 5-qadam).

| Qadam | Ish bazasiga ta'siri |
|---|---|
| `scripts/pitr-restore.sh --info` | Yo'q (faqat o'qiydi) |
| `scripts/pitr-restore.sh "vaqt"` (tiklash va 5499 portida ochish) | Yo'q: yangi joyga, alohida Postgres, faqat Docker tarmog'ida |
| Tiklangan nusxaga `psql` bilan qarash, kerakli qatorlarni fayl qilib olish | Yo'q (o'qiydi) |
| `--stop`, `--drop` | Faqat tiklangan nusxani to'xtatadi/o'chiradi |
| `scripts/pitr-switch.sh <nom>` | **ISH BAZASINI ALMASHTIRADI** (5-qadam; baza nomini yozib tasdiqlash so'raladi; faqat oldinga) |
| `restore.sh --allow-live` (4-bo'lim, kunlik dump) | Almashtiradi: baza nomini yozib tasdiqlash so'raladi |

**Kimga qo'ng'iroq qilish.** 1–3-qadamlarni egasi o'zi bajara oladi (hech narsa buzilmaydi). 4–5-qadamlar (qatorlarni qaytarish va ish bazasini almashtirish) texnik yordamchi bilan bajariladi: ikkalasi birga ko'rib, keyin qaror qiladi. Telefon raqamlarini shu yerga yozib qo'ying: texnik yordamchi: ______, hosting provayderi: ______.

**1. Vaqtni aniqlang va hech narsani qayta ishga tushirmang.** Buzilish aynan qachon boshlanganini yozib oling (masalan, 14:05). Tiklash nuqtasi shundan **biroz oldin** bo'lsin (14:04). Vaqt zonasi yozilmasa **Toshkent vaqti** (+05) deb olinadi; UTC kerak bo'lsa oxiriga `+00` yozing. Buzilish davom etayotgan bo'lsa (masalan, noto'g'ri skript yozyapti), avval uni to'xtating; kerak bo'lsa ilovani to'xtating: `docker compose stop backend queue queue-reports scheduler reverb`.

**2. Qaysi vaqtlarga tiklash mumkinligini ko'ring** (bir necha soniya, hech narsaga tegmaydi):
```bash
scripts/pitr-restore.sh --info
```
Avval «Qisqa xulosa» chiqadi: holat, har ombor (`repo1` shu server, `repo2` S3) bo'yicha oxirgi to'liq va oxirgi nusxa, «Tiklash mumkin: <vaqt> dan oxirgi arxivlangan WAL gacha» va «Oxirgi WAL arxivga: <vaqt>» (UTC). Siz tanlagan vaqt shu oraliqda bo'lishi shart. Pastda pgBackRest'ning to'liq ro'yxati.

**3. Yangi joyga tiklang va oching** (ish bazasiga tegmaydi):
```bash
scripts/pitr-restore.sh "2026-12-01 14:04"
```
- Nima qiladi: pgBackRest eng yaqin to'liq nusxani va kerakli WAL larni olib, `pitr_restore` volume'idagi yangi papkaga (`pitr-202612011404`) tiklaydi va ikkinchi Postgres'ni `backup` konteynerida **5499** portida ochadi. Ilova bu nusxani ko'rmaydi.
- Qancha vaqt: **baza hajmiga bog'liq va hajm bilan o'sadi.** CI dagi kichik sinov bazasida 2 soniya bo'ldi; bu haqiqiy son emas. Haqiqiy serverdagi vaqtni birinchi tiklash mashqi (19-bo'lim) o'lchaydi: rejalashtirishda shu mashq hisobotidagi `Tiklash vaqti` ni oling.
- Joy: bazaning taxminan bir hajmicha bo'sh joy kerak (`pitr_restore` volume'i Docker diskida). Tekshiring: `df -h` va `docker system df`.
- Tugaganini bilish: oxirgi qatorlarda `TAYYOR. Qaytarilgan nuqta (oxirgi tranzaksiya): <vaqt>` va ulanish buyrug'i chiqadi. Xato bo'lsa `XATO:` qatori sababni aytadi (ko'pincha: vaqt eng birinchi nusxadan oldin yoki oraliqdan tashqari: `--info` bilan solishtiring; joy yetmadi).
- Faqat bitta tiklangan nusxa ochiq tura oladi (port 5499). Ikkinchisini ochishdan oldin: `scripts/pitr-restore.sh --stop <nom>`. Ochiq nusxa xotira va disk egallaydi, ishingiz tugagach o'chiring (6-qadam). Ro'yxat: `scripts/pitr-restore.sh --list`.
- S3 dagi nusxadan (shu serverdagi nusxa buzilgan bo'lsa): `--repo 2` qo'shing: `scripts/pitr-restore.sh --repo 2 "2026-12-01 14:04"`. Server yoki disk butunlay yo'qolgan holat (yangi serverda `.env`, jumladan `PITR_CIPHER_PASS` va S3 kalitlari bilan tiklash) haqiqiy serverda hali mashq qilinmagan: texnik yordamchi bilan va hosting provayderi bilan rejalashtiring.

**4. Ma'lumotni ko'ring va kerakli qismini qaytaring (xavfsiz yo'l).**
Tiklangan nusxaga ulanish:
```bash
scripts/pitr-restore.sh --psql
```
Masalan, `select count(*) from orders;` yoki `select id, name from products where store_id = 12;`: yo'qolgan ma'lumot shu yerda bormi, ko'ring. Faqat kerakli qatorlarni olish kerak bo'lsa (ish bazasi boshqa qismlarda to'g'ri bo'lib turgan paytda; **bu 90 % hollarda eng yaxshi yo'l**), ularni faylga oling (fayl serverdagi `backups/` papkasiga tushadi):
```bash
docker compose exec backup psql -h /restore/restore-socket -p 5499 -c "\copy (select * from products where store_id = 12) to '/backups/products-12.csv' csv header"
```
Faylni ish bazasiga yuklashni texnik yordamchi bajaradi: avval vaqtinchalik jadvalga, ko'rib chiqib, keyin asosiy jadvalga (id lar to'qnashmasin). Shu yo'l bilan ish bazasi to'xtamaydi va tiklash nuqtasidan keyingi yangi buyurtmalar yo'qolmaydi.

**5. Butun bazani tiklangan nusxaga almashtirish (faqat haqiqiy avariyada; ISH BAZASINI ALMASHTIRADI).** Bunda 14:04 dan keyin yozilgan HAMMA narsa (buyurtmalar, to'lovlar) yangi bazada bo'lmaydi. Eski ish bazasi o'zgarmay qoladi: orqaga qaytish mumkin. *Bu yo'l CI da kichik bazada mashq qilinadi, haqiqiy serverda hali yo'q: birinchi marta texnik yordamchi bilan va kam yuk paytida bajaring; avval staging'da sinab ko'ring.* Qaror ikki kishi tomonidan og'zaki tasdiqlansin.
```bash
# a) 3-qadamdagi nusxa (masalan pitr-202612011404) yopiq bo'lsin
scripts/pitr-restore.sh --stop pitr-202612011404
# b) Almashtirish: bitta buyruq, baza nomini yozib tasdiqlashni so'raydi
scripts/pitr-switch.sh pitr-202612011404
```
Skript nima qiladi (har qadam ekranda raqamlangan, `1/8` ... `8/8`): nusxa to'liq tiklanib (promote bo'lib) toza yopilganini va joy yetishini tekshiradi (hali ochilmagan yoki xato bilan to'xtagan nusxani rad etadi va nima qilishni aytadi: `--start <nom>`, so'ng `--stop <nom>`); qaytarish uchun kerak narsalarni yozib qo'yadi; ilovani, `backup` va `postgres` ni to'xtatadi; ildiz `.env` ga `POSTGRES_DATA_VOLUME=<nusxa papkasi>` yozadi (eski `.env` nusxasi `.env.before-pitr-switch-<vaqt>` bo'lib qoladi); Postgres'ni ko'tarib `pg_is_in_recovery = f` va asosiy jadvallar sonini ko'rsatadi; `migrate:status` va `db:restore-smoke` bilan tekshiradi; **yangi timeline'da to'liq PITR nusxa oladi** (shu serverda, repo1); shundan keyingina ilovani ko'taradi va S3 ga ham to'liq nusxa oladi. Ilova to'liq nusxa tugaguncha to'xtab turadi (baza hajmiga qarab daqiqalar). Oxirida `TAYYOR`.

Repo1 ga to'liq nusxa olinmaguncha har qanday xato, Ctrl-C yoki SSH uzilishida skript **oldingi bazani o'zi qaytaradi** (`QAYTARILDI: ...`; oldingi baza o'zgarmagan). Server yoki skript butunlay to'xtab qolgan bo'lsa (elektr, `kill -9`): `scripts/pitr-switch.sh --recover`. S3 ga nusxa olinmasa almashtirish baribir tugaydi, lekin `DIQQAT` yoziladi va markaz S3 nusxasi eskirganini ko'rsatadi.

**Almashtirish faqat oldinga.** Boshqa daqiqaga o'tish kerak bo'lsa (masalan, noto'g'ri vaqt tanlangan), xuddi shu yo'l qayta bajariladi: `scripts/pitr-restore.sh "<to'g'ri vaqt>" --into <yangi nom>`, so'ng `scripts/pitr-switch.sh <yangi nom>`. Har almashtirish yangi timeline oladi, zaxiralar odatdagidek davom etadi.

**Avvalgi ish bazasi** diskda saqlab qo'yiladi: `scripts/pitr-restore.sh --list` da «avvalgi baza (saqlab qo'yilgan)». U qayta ishga tushirilmaydi va ish bazasi qilinmaydi (skript va `postgres` buni rad etadi): bu texnik yordamchi uchun oxirgi chora, orqaga qaytish yo'li emas. O'chirish: `scripts/pitr-restore.sh --drop <nom>` (nomini yozib tasdiqlanadi). Birinchi almashtirishdan keyin avvalgi baza `postgres_data` volume'ida qoladi; uni texnik yordamchi kamida bir hafta o'tib o'chiradi.

Server o'zi yo'qolgan bo'lsa yoki nusxa boshqa diskda bo'lishi kerak bo'lsa: `scripts/pitr-restore.sh "2026-12-01 14:04" --into-dir /srv/rizqim/pgdata-20261201` (xostdagi yangi papka: tiklaydi, promote qiladi va toza yopadi), so'ng `scripts/pitr-switch.sh --dir /srv/rizqim/pgdata-20261201`. Tiklash nuqtasidan keyingi yo'qotilgan to'lovlarni Click/Payme kabinetidan solishtiring (4-bo'lim, 3-band).

**6. Tozalash.** Tiklangan nusxa kerak bo'lmagach: `scripts/pitr-restore.sh --stop pitr-202612011404`, so'ng `scripts/pitr-restore.sh --drop pitr-202612011404` (ish bazasi bo'lib turgan nusxani skript o'chirmaydi).

## 19. Tiklash mashqi

Zaxira tiklanishini faqat tiklab ko'rib bilasiz. Bitta buyruq (serverda, loyiha papkasida):
```bash
scripts/restore-drill.sh
```
Nima qiladi: ish bazasiga belgi yozadi, uning arxivga yetishini kutadi, zaxiradan shu daqiqaga **yangi joyga** tiklaydi, ikkinchi Postgres'ni 5499 portida ochib tekshiradi (belgilar, jadvallar soni, migratsiyalar, bo'linmalar, indekslar), ilova tekshiruvini (`db:restore-smoke`) bajaradi, natijani yozadi va nusxani o'chiradi. Ish bazasi va ilova to'xtamaydi; faqat disk va protsessorga vaqtincha yuk tushadi. Bazaning 1,2 baravari bo'sh joy kerak. Davomiyligi baza hajmiga qarab: kichik bazada soniyalar, kattasida daqiqalar-soatlar (haqiqiy serverda hali o'lchanmagan: birinchi mashq o'lchaydi). Tugashi: `TIKLASH MASHQI: MUVAFFAQIYATLI` va chiqish kodi 0. S3 dagi nusxani sinash: `scripts/restore-drill.sh --repo 2`.

**Qachon o'zi ishlaydi.** `backup` xizmati har oyning **birinchi yakshanbasida `PITR_DRILL_TIME` (standart 23:00 UTC = dushanba 04:00 Toshkent)** da mashq qiladi. O'chirish: `PITR_DRILL_MONTHLY=0` (ildiz `.env`). Avtomatik mashq bazani tekshiradi, lekin ilova tekshiruvini bajara olmaydi (`backup` konteynerida PHP yo'q): uning hisobotida `app_smoke: o'tkazib yuborildi` va `restore_drills.checks` da `"trigger": "auto"` turadi. To'liq tekshirilgan mashq (`app_smoke: to'g'ri`, `"trigger": "manual"`) faqat qo'lda `scripts/restore-drill.sh`: uni kamida har chorakda bajaring. **PITR ni yoqqach darhol birinchi mashqni qo'lda bajaring** (birinchi to'liq nusxa tugagandan keyin): shu mashq haqiqiy tiklash vaqtini o'lchaydi va 18-bo'limdagi vaqtlarni rejalashtirishga asos bo'ladi.

**Hisobot qatorlari** (vaqtlar UTC):

| Qator | Ma'nosi |
|---|---|
| `Tiklash vaqti: 4 daq 12 s` | Tiklash boshlanganidan nusxa ishga tayyor bo'lguncha o'tgan vaqt: haqiqiy avariyada ma'lumot shuncha vaqtda qaytadi (+ sizning qo'l bilan qadamlaringiz). Baza kattalashgani sari o'sadi |
| `Qaytarilgan nuqta: 2026-12-06 23:00:41 (UTC)` | Tiklangan nusxadagi oxirgi tranzaksiya vaqti: shu daqiqagacha ma'lumot bor |
| `Ma'lumot yo'qotish oynasi: 38 s` | Yozuv bazada paydo bo'lganidan arxivga yetgunicha o'tgan vaqt: server shu paytda o'chsa, shuncha so'nggi ma'lumot yo'qolardi. Kutilgan qiymat 1–2 daqiqadan kam |
| `tekshiruv <nom>: to'g'ri / o'tkazib yuborildi / XATO` | `probe` (belgi: T dan oldingisi bor, keyingisi yo'q), `manifest` (jadvallardagi qatorlar soni ish bazasi bilan bir xil), `migrations`, `partitions` (oylik bo'linmalar va `audit_logs_append_only` trigger), `amcheck` (indekslar butunligi), `app_smoke` (ilova tekshiruvi) |

CI dagi kichik sinov bazasida natija: tiklash 2 s, yo'qotish oynasi 61 s edi. **Bu productionning sonlari emas**; haqiqiy vaqtni birinchi mashq beradi.

**Natija qayerda.** Har mashq ish bazasidagi `restore_drills` jadvaliga yoziladi. Boshqaruv markazida: **Monitoring → Holat → «Tiklash mashqi»** (oxirgi mashq, tiklash vaqti, yo'qotish oynasi). Mashq 35 kundan beri o'tmagan bo'lsa «DIQQAT», oxirgisi muvaffaqiyatsiz bo'lsa «KRITIK» ogohlantirish keladi (21-bo'lim).

**Mashq muvaffaqiyatsiz bo'lsa.**
1. Hisobotdagi `Xatolar:` qatoriga qarang va `docker compose logs --tail=100 backup`.
2. Ko'p uchraydigan sabablar: «joy yetmaydi» (diskni bo'shating); «WAL … s ichida arxivga yetmadi» (arxivlash ishlamayapti: 17-bo'limdagi `wal_archive` ro'yxati); `pgbackrest check o'tmadi`; `pgbackrest restore xato berdi` (zaxira buzilgan yoki parol/S3 xatosi); «PITR o'chiq».
3. Sababni tuzatib, buyruqni qayta bajaring. Muvaffaqiyatli mashq chiqquncha uzluksiz zaxiraga ishonmang: qo'lda `docker compose exec backup sh /usr/local/bin/backup.sh` bilan dump oling va 4-bo'limdagi tiklash yo'lini sinab ko'ring (kunlik `pg_dump` ikkinchi himoya qatori).
4. Ochiq qolgan mashq nusxasi bo'lsa (xato tufayli to'xtagan): `docker compose exec backup sh /usr/local/bin/pitr-drill.sh cleanup`.

## 20. Jurnallar arxivi

**Nima va nima uchun.** Uchta jurnal jadvali oylar bo'yicha bo'lingan (har oy alohida bo'lak): `audit_logs` (harakatlar jurnali), `fiscal_receipts` (fiskal cheklar) va `notification_logs` (xabarnomalar). Ular tinimsiz o'sadi; eski oyni qatorma-qator o'chirish sekin va bazani shishiradi, butun oy bo'lagini ajratib olish esa bir lahzada bajariladi. Buyurtmalar, to'lovlar va hisob-kitob jadvallari bo'linmaydi va bu mexanizm ularga tegmaydi.

| Jadval | Bazada saqlanadi (joriy oy bilan) | Sozlama (`backend/.env`) | Eski oy bilan nima bo'ladi |
|---|---|---|---|
| `audit_logs` | 7 oy | `ARCHIVE_AUDIT_MONTHS` | faylga olinadi, S3 da tasdiqlangach bazadan o'chadi; arxiv fayli **hech qachon o'chirilmaydi** |
| `fiscal_receipts` | 13 oy | `ARCHIVE_FISCAL_MONTHS` | xuddi shunday (arxiv fayli hech qachon o'chirilmaydi) |
| `notification_logs` | 6 oy | `ARCHIVE_NOTIFICATION_MONTHS` | arxivlanmaydi, o'chiriladi (eski xabarnomalar kerak emas) |
| `analytics_events` (P to'lqini, 2-qadam) | 3 oy: joriy oy va undan oldingi ikkitasi | `ANALYTICS_EVENTS_MONTHS` | arxivlanmaydi, o'chiriladi: oy shu oynadan chiqqan kunning kechasi (`db:partitions:archive`, 03:40) bazadan o'chadi, ya'ni eng eski yozuv taxminan 3 oy, eng ko'pi bilan 3 oy + 1 kun turadi (Mini App harakatlari; maxfiylik siyosatidagi muddat shu sozlamaga mos bo'lishi shart). Bo'laklar faqat shu oynadan boshlab yaratiladi (`PARTITIONS_FIRST_MONTH` unga tegmaydi) |

**`analytics_events` haqida ikki ogohlantirish.** (1) Kod orqaga qaytarilsa-yu, migratsiya qaytarilmasa (`migrate:rollback`
qilinmasa), jadval bazada qoladi, lekin `config/partitions.php` dagi yozuvi yo'qoladi: **uni hech narsa o'chirmaydi** —
saqlash muddati to'xtaydi. Unda maxfiylik siyosatidagi muddatga rioya qilish uchun migratsiyani ham qaytaring
(`php artisan migrate:rollback --path=database/migrations/2026_12_08_100000_create_analytics_events_table.php`, jadvalni oylari bilan o'chiradi) yoki eski oylarni qo'lda o'chiring. (2) Scheduler
uzoq to'xtab qolgan bo'lsa, oylar ulanmagani uchun yozuvlar «zaxira» (DEFAULT) bo'lakka tushadi; keyingi
`db:partitions:ensure` ularni o'z oyiga ko'chirayotganda jadvalni qulflaydi va shu vaqt ichida **statistika yozuvi
to'xtaydi** (`POST /analytics/events` kutadi yoki nginx'ning qisqa kutish vaqtidan keyin yo'qoladi; buyurtmalarga ta'sir
qilmaydi). DEFAULT katta bo'lsa (`db:partitions:status`), `ensure` ni tinch vaqtda qo'lda ishga tushiring.

Qolgan ixtiyoriy sozlamalar (standart qiymatlar yaxshi; `backend/.env.example` da izoh sifatida turibdi, kerak bo'lsa `backend/.env` ga ko'chiring): `PARTITIONS_FIRST_MONTH` (2026-01, migratsiya yaratadigan birinchi oy), `PARTITIONS_INLINE_MAX_ROWS` (200000, undan katta jadval onlayn ko'chiriladi, pastga qarang), `PARTITIONS_LOCK_TIMEOUT` (2s), `PARTITIONS_DETACH_LOCK_TIMEOUT` (3s), `ARCHIVE_ALLOW_LOCAL_ONLY` (standart o'chiq, pastga qarang). O'zgartirgandan keyin: `docker compose up -d --force-recreate backend queue queue-reports scheduler`.

**Har kecha nima bo'ladi** (UTC; Toshkent = +5):
1. **00:40 (05:40)** `db:partitions:ensure`: kelgusi 3 oy uchun bo'laklarni tayyorlaydi va «zaxira» (DEFAULT) bo'lakka tushib qolgan qatorlarni o'z oyiga ko'chiradi.
2. **21:30 (02:30)** `backup` xizmati kunlik `pg_dump` dan keyin `archive-partitions.sh` ni ishga tushiradi: avval ajratilgan oylarni (`detached`) `pg_dump -Fc` bilan faylga oladi (`backups/archive/<jadval>/<YYYY-MM>.dump`), `sha256` hisoblaydi, S3 ga yuklaydi (`ARCHIVE_S3_*`, bo'sh bo'lsa `BACKUP_S3_*`; `<prefiks>/archive/<jadval>/`), **faylni S3 dan qayta yuklab oladi**, `sha256` va `pg_restore --list` ni tekshiradi; shundagina holat `verified` bo'ladi.
3. **22:40 (03:40)** `db:partitions:archive`: (a) bazadagi eng eski oylar (jadvalning «bazada saqlanadi» chegarasidan eskilari) asosiy jadvaldan **ajratiladi** (o'chirilmaydi; holat `detached`); (b) tasdiqlanganiga (`verified`) 3 kundan ko'p bo'lgan oylar bazadan o'chiriladi (`dropped`).
Natijada eski oy avval ajratiladi, keyingi kecha faylga olinib S3 da tekshiriladi, kamida 3 kundan keyin o'chadi. **Tasdiqlangan tashqi nusxasiz hech narsa o'chirilmaydi.** Arxiv fayllariga bucket'da lifecycle (muddatli o'chirish) qo'ymang; imkon bo'lsa Object Lock yoki versiyalash yoqing. Arxiv uchun o'chirish huquqi **yo'q** alohida kalit bering (ildiz `.env` da `ARCHIVE_S3_ACCESS_KEY`, `ARCHIVE_S3_SECRET`, kerak bo'lsa alohida `ARCHIVE_S3_BUCKET`). Skript har kecha kichik sinov fayli bilan kalit o'chira oladimi tekshiradi: o'chira olsa logda `DIQQAT: arxiv kaliti S3 dan O'CHIRA OLADI` chiqadi (natija `monitoring_markers` dagi `archive` belgisida). `BACKUP_S3_RETENTION_DAYS` faqat prefiks ildizidagi `rizqim-*.dump` fayllarini o'chiradi, `pitr/` va `archive/` ga tegmaydi.

**S3 bo'lmasa.** Arxiv fayli faqat serverdagi `backups/archive/` da qoladi (holat `uploaded`), bazadan **hech narsa o'chirilmaydi** va jurnallar bazada o'sishda davom etadi (`notification_logs` bundan mustasno: u arxivsiz o'chiriladi). `backend/.env` da `ARCHIVE_ALLOW_LOCAL_ONLY=1` bo'lsa mahalliy nusxa yetarli hisoblanib bo'lak o'chiriladi: **tavsiya etilmaydi**, server yo'qolsa arxiv ham yo'qoladi.

**Arxivlangan oyni qayta o'qish (bitta buyruq):**
```bash
scripts/archive-restore.sh audit_logs 2026-03
```
Fayl serverda bo'lmasa S3 dan oladi, `sha256` ni katalog bilan solishtiradi va oyni **alohida** `rizqim_archive` bazasiga tiklaydi (ish bazasiga tegmaydi). Oxirida qatorlar soni va tayyor buyruq chiqadi, masalan: `docker compose exec backup psql -d rizqim_archive -c 'SELECT * FROM audit_logs_2026_03 ORDER BY id DESC LIMIT 20'`. Fayl hajmiga qarab soniyalar-daqiqalar. Fiskal cheklar uchun jadval nomi `fiscal_receipts`. Xato `XATO:` qatori bilan chiqadi (oy katalogda yo'q, fayl buzilgan, S3 ga ulanib bo'lmadi). `--attach` (bo'lakni ish bazasiga qaytarib ulash) faqat mutaxassis uchun va tasdiq uchun bo'lak nomini yozishni so'raydi.

**Ogohlantirish: oldinda oylar yetishmaydi (`partitions`).** Odatda scheduler har kuni 05:40 da kelgusi oylarni o'zi tayyorlaydi; ogohlantirish kelsa, scheduler ishlamayotgan bo'lishi mumkin (`docker compose ps scheduler`). Tuzatish — bitta buyruq:
```bash
docker compose exec -u www-data backend php artisan db:partitions:ensure
```
Bir necha soniya. Holatni ko'rish: `docker compose exec -u www-data backend php artisan db:partitions:status` (jadval; `Hammasi joyida.` chiqsa muammo yo'q, aks holda chiqish kodi 1 va nima qilish yozilgan). Ogohlantirish kelguncha ham hech narsa yo'qolmaydi: oy bo'lagi bo'lmasa qatorlar «zaxira» bo'lakka yoziladi va `ensure` ularni o'z oyiga ko'chiradi. «Arxivlashda N ta xato» yoki «N ta ajratilgan bo'lim 3 kundan beri yuklanmagan» chiqsa: `docker compose logs backup` da `XATO:` qatorini o'qing, S3 sozlamasini tuzating va `scripts/archive-partitions.sh` ni qo'lda ishga tushiring (qayta urinadi).

**Pilot paytida: bazasida ma'lumot bor o'rnatish.** Migratsiya har bir jadvalni bitta tranzaksiyada o'zi bo'ladi: shu vaqt ichida shu jadvalga **yozish to'xtaydi** (o'qish davom etadi), taxminan har 140 000 qatorga 1 soniya (o'lchangan: 190 000 qator 1,3 soniya; 5 000 000 qator taxminan 36 soniya). Nusxa asl jadval bilan qator-qator solishtiriladi; farq bo'lsa hech narsa o'zgarmaydi. Jadvalda `PARTITIONS_INLINE_MAX_ROWS` (200 000) dan ko'p qator bo'lsa migratsiya to'xtaydi va qatorlar sonini, necha soniya yozish to'xtashini va bajariladigan buyruqni aytadi; to'xtab qolgan deploy ilovani ko'tarmaydi. Unda **kechasi**, tinch vaqtda, texnik yordamchi bilan:
```bash
docker compose exec backup sh /usr/local/bin/backup.sh                                       # zaxira
docker compose exec -u www-data -e PARTITIONS_INLINE_MAX_ROWS=<xabardagi son> backend php artisan migrate --force
```
Shu soniyalarda kassalar oflayn rejimda sotishda davom etadi, yozuvlar keyin yuboriladi. Jadval band bo'lsa (uzoq davom etayotgan tranzaksiya) migratsiya kutib turmaydi: bir necha soniyadan keyin xato beradi; birozdan keyin qayta bajaring. Boshqa yo'l (onlayn ko'chirish) yo'q.

## 21. Ogohlantirishlar

Boshqaruv markazi har daqiqada (`scheduler` ichida) 20 ta qoidani tekshiradi (11-bo'lim). Ogohlantirish **Telegram**'ga `TELEGRAM_OPS_CHAT_ID` guruhiga (bo'sh bo'lsa `TELEGRAM_SUPERADMIN_CHAT_ID` ga) keladi: botni shu guruhga qo'shing, guruhga egasi va texnik yordamchi a'zo bo'lsin. Xabar ko'rinishi:
```
[KRITIK] Baza turgan disk 91 % to'lgan, bo'sh joy 12.3 GB
Disk to'lib bormoqda
Nima qilish kerak: Keraksiz fayllarni o'chiring yoki diskni kengaytiring; pg_wal katta bo'lsa WAL yuborilishini tekshiring.
Boshlangan: 02.12.2026 14:05
<markazdagi havola>
```
Muammo hal bo'lganda ham xabar keladi. Yuborish qoidalari:
- Hali yuborilmagan ochiq ogohlantirish har daqiqada qayta taklif qilinadi: chegara, Telegram yoki Redis ishlamay turgani faqat kechiktiradi, yo'qotmaydi. KRITIK'lar birinchi ketadi.
- Bir qoidadan bir vaqtda 3 tadan ko'p ogohlantirish bo'lsa (masalan internet uzilib 100 ta kassa oflayn), ular **bitta** xabar bo'lib keladi: «Ish vaqtida oflayn kassa: 100 ta: ... va yana 97 ta». Tiklanganda ham bitta: «30 tasi tiklandi». Qoidalar navbat bilan yuboriladi: bitta shovqinli qoida boshqalarini to'sib qo'ymaydi.
- DIQQAT darajasidagilar: soatiga ko'pi bilan 20 ta xabar (guruh xabari bitta hisoblanadi). KRITIK'lar bu songa kirmaydi (har qoida uchun soatiga 10 ta xabargacha, faqat cheksiz takrorlanishdan himoya). Chegaradan oshganlari jim qolmaydi: soatiga bitta «Yana N ta ogohlantirish ochiq: ...» xabari keladi, chegara bo'shagach hali ochiq turganlari yuboriladi.
- «HAL QILINDI» xabari har doim yuboriladi. Muammo «HAL QILINDI» dan keyin qaytsa, darhol yana yuboriladi. DIQQAT KRITIK'ka aylansa, qayta yuboriladi.
- Telegram javob bermasa xabar navbatga qo'yiladi; navbat ishchisi uni 10 daqiqada yubormasa, scheduler o'zi to'g'ridan-to'g'ri yuboradi.
- Shunday qilib soatiga ko'pi bilan: 20 ta DIQQAT xabari + har qoida uchun 10 ta KRITIK xabari + «HAL QILINDI» xabarlari (ko'p bo'lsa qoida bo'yicha bitta) + 1 ta umumiy xabar.

**Chegaralarni o'zgartirish.** Boshqaruv markazi → **Monitoring → Qoidalar**: har qoidaning chegarasi, oynasi va Telegram'ga yuborilishi shu yerda o'zgaradi (env va qayta ishga tushirish kerak emas). Quyidagi jadvaldagi chegaralar standart qiymatlar.

| Qoida (markazda) | Nimani bildiradi | Standart chegara | Daraja | Nima qilish (Telegram'dagi matn) |
|---|---|---|---|---|
| Yiqilgan vazifalar ko'payishi | Oxirgi daqiqalarda yiqilgan fon vazifalari soni oshdi | 10 ta / 15 daqiqa | DIQQAT | Monitoring → Yiqilgan vazifalar bo'limida xatoni o'qing va sababini tuzatib, vazifani qayta yuboring. |
| Navbat to'planishi | Navbatda vazifalar to'planib qoldi yoki eng eskisi uzoq kutmoqda | 500 ta; eng eskisi 10 daqiqa | DIQQAT | Navbat ishchisi ishlayotganini tekshiring (`docker compose ps queue`) va kerak bo'lsa qayta ishga tushiring. |
| Ish vaqtida oflayn kassa | Ish vaqtida kassa yoki oshxona ekrani aloqada emas | 15 daqiqa | DIQQAT | Ovqatxonaga qo'ng'iroq qilib kassaning interneti va ilovasini tekshirtiring. |
| Fiskal cheklar yiqilishi | Fiskal chek soliq tizimida ro'yxatdan o'tmadi | 1 ta / 60 daqiqa | KRITIK | Yiqilgan cheklarni Kassalar bo'limida ko'ring va xato sababini (STIR, kassa raqami, OFD) tuzating. |
| Hisob-kitob xatolari | Yetkazilgan buyurtma puli hisobga yozilmadi | 1 ta | KRITIK | Moliya bo'limida hisobga yozilmagan buyurtmalarni ko'ring; tizim ularni har 10 daqiqada qayta urinadi. |
| Zaxira nusxa eskirgan | Kunlik `pg_dump` zaxirasi eski | 26 soat | KRITIK | `backup` xizmatini tekshiring: `docker compose logs backup`; so'ng `docker compose exec backup sh /usr/local/bin/backup.sh` bilan zaxira oling. |
| Scheduler to'xtagan | Rejalashtirilgan ishlar ishlamayapti | 180 soniya | KRITIK | `scheduler` xizmatini tekshiring va qayta ishga tushiring: `docker compose restart scheduler`. |
| Bazaga ulanishlar ko'p | Postgres'ga ochiq ulanishlar ruxsat etilganning ulushidan oshdi: yangi so'rovlar ulana olmay qolishi mumkin | 80 % | KRITIK | Tafsilotlarda qaysi manzil ko'p ulanish ochganini ko'ring va o'sha xizmatni qayta ishga tushiring. |
| Uzoq davom etayotgan tranzaksiya | Bazada bitta tranzaksiya juda uzoq ochiq: tozalashni to'xtatadi, boshqalarni qulflashi mumkin | 300 soniya ishlayotgan; 60 soniya ochiq qolib turgan | DIQQAT | Tafsilotlardagi jarayon (pid) nima ekanini aniqlang; osilib qolgan bo'lsa `SELECT pg_terminate_backend(pid)` bilan to'xtating. |
| Bazada qulf kutayotgan so'rovlar | Bir nechta so'rov boshqasining qulfini uzoq kutmoqda: kassalar sekinlashadi | 3 ta so'rov, 30 soniya | DIQQAT | Qulfni ushlab turgan uzoq tranzaksiyani toping (Uzoq davom etayotgan tranzaksiya ogohlantirishi) va uni to'xtating. |
| Uzluksiz zaxira (WAL) yuborilmayapti | Bazaning o'zgarishlari zaxira omboriga ketmayapti: PITR zanjiri uzilmoqda | 10 ta fayl kutmoqda yoki 5 daqiqa xato | KRITIK | Zaxira omborini (S3) va `backup` xizmati logini tekshiring; ishlay boshlagach to'liq zaxira o'zi olinadi (17-bo'lim). |
| Uzluksiz zaxira (PITR) eskirgan | Oxirgi nusxa eski yoki zaxira xizmati holatni yozmayapti; S3 sozlamasi chala | har qanday nusxa 26 soat; to'liq 8 kun; holat 15 daqiqa | KRITIK | `backup` xizmati ishlayotganini tekshiring (`docker compose logs backup`), so'ng `docker compose exec backup sh /usr/local/bin/backup.sh --pitr-backup` bajaring. |
| Tiklash mashqi | Mashq uzoq o'tkazilmagan yoki oxirgisi muvaffaqiyatsiz | 35 kun (DIQQAT); oxirgisi xato (KRITIK) | DIQQAT / KRITIK | Serverda `scripts/restore-drill.sh` ni ishga tushiring va hisobotdagi xato qatorini o'qing (19-bo'lim). |
| Disk to'lib bormoqda | Baza yoki zaxira ombori turgan disk to'lib bormoqda | 80 % DIQQAT; 90 % KRITIK | DIQQAT / KRITIK | Keraksiz fayllarni o'chiring yoki diskni kengaytiring; `pg_wal` katta bo'lsa WAL yuborilishini tekshiring. |
| Redis xotirasi to'lib bormoqda | Redis xotirasi chegaraga yaqin: to'lsa navbatga vazifa qo'shilmaydi | 80 % DIQQAT; 90 % KRITIK | DIQQAT / KRITIK | Navbatda to'planib qolgan vazifalar bormi ko'ring (navbat ishchisi), yoki Redis `maxmemory` ni oshiring. |
| Navbat ishchisi to'xtagan | Ishchi har daqiqalik tekshiruv vazifasini bajarmadi: Telegram xabarlari va fon vazifalari ketmayapti | 180 soniya | KRITIK | `queue` xizmatini qayta ishga tushiring: `docker compose restart queue`; logida xato bo'lsa uni tuzating. |
| Real vaqt (Reverb) ishlamayapti | Real vaqt yangilanishlari ishonchsiz; ilovalar tezroq so'rov bilan ishlaydi, buyurtmalar yo'qolmaydi | 3 daqiqa | DIQQAT | `reverb` va `queue` xizmatlarini tekshiring: `docker compose ps reverb queue`; kerak bo'lsa qayta ishga tushiring (6-bo'lim). |
| Jadvallarda o'lik qatorlar ko'p | Asosiy jadvallarda o'chirilgan, lekin tozalanmagan qatorlar ko'p yoki uzoq tozalanmagan | 20 % (va 100 000 dan ko'p qator); 24 soat tozalanmagan | DIQQAT | Kechasi o'sha jadvalga `VACUUM (ANALYZE)` bajaring; takrorlansa `autovacuum` sozlamasini ko'rib chiqing. |
| Jurnallarning oylik bo'limlari | Oldindagi oylar tayyor emas, «zaxira» bo'limda qatorlar bor yoki arxivlashda xato | oldinda kamida 2 oy; zaxira bo'limda 0 qator | KRITIK (oylar yetishmasa) / DIQQAT | `php artisan db:partitions:ensure` bajaring; xabar boshqa buyruqni aytsa, o'shani bajaring (20-bo'lim). |
| Ma'lumotlar bazasi javob bermayapti (qoida emas, har doim yoqiq) | Baza ishlamayapti: ilova va qolgan ogohlantirishlar ham ishlamaydi. Scheduler bazaga murojaat qilmasdan to'g'ridan-to'g'ri Telegram'ga yozadi; baza uzilib-ulanib tursa ham 15 daqiqada bitta xabar (necha marta uzilgani bilan); baza 3 daqiqa barqaror ishlagach «yana javob bermoqda» xabari keladi. Disk to'lgan bo'lsa ham takrorlanib ketmaydi (holat xotirada saqlanadi; u ham bo'lmasa soatda bir marta) | har 15 daqiqada bir marta | KRITIK | Serverda `docker compose ps postgres`, `docker compose logs --tail=50 postgres`; disk to'lmaganini tekshiring (`df -h`). |
| Redis xotirasi to'lib bormoqda: Redis javob bermayapti | Redis ishlamayapti: navbat (Telegram xabarlari, fon vazifalari), kesh va real vaqt to'xtagan. Bu vaqtda scheduler va navbat ishchisi qoidalari «noma'lum» deydi (ular Redis'da yozadi), xabar bitta: Redis | javob yo'q | KRITIK | `docker compose ps redis`, `docker compose logs --tail=50 redis`; kerak bo'lsa `docker compose restart redis`. |
| HTTPS sertifikati muddati tugayapti | Sertifikat tugashiga oz qoldi: tugasa mijozlar ilovani ocholmaydi | 14 kun DIQQAT; 3 kun KRITIK | DIQQAT / KRITIK | Sertifikatni yangilang (masalan `certbot renew`) va proksi serverni qayta yuklang. |
| Fiskal cheklar yiqilishi: 30 kundan eski chek | Savdoning fiskal cheki qayta urinish muddati (`FISCAL_RETRY_MAX_AGE_DAYS`, 30 kun) ichida ro'yxatdan o'tmadi: savdo soliqda ko'rinmaydi va chek endi o'zi qayta yuborilmaydi. Chek `failed` holatidan chiqmaguncha (qo'lda qayta yuborilib ro'yxatdan o'tmaguncha) ogohlantirish ochiq turadi; matnda soni va eng eski sana | 1 ta | KRITIK | Kassalar bo'limida yiqilgan cheklarni ko'ring va «Qayta yuborish» bilan qo'lda qayta yuboring. |
| Fiskal cheklar yiqilishi: 30 kundan ortiq kutayotgan chek | Chek 30 kundan ortiq `pending` holatida turibdi: bunday chek bo'lmasligi kerak (qayta yuborish ishi uni o'zi `failed` qiladi), demak bu ish to'xtagan | 1 ta | KRITIK | `scheduler` ishlayotganini tekshiring (`docker compose ps scheduler`) va cheklarni Kassalar bo'limida qo'lda qayta yuboring. |
| Uzluksiz zaxira (PITR) eskirgan: tashqi nusxa yo'q | PITR yoqilgan, lekin S3 sozlanmagan: yagona nusxa baza bilan bir diskda, disk buzilsa hammasi yo'qoladi. Doimiy ogohlantirish: bir marta yuboriladi va S3 sozlanguncha markazda ochiq turadi | S3 yo'q | DIQQAT | S3 kalitlarini (`PITR_S3_*`) ildizdagi `.env` ga yozing (17-bo'lim). |
| Tiklash mashqi: ilova tekshiruvisiz | Oylik avtomatik mashq ilova tekshiruvini (`db:restore-smoke`) o'tkazmaydi: u o'tgan hisoblanadi, lekin to'liq tekshirilmagan. Ilova tekshiruvi bilan to'liq mashq uzoq bo'lmagan | 100 kun | DIQQAT | Serverda `scripts/restore-drill.sh` ni ishga tushiring (choragida bir marta, 19-bo'lim). |
| Jurnallarning oylik bo'limlari: himoya triggeri yo'q | `fiscal_receipt_keys_insert` yoki audit triggeri yo'qolgan: fiskal cheklar takrorlanishdan himoyasiz | trigger yo'q | KRITIK | `php artisan db:partitions:ensure` bajaring (triggerni tiklaydi); ogohlantirish yopilmasa texnik yordamga murojaat qiling. |
| Jurnallarning oylik bo'limlari: arxiv kaliti o'chira oladi | Jurnallar arxivi yoziladigan S3 kaliti fayllarni o'chira oladi: arxiv himoyasiz | kalit o'chira oladi | DIQQAT | O'chirish huquqi yo'q alohida kalit bering (`ARCHIVE_S3_ACCESS_KEY`, `ARCHIVE_S3_SECRET`; 20-bo'lim). |
| Jurnallarning oylik bo'limlari: arxiv faqat shu serverda | S3 yo'q: eski oylar faqat shu serverdagi faylga arxivlangan, bazadan o'chirilmaydi va disk buzilsa yo'qoladi | 1 ta oy | DIQQAT | S3 kalitlarini yozing (20-bo'lim). |
| Jurnallarning oylik bo'limlari: RLS yozuvni rad etdi (M4) | Do'kon so'rovining yozuvini PostgreSQL qator himoyasi bilan rad etdi (kassa yoki panel server xatosi oldi): yo birov boshqa do'kon nomidan yozmoqchi bo'lgan, yo qonuniy amal adashib rad etilgan. Matnda jadval, yo'l, do'kon va necha marta; oxirgisidan bir soat o'tgach o'zi yopiladi | 1 ta | KRITIK | Qonuniy amal bo'lsa darhol o'chiring: ildizdagi `.env` da `TENANT_RLS=0`, `docker compose up -d`; log qatorini dasturchiga yuboring (24-bo'lim). |
| Jurnallarning oylik bo'limlari: RLS yoqilgan, lekin ishlamayapti (M4) | `TENANT_RLS=1`, ammo `rizqim_tenant` roli yo'q, unga o'tib bo'lmaydi, u himoyani chetlab o'tadi yoki unda huquq yetishmaydi: so'rovlar himoyasiz bajariladi (huquq yetishmasa xato beradi). Har daqiqada bazaning o'zidan so'raladi, holat tuzalmaguncha ochiq turadi. Ilova rolining o'zi siyosatga tushib qolgan bo'lsa ham shu yerda chiqadi | darhol | KRITIK | `docker compose run --rm db-roles`, so'ng `app:check-production` (24-bo'lim). |
| Jurnallarning oylik bo'limlari: RLS jadvali himoyasiz (M4) | `TENANT_RLS=1`, ammo 20 ta jadvaldan birida himoya o'chirilgan yoki `tenant` siyosati kelishilgan ko'rinishda emas: do'kon tranzaksiyasi o'sha jadvalda boshqa do'konlarni ko'radi. Matnda jadval nomlari; tuzalmaguncha ochiq turadi | 1 ta jadval | KRITIK | Siyosatni tiklang (24-bo'limdagi SQL) yoki `TENANT_RLS=0`. |
| Jurnallarning oylik bo'limlari: RLS holatini tekshirib bo'lmadi (M4) | `TENANT_RLS=1`, lekin tekshiruv so'rovi ishlamadi: himoya ishlayotgani tasdiqlanmagan | 1 marta | DIQQAT | Takrorlansa `php artisan app:check-production` ni ishga tushirib xatoni o'qing (24-bo'lim). |
| Jurnallarning oylik bo'limlari: egasi roli yo'q | Rollar ajratilgan, lekin scheduler'da `DB_OWNER_USERNAME`/`DB_OWNER_PASSWORD` yo'q: har kechagi `db:partitions:ensure`/`:archive` rad etiladi, keyingi oylar tayyorlanmaydi | darhol | KRITIK | Ildizdagi `.env` ga egasi rolini yozing va `docker compose up -d scheduler` (22-bo'lim). |

Eslatma: `db:partitions:ensure` kabi `php artisan` buyruqlari `docker compose exec -u www-data backend ...` ichida bajariladi. PITR, tiklash mashqi va disk qoidalari uzluksiz zaxira **yoqilmagan** (`PITR_ENABLED` bo'sh) bo'lsa jim turadi: markazda «O'chiq» ko'rinadi.

**Server butunlay o'chsa, ichkaridan hech narsa ogohlantirmaydi.** Shuning uchun ikkita bepul tashqi kuzatuvchi kerak (har biri ~10 daqiqa; ikkalasi ham tashqi xizmat, tugma nomlari vaqt o'tishi bilan o'zgarishi mumkin: haqiqiy hisobda tekshirilmagan).

*1) UptimeRobot: «sayt javob bermayapti».*
1. uptimerobot.com da bepul hisob oching.
2. **Add New Monitor** → turi **Keyword**.
3. URL: `https://<domen>/api/v1/health`; kalit so'z: `"status":"ok"`; ogohlantirish: kalit so'z **topilmasa** (Keyword does not exist).
4. Tekshirish oralig'i: 5 daqiqa (bepul tarifda eng kami).
5. **Alert Contacts** ga egasining Telegram'i yoki e-pochtasini qo'shing (Telegram: UptimeRobot'ning Telegram integratsiyasi) va monitorga bog'lang.
6. Saqlang: bir daqiqadan keyin monitor «Up» bo'lishi kerak. «Down» bo'lib tursa, `curl -i https://<domen>/api/v1/health` ni serverda tekshiring (2-bo'lim).

*2) healthchecks.io: «server yoki scheduler jim bo'lib qoldi» (o'lik odam kalitga o'xshash).*
1. healthchecks.io da bepul hisob oching.
2. Birinchi tekshiruv: **Add Check**, nomi `rizqim-scheduler`, **Period 1 minute**, **Grace Time 5 minutes**. Ko'rsatilgan ping manzilini (`https://hc-ping.com/<uuid>`) nusxalang.
3. Ildizdagi `.env` ga yozing: `HEALTHCHECKS_PING_URL=https://hc-ping.com/<uuid>` va `docker compose up -d`. Scheduler har daqiqalik tekshiruv tugagach shu manzilga so'rov yuboradi; server, scheduler yoki baza ishlamasa so'rovlar to'xtaydi.
4. Ikkinchi tekshiruv: `rizqim-pitr`, **Period 1 day**, **Grace Time 2 hours** (26 soatdan keyin ogohlantiradi). Uning manzilini `.env` ga `PITR_PING_URL=...` deb yozing va `docker compose up -d backup`. `backup` xizmati har muvaffaqiyatli uzluksiz zaxiradan keyin shunga so'rov yuboradi (birinchisi to'liq nusxa tugagach).
5. **Integrations** bo'limida egasining Telegram'i yoki e-pochtasini ulang va ikkala tekshiruvga bog'lang.
6. Tekshirish: 1–2 daqiqadan keyin `rizqim-scheduler` yashil («Up») bo'ladi. `.env` da manzil bo'sh bo'lsa hech narsa yuborilmaydi.

## 22. Baza rollari (M4)

Ilova bazaga jadvallar egasi yoki superuser sifatida ulanmaydi: ilovadagi xato (yoki buzg'unchi) jadvalni o'chira, trigger va
himoyalarni o'chira olmaydi. Rollar:

| Rol | Kim ishlatadi | Huquqi |
|---|---|---|
| bootstrap superuser (`POSTGRES_USER`, mavjud o'rnatishda eski `DB_USERNAME`) | `postgres` konteyneri, `backup` (pg_dump, pgBackRest, tiklash mashqi, jurnallar arxivi), `db-roles` | hammasi |
| `rizqim_owner` (`DB_OWNER_USERNAME`) | migratsiyalar (`backend` konteyneri ishga tushganda, `php artisan migrate`), `db:partitions:ensure` (00:40 UTC) va `db:partitions:archive` (22:40 UTC, `scheduler`) | sxemadagi hamma obyektning egasi |
| `rizqim_app` (`DB_USERNAME`) | php-fpm (PgBouncer orqali), queue, queue-reports, scheduler, reverb | faqat SELECT/INSERT/UPDATE/DELETE, ketma-ketliklar, `pg_monitor` (Monitoring uchun); 3-bosqichdan `BYPASSRLS` va `rizqim_tenant` ga o'tish huquqi (24-bo'lim) |
| `rizqim_tenant` (`DB_TENANT_ROLE`, 3-bosqich) | hech kim ulanmaydi (parolsiz, NOLOGIN): `TENANT_RLS=1` bo'lsa ilova do'kon so'rovining tranzaksiyasi ichida shu rolga o'tadi | `rizqim_app` niki bilan bir xil o'qish-yozish; qator himoyasi (RLS) faqat shu rolga ta'sir qiladi |

`DB_OWNER_USERNAME` bo'sh bo'lsa hammasi avvalgidek bitta rol bilan ishlaydi (`app:check-production` faqat ogohlantiradi).
Egasining paroli faqat `backend` (migratsiya uchun; php-fpm ishga tushishidan oldin muhitdan o'chiriladi) va `scheduler`
konteynerlariga beriladi, `bootstrap/cache/config.php` ga hech qachon yozilmaydi (`app:check-production` tekshiradi). Uni
`backend/.env` ga yozmang: u fayl hamma konteynerga tushadi.

**`db-roles` xizmati** (`scripts/db-roles.sh`, bir martalik) har `docker compose up` da backend, queue, scheduler, reverb va
pgbouncer'dan oldin ishlaydi: yo'q rollarni yaratadi, parollarni `.env` dan qo'yadi, huquqlarni beradi (egasi keyin yaratadigan
jadval va oylar uchun ham) va egasi `rizqim_owner` bo'lmagan har obyektni unga o'tkazadi. Har obyekt alohida, 2 soniyalik
kutish chegarasi bilan; band obyektlarni 5 soniyada bir qayta urinadi, boshlanishidan 300 soniya o'tgach boshqa urinmaydi
(odatda ularni kechki `pg_dump` ushlab turadi). Ilova ishlab turganda qo'lda ishga tushirilsa, band jadvalga har urinishda
o'qish va yozish ham 2 soniyagacha kutib qoladi (o'lchangan: `orders` dagi oddiy SELECT har urinishda 1,3-1,6 s), shuning uchun
tinch paytda ishga tushiring. Shundan keyin ham band qolganlarini oxirida bitta «XATO: N ta
obyekt ... ko'chirilmadi» qatorida nomlari bilan aytadi, lekin xizmat 0 bilan tugaydi va stek ishga tushadi: ilovaning
huquqlari egalikka bog'liq emas. Biroq o'sha obyektni o'zgartiradigan keyingi migratsiya «must be owner» bilan yiqiladi va
`backend` qayta-qayta ishga tushib, veb va kassa API'si ishlamay qoladi: bunday qator ko'rinsa, deploy'dan oldin uzun
tranzaksiya yo'qligini tekshirib (pastda) `docker compose run --rm db-roles` ni qayta bajaring. Monitoring buni ham
ko'rsatadi (21-bo'lim: «N ta jadval rizqim_owner niki emas»). Qayta ishga tushirish xavfsiz: ikkinchi marta «ko'chiriladigan
obyekt yo'q» deydi.

**Mavjud o'rnatishda birinchi marta** (jadvallar soniga qarab bir necha soniya). Vaqti: 04:45-05:30 Toshkent. Kechki ishlar
(Toshkent vaqti): 02:30 dan zaxira zanjiri (pgBackRest, so'ng `pg_dump` hamma jadvalni o'qish qulfida ushlaydi, so'ng
jurnallar arxivi), 03:40 `db:partitions:archive`, oyning birinchi dushanbasi 04:00 tiklash mashqi (UTC bo'yicha birinchi
yakshanba 23:00), 05:00 `model:prune` va `queue:prune-failed` (qisqa DELETE: band jadvalni db-roles bir necha soniyadan keyin
qayta uradi, zararsiz), 05:40 `db:partitions:ensure`. Shuning uchun 02:30-04:30 oralig'ida va oyning birinchi dushanbasida
qilmang. `backup` konteyneri `db-roles` tugashini kutadi, shuning uchun `up -d --build` dagi ishga tushishdagi zaxira
(`BACKUP_RUN_ON_START`) rollar ko'chirilib bo'lgach boshlanadi. Boshlashdan oldin uzun
tranzaksiya (ishlab turgan zaxira) yo'qligini tekshiring: quyidagi buyruq hech narsa chiqarmasligi kerak.
```bash
docker compose exec postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "SELECT pid, application_name, state FROM pg_stat_activity WHERE extract(epoch FROM now() - xact_start) > 5"'
docker compose exec backup sh /usr/local/bin/backup.sh        # avval zaxira (tugashini kuting)
# ildizdagi .env: eski DB_USERNAME/DB_PASSWORD -> POSTGRES_USER/POSTGRES_PASSWORD (o'zgarmaydi, superuser bo'lib qoladi);
#   DB_USERNAME=rizqim_app, DB_PASSWORD=<yangi parol>; DB_OWNER_USERNAME=rizqim_owner, DB_OWNER_PASSWORD=<yangi parol>
#   (uchala parol har xil: openssl rand -hex 24)
git pull && docker compose up -d --build                      # db-roles -> pgbouncer -> backend (migrate egasi bilan) -> ...
docker compose logs db-roles                                  # nima ko'chirilgani; «XATO: ... ko'chirilmadi» bo'lmasin
docker compose exec -u www-data backend php artisan app:check-production
docker compose exec -u www-data scheduler php artisan db:partitions:ensure   # egasi bilan ishlashini tekshirish
```
PgBouncer yangi foydalanuvchi bilan qayta ishga tushadi: shu soniyalarda kelgan so'rovlar xato oladi, kassalar oflayn navbatga
yozadi (`pos_sync`). Qaytarish: `.env` da `DB_USERNAME/DB_PASSWORD` ni yana bootstrap qiymatiga qaytaring,
`DB_OWNER_USERNAME` ni bo'sh qiling, `docker compose up -d` (egalik `rizqim_owner` da qoladi, superuser hammasini qila oladi).

**Parolni almashtirish:** ildizdagi `.env` da `DB_PASSWORD` yoki `DB_OWNER_PASSWORD` ni o'zgartiring va
`docker compose up -d db-roles pgbouncer backend queue queue-reports scheduler reverb` (db-roles yangi parolni qo'yadi, qolganlari
yangi parol bilan qayta ulanadi). Bootstrap parolini (`POSTGRES_PASSWORD`) almashtirish:
`docker compose exec postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "\password"'` (yangi parolni so'raydi),
so'ng ildizdagi `.env` da `POSTGRES_PASSWORD` ni yangilang va `docker compose up -d --no-deps backup db-roles` (`--no-deps`:
Postgres hozir qayta ishga tushmaydi). `postgres` konteynerining muhiti o'zgargani uchun keyingi to'liq `docker compose up -d`
uni bir marta qayta yaratadi: Postgres bir necha soniya to'xtaydi va ilova uziladi, buni tinch paytda yoki deploy bilan qiling.

**Qo'lda migratsiya va `db:partitions:*`:** `docker compose exec -u www-data scheduler php artisan ...` (yoki `backend`).
`docker compose exec` konteynerning compose'dagi muhitini oladi, shuning uchun bu ikkisida `DB_OWNER_*` bor (entrypoint ularni
faqat php-fpm jarayonlaridan olib tashlaydi). `queue`, `queue-reports`, `reverb` da ular yo'q: u yerda migratsiya «must be
owner» bilan, `db:partitions:*` esa «bo'limlarga tegilmadi» bilan rad etiladi.

**Rad etishlar:** `db:partitions:*` egasi rolisiz ishga tushsa «XATO: bo'limlarga tegilmadi ... DB_OWNER_USERNAME va
DB_OWNER_PASSWORD bering» deydi va 1 bilan chiqadi (hech narsa o'zgarmaydi); Monitoring darhol KRITIK ogohlantiradi (21-bo'lim).
`app:check-production` rollar ajratilgandan keyin superuser, jadvallar egasi yoki `rizqim_owner` ga `SET ROLE` qila oladigan
`DB_USERNAME` bilan konteynerni ishga tushirmaydi. Monitoring egasi roli bilan har daqiqa ulanib tekshiradi: parol xato yoki
jadvallar unga tegishli bo'lmasa KRITIK; boshqa rolniki bo'lib qolgan jadvallar DIQQAT.

## 23. `payments.store_id` yakunlanmagan (M4)

Migratsiya `2026_11_27_100000_add_store_id_to_payments_table` har to'lovga do'konini (buyurtmaniki yoki Payme orqali hisob
to'ldirish so'rovidagi do'kon) yozadi. Do'koni topilmagan to'lov bo'lsa (amalda: buyurtmasiz Payme to'ldirish to'lovi, uning
so'rovi do'kon qo'lda butunlay o'chirilganda yo'qolgan), migratsiya ularni taxmin qilmaydi: ustun NULL ga ruxsat bilan qoladi,
deploy davom etadi, Monitoringda `partitions` tekshiruvi «payments.store_id hali NULL bo'lishi mumkin» DIQQAT ogohlantirishini
sonini ko'rsatib chiqaradi (21-bo'lim; jadval katta bo'lib sanash vaqtida tugamasa, son o'rniga «sanab bo'lmadi» deb yozadi, ogohlantirish yopilmaydi). Bunday to'lovni do'konga bog'langan kassa ko'rmaydi.

Egasi roli bilan (`rizqim_owner`, rollar ajratilmagan bo'lsa `DB_USERNAME`) tekshiring:

```sql
SELECT id, uuid, provider, amount, status, created_at, payload -> 'account' ->> 'order_id' AS reference
FROM payments WHERE store_id IS NULL ORDER BY id;
```

Har birini hal qiling: do'kon ma'lum bo'lsa `UPDATE payments SET store_id = <do'kon id> WHERE id = <id>;`, keraksiz yetim
qator bo'lsa zaxiradan keyin `DELETE FROM payments WHERE id = <id>;`. So'rov 0 qator qaytargach, NOT NULL qadamini yakunlang
(tinch paytda; har buyruq qisqa, kassalar to'xtamaydi):

```sql
SET lock_timeout = '500ms';
ALTER TABLE payments ADD CONSTRAINT payments_store_id_not_null CHECK (store_id IS NOT NULL) NOT VALID;
ALTER TABLE payments VALIDATE CONSTRAINT payments_store_id_not_null;
BEGIN;
ALTER TABLE payments ALTER COLUMN store_id SET NOT NULL;
ALTER TABLE payments DROP CONSTRAINT payments_store_id_not_null;
COMMIT;
```

`lock_timeout` xatosi chiqsa, o'sha buyruqni qayta bajaring. Shundan keyin Monitoring ogohlantirishi o'zi yopiladi.

## 24. Qator darajasidagi himoya: RLS (M4)

**Nimani himoya qiladi.** Ilovadagi xato (unutilgan do'kon filtri, yangi yo'l) bir do'kon xodimiga boshqa do'konning pul
qatorlarini ko'rsatib yoki o'zgartirtirib qo'ymasin: endi buni PostgreSQL'ning o'zi ham rad etadi. 20 ta jadvalda
`tenant` siyosati bor: `orders`, `order_bills`, `payments`, `fiscal_receipts`, `pos_shifts`, `cash_movements`, `pos_refunds`,
`pos_tips`, `pos_qr_payments`, `customer_accounts`, `customer_account_entries`, `vouchers`, `voucher_entries`,
`booking_payments`, `banquet_payments`, `banquet_payment_entries`, `staff_pay_statements`, `store_transactions`,
`store_wallet_transactions`, `audit_logs`. Siyosat faqat `rizqim_tenant` roliga ta'sir qiladi. Do'kon so'rovi (kassa,
hamkor paneli, xodim ilovasi) tranzaksiya ochganda ilova shu rolga o'tadi va do'kon raqamini `app.store_id` ga yozadi
(ikkalasi tranzaksiya bilan birga tugaydi, PgBouncer'da keyingi mijozga o'tmaydi). Shu tranzaksiya ichida boshqa
do'konning qatori o'qilmaydi, uni to'g'ridan-to'g'ri o'zgartirish yoki o'chirish 0 qatorga ta'sir qiladi, boshqa do'kon
nomidan yozish xato beradi. Siyosat **qamramaydigan** narsalar (PostgreSQL shunday ishlaydi): tashqi kalit amallari
(`ON DELETE CASCADE` / `SET NULL` jadval egasi nomidan bajariladi: siyosat ostida bo'lmagan ota qator, masalan foydalanuvchi
yoki filial o'chirilsa, unga bog'langan pul qatorlari qaysi do'konda bo'lmasin birga o'chadi yoki o'zgaradi; shuning uchun
ilova bunday ota qatorlarni o'chirmaydi), o'z do'koni nomidan yozilgan qatorning boshqa do'kon buyurtmasiga ishora qilishi
(`payments` dan tashqari: unda trigger do'konni buyurtmadan oladi) va o'zida `store_id` bo'lmagan bola jadvallar
(`order_items` va h.k.): bularni avvalgidek ilovaning o'zi qo'riqlaydi. Qolgan hammasi (navbat ishchilari, scheduler, vebhuklar, boshqaruv markazi, market, tranzaksiyadan tashqaridagi
o'qishlar) avvalgidek `rizqim_app` roli bilan ishlaydi: unda `BYPASSRLS` bor, siyosat unga tegmaydi. Jadvallar egasi
(`rizqim_owner`: migratsiyalar, `db:partitions:*`) va bootstrap superuser (zaxira, tiklash mashqi) ham chetlab o'tadi.
Bitta rolli o'rnatishda (22-bo'lim bajarilmagan) siyosatlar bor, lekin hech narsani o'zgartirmaydi: ilova jadvallar egasi.
Filial darajasidagi ajratish avvalgidek ilovada.

**Yoqish (rollar ajratilgan bo'lishi shart, 22-bo'lim).** Ikki qadam, orasida kamida bir kun kuzating:
```bash
# 1-qadam: kod. db-roles rizqim_tenant rolini yaratadi va rizqim_app ga BYPASSRLS beradi, migratsiya siyosatlarni qo'yadi
#   (20 ta jadval, har biri soniyaning ulushi; band jadvalni har urinishda 0,5 s dan ortiq kutmaydi va bir daqiqagacha
#   qayta urinadi; shundan keyin ham band bo'lsa «lock timeout» (55P03) bilan to'xtaydi va backend qayta ishga tushganda
#   qolgan jadvallardan davom etadi: qayta ishga tushirish xavfsiz. Jadvallarni uzoq ushlab turadigan yagona narsa
#   kechki zaxira (pg_dump, 21:30 UTC dan) va BACKUP_RUN_ON_START: bu relizni zaxira ketayotgan paytda joylamang). Bu qadamda hech narsa o'zgarmaydi: TENANT_RLS hali yo'q.
git pull && docker compose up -d --build
docker compose logs db-roles | tail -5                # «... rizqim_tenant (do'kon tranzaksiyasi roli, NOLOGIN)»
docker compose exec -u www-data backend php artisan app:check-production
# 2-qadam: yoqish. Ildizdagi .env ga TENANT_RLS=1 yozing
docker compose up -d                                  # backend, queue, scheduler, reverb yangi sozlama bilan qayta yaratiladi (soniyalar)
```
`app:check-production` `TENANT_RLS=1` bilan konteynerni ishga tushirmaydi, agar rol nomi noto'g'ri bo'lsa, ilova
`rizqim_tenant` roliga o'ta olmasa, o'sha rol himoyani chetlab o'tsa (superuser, `BYPASSRLS`, jadvallar egasining a'zosi)
yoki unda ilova rolidagi biror huquq yetishmasa: avval `docker compose run --rm db-roles`.
`TENANT_RLS` qanday bo'lmasin, ilova rolining o'zi siyosatga tushib qolgan bo'lsa (unda `BYPASSRLS` yo'q) ham ishga
tushirmaydi: bunda navbat va vebhuklar bu jadvallarda hech narsa ko'rmay qolar edi; migratsiya ham shu holatda siyosatlarni
qo'ymaydi va «Avval rollarni yangilang» deb to'xtaydi.

**Yoqilganini tekshirish.**
```bash
docker compose exec -u www-data backend php artisan app:check-production   # «Qator himoyasi (RLS): yoqilgan ...» qatori
docker compose exec postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "SELECT count(*) FROM pg_class c JOIN pg_policy p ON p.polrelid = c.oid WHERE c.relrowsecurity AND p.polname = '"'"'tenant'"'"'"'   # 20
docker compose exec postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "SELECT rolname, rolbypassrls, rolcanlogin FROM pg_roles WHERE rolname IN ('"'"'rizqim_app'"'"', '"'"'rizqim_tenant'"'"')"'   # rizqim_app|t|t va rizqim_tenant|f|f
```

**Rad etish qanday ko'rinadi.** Do'kon so'rovi boshqa do'kon (yoki hech bir do'kon) nomidan shu jadvallarga yozmoqchi
bo'lsa, PostgreSQL `new row violates row-level security policy for table "..."` (SQLSTATE 42501) deydi. Bunda:
- kassa yoki panel odatdagi server xatosini oladi (HTTP 500, `{"message": "Server Error"}`), amal bajarilmaydi;
- `laravel.log` da (va Sentry'da) KRITIK qator: `tenant_rls: PostgreSQL refused a write (row-level security)` jadval,
  yo'l (masalan `POST api/v1/pos/bills/{uuid}/payments`) va do'kon raqami bilan; qatorda mijoz ma'lumoti yo'q;
- Telegram'ga KRITIK ogohlantirish keladi («Jurnallarning oylik bo'limlari» qoidasi ostida: «Qator himoyasi (RLS) yozuvni
  rad etdi: 3 marta (oxirgisi: jadval payments, yo'l POST api/v1/pos/bills/{uuid}/payments, do'kon 12)»); oxirgi rad
  etishdan bir soat o'tgach o'zi yopiladi. Ogohlantirish Redis keshi orqali ishlaydi (`CACHE_STORE=redis`).

Bu ikki narsadan biri: kimdir boshqa do'kon nomidan yozishga uringan (himoya ishladi) yoki ilovadagi qonuniy yo'l
adashib rad etilgan (xato: kassa ishlamay qoldi). Ikkinchisida **darhol o'chiring** (pastda) va log qatoridagi jadval bilan
yo'lni dasturchiga yuboring. O'qish, o'zgartirish va o'chirishda xato chiqmaydi: begona qator shunchaki ko'rinmaydi
(0 qator), shuning uchun bunday holat logda ham, ogohlantirishda ham bo'lmaydi.

**Himoya holati har daqiqada tekshiriladi.** Ishlab turgan paytda rol yo'qolsa yoki a'zolik olib tashlansa (`TENANT_RLS=1`, ammo
ilova rolga o'ta olmaydi), so'rovlar to'xtamaydi: ular himoyasiz, `TENANT_RLS=0` dagidek bajariladi (kassa to'xtab qolgandan
ko'ra shunisi ma'qul). Buni ikki joy aytadi: o'sha so'rovning o'zi `laravel.log` ga (va Sentry'ga) darhol KRITIK qator yozadi
(`tenant_rls: cannot switch to the tenant role, this request runs WITHOUT row-level security`), Monitoring esa har daqiqada
bazaning o'zidan so'raydi va KRITIK ogohlantirishni bir daqiqa ichida Telegram'ga yuboradi: «Qator himoyasi (RLS) yoqilgan,
lekin ishlamayapti: ...». U holat tuzalmaguncha ochiq turadi (savdo bo'lmagan soatda ham), tuzalgach o'zi yopiladi. Sabablari:
rol yo'q, rolga o'tib bo'lmaydi, rol himoyani chetlab o'tadi yoki rolda ilova rolidagi biror huquq yetishmaydi (bunda do'kon
so'rovlari «permission denied» bilan yiqiladi). Hammasiga davo bitta: `docker compose run --rm db-roles`. Xuddi shu
tekshiruv konteyner ishga tushganda ham o'tadi (`app:check-production`): noto'g'ri `DB_TENANT_ROLE` yoki yetishmagan huquq
birinchi savdoda emas, ishga tushishda ushlanadi.

Shu tekshiruv jadvallarning o'zini ham ko'radi: 20 ta jadvalning har birida himoya yoqilganmi va unda aynan bitta, kelishilgan
`tenant` siyosati turibdimi. Kimdir jadvalda himoyani o'chirsa, siyosatni o'zgartirsa yoki yoniga hammasiga ruxsat beradigan
boshqa siyosat qo'shsa, hech qanday xato chiqmaydi, lekin do'kon tranzaksiyasi o'sha jadvalda boshqa do'konlarni ko'ra
boshlaydi: Monitoring «... N ta jadval himoyasiz (jadval nomlari)» deb KRITIK ogohlantiradi, `app:check-production` esa
konteynerni ishga tushirmaydi. Tiklash (egasi roli bilan, har bir nomlangan jadval uchun):
```sql
DROP POLICY IF EXISTS tenant ON <jadval>;   -- va ortiqcha siyosatlarni ham: SELECT polname FROM pg_policy WHERE polrelid = '<jadval>'::regclass;
CREATE POLICY tenant ON <jadval> USING (store_id = nullif(current_setting('app.store_id', true), '')::bigint);
ALTER TABLE <jadval> ENABLE ROW LEVEL SECURITY;
```
Tekshiruvning o'zi ishlamasa (baza javob bermadi, so'rov vaqtida tugamadi), bu «hammasi joyida» degani emas:
`TENANT_RLS=1` bo'lsa Monitoring «holatini tekshirib bo'lmadi» DIQQAT ogohlantirishini beradi, ishga tushishda esa konteyner
ishga tushmaydi. Tekshirilmaydigan yagona narsa: yuqorida aytilgan tashqi kalit amallari va `store_id` siz bola jadvallar.

Harakatlar jurnali (`audit_logs`): do'kon so'rovi o'z do'koni nomidan yozadi; do'konsiz (platforma darajasidagi) yozuv
o'tkaziladi; boshqa do'kon nomidan yozuv esa rad etiladi (yuqoridagi rad etish kabi ko'rinadi).

**O'chirish (qaytarish), deploy'siz.** Ildizdagi `.env` da `TENANT_RLS=0` (yoki qatorni o'chiring), so'ng:
```bash
docker compose up -d        # konteynerlar yangi sozlama bilan qayta yaratiladi; kassalar bir necha soniya oflayn navbatga yozadi
```
`docker compose restart` yetmaydi: sozlama konteyner muhitidan va ishga tushishda yoziladigan `config:cache` dan o'qiladi,
`restart` esa eski muhitni saqlaydi. Shundan keyin ilova rolga o'tmaydi va hammasi 3-bosqichdan oldingidek ishlaydi;
siyosatlar jadvallarda qoladi, lekin hech kimga ta'sir qilmaydi. Siyosatlarning o'zini olib tashlash kerak bo'lsa (odatda
kerak emas), egasi roli bilan har jadvalga: `ALTER TABLE <jadval> DISABLE ROW LEVEL SECURITY;` (siyosat saqlanadi,
`ENABLE` bilan qaytadi).

**Bilib qo'ying.**
- Yangi oylik bo'limlar (`db:partitions:ensure`) va arxivdan qaytarilgan oylar (`scripts/archive-restore.sh`) uchun hech
  narsa qilish shart emas: `fiscal_receipts` va `audit_logs` dagi siyosat ota jadvalda turadi va u orqali o'tgan har
  so'rovga ta'sir qiladi; ilova bo'limni nomi bilan chaqirmaydi.
- `pg_dump`, tiklash mashqi va jurnallar arxivi bootstrap superuser bilan ishlaydi: siyosat ularga tegmaydi. Zaxiradan
  boshqa serverga tiklanganda siyosatlar baza bilan birga keladi, rollar esa yo'q: `docker compose up -d` dagi `db-roles`
  ularni yaratadi.
- Qo'lda `psql` bilan `rizqim_app` yoki superuser sifatida ulansangiz hamma qatorni ko'rasiz. Do'kon ko'zi bilan ko'rish:
  `BEGIN; SET LOCAL ROLE rizqim_tenant; SET LOCAL app.store_id = '<do'kon id>'; SELECT ...; ROLLBACK;`.

## 25. Kassa navbatini fayldan tiklash (M5 S4)

Kassa internetsiz ishlaganda sotuv va to'lovlar uning navbatida (jurnal faylida) turadi. Kassa buzilsa, Windows qayta
o'rnatilishi kerak bo'lsa yoki uzoq vaqt internetga chiqa olmasa, navbat **faylga** saqlanadi va serverga qo'lda
yuklanadi. Server faylni tekshiradi va amallarni o'sha kassa nomidan, oddiy sinxron bilan bir xil qo'llaydi: hech
narsa ikki marta yozilmaydi, to'lov hech qachon tashlanmaydi. Texnik tafsilotlar: `docs/API.md` «Navbat faylidan
tiklash», `docs/scale/M5_DESIGN.md` 5-bo'lim.

**Qachon kerak.** Kassada «yuborilmagan amallar» bor va: kassa internetga chiqa olmaydi (2 soatdan ko'p), kassani
uzish yoki almashtirish kerak, yoki kassa ilovasi qayta o'rnatiladi. Kassa ishlab tursa va internet qaytsa, hech narsa
qilish shart emas: navbat o'zi ketadi.

**1. Faylni kassadan oling** (kassa ishga tushishi kerak: faylni faqat kassaning o'zi imzolay oladi).
- Kassa → Sozlamalar → «Zaxira» → «Navbatni faylga saqlash». Ekranda fayl joyi ko'rinadi, nomi
  `rizqim-navbat-<kassa uuid>-<birinchi>-<oxirgi>.rzq` (Windows: `Hujjatlar\RIZQIM\<kassa uuid>\`).
- Faylni fleshkaga yoki telefonga ko'chiring. **Faylni ochib saqlamang** (Bloknot va boshqa muharrirlar qator
  oxirini o'zgartiradi): bitta bayt o'zgarsa ham server faylni `file_mac_invalid` bilan rad etadi.
- Kassani **uzishdan oldin** faylni oling. Uzilgandan keyin sanalgan amallar ham yoziladi (sotuv va to'lov hech qachon
  tashlanmaydi), lekin har biri «Sinxronlash xatolari»da `device_revoked` bo'lib turadi va menejer tekshirishi kerak;
  shu paytdagi chegirma va taom o'chirish qabul qilinmaydi (schyot to'liq narxda).
- Amal ko'p bo'lsa kassa bir nechta fayl yozadi (har birida 10 000 tagacha amal): hammasini `seq` tartibida yuklang
  (fayl nomidagi birinchi raqam bo'yicha). Tartib buzilsa ham hech narsa yo'qolmaydi, lekin natijada «Fayl N-amaldan
  boshlanadi, serverda esa oldingilari yo'q» ogohlantirishi chiqadi (`earlier_numbers_missing`): qolgan faylni ham
  yuklang.

**2. Faylni yuklang** (imkon qadar **3 kun ichida**: kechroq yuklangan faylda kassir tekshiruvi o'tmaydi — sotuvlar
`staff_not_signed_in` bilan rad etiladi, to'lovlar `staff_unverified` bo'lib yoziladi).
- Egasi yoki filial menejeri: hamkor paneli → Qurilmalar → «Navbat faylini yuklash» (`devices.manage` huquqi; kassa
  PIN sessiyasidan emas). Menejer faqat o'z filiali kassasining faylini yuklay oladi.
- Ega yuklay olmasa — RIZQIM qo'llab-quvvatlash: boshqaruv markazi → POS → Qurilmalar → «Navbat faylini import
  qilish» (`stores.manage`, qayta tasdiq va sabab yoziladi). Har qanday do'kon kassasi uchun ishlaydi.
- Ekranlar tayyor bo'lmaguncha (M5 S4-c) yo'lning o'zi: `POST /api/v1/owner/pos/sync/import` (egasining panel tokeni,
  `multipart/form-data`, maydon `file`) yoki `POST /api/v1/admin/pos/sync-import` (qo'shimcha `reason`).
- Fayl 8 MB gacha (nginx 16 MB gacha o'tkazadi).

**3. Natijani o'qing.**
- «Fayl o'qildi: N ta amal yozildi, M tasi avval kelgan.» — tayyor. Faylni ikkinchi marta yuklash zararsiz: hammasi
  «avval kelgan» bo'lib qaytadi.
- «... ta amal qoldi: shu faylni yana bir marta yuklang.» (`complete: false`) — bitta so'rov ~20 soniya ishlaydi yoki
  kassaning o'zi aynan shu paytda yuborayotgan edi. **Shu faylni** yana yuklang, toki qoldiq 0 bo'lguncha.
- Rad etilgan va farq bilan qabul qilingan amallar (masalan imzosi buzuq, kassa uzilganidan keyin yozilgan —
  `device_revoked`, 12 oydan eski — `operation_too_old`) odatdagi «Sinxronlash xatolari»da turadi: menejer ularni har
  doimgidek hal qiladi. «O'tkazib yuborilgan qatorlar» (`skipped`) — o'qib bo'lmaydigan qatorlar, kassaning avvalgi
  ulanishi bo'lmagan qurilmaga (ishlab turgan kassa, boshqa filial) yozilgan qatorlar (`device_unknown`) yoki avvalgi
  ulanish kaliti bilan imzolanmagan qatorlar (`signature_invalid`); ular uchun hech narsa yozilmagan.
- Importdan keyin kassaning navbat hisoboti kamayadi: kassa smenasini yopish mumkin.

**Butun fayl rad etilsa** (hech narsa yozilmaydi):

| Sabab | Nima qilish kerak |
|---|---|
| `device_not_found` (404) | Fayl boshqa do'kon (yoki boshqa filial) kassasiniki. To'g'ri do'kon egasi yuklasin yoki boshqaruv markazidan |
| `not_export_file` | Bu `.rzq` navbat fayli emas yoki u kesilgan (to'liq ko'chirilmagan). Faylni kassadan qaytadan oling |
| `version_unsupported` | Fayl yangiroq kassa ilovasida yozilgan. Serverni yangilang yoki dasturchiga yozing |
| `file_too_large` | 8 MB yoki 10 000 amaldan ko'p. Kassa kichikroq fayllarga bo'lib yozadi: ularni yuklang |
| `not_a_till` | Fayldagi qurilma kassa emas (KDS, ekran). Dasturchiga yozing |
| `key_unavailable` | Serverda kassaning kaliti yo'q: kassa uzilganiga 30 kundan oshgan (kalit avtomatik o'chiriladi) yoki kalit ikki marta almashtirilgan. Faylni tekshirib bo'lmaydi; dasturchiga yozing (amallarni qo'lda kiritish kerak bo'ladi) |
| `file_mac_invalid` | Fayl o'zgartirilgan, to'liq emas yoki boshqa kassa kaliti bilan. Asl faylni kassadan qaytadan oling; muharrirda ochmang |

**Bilib qo'ying.**
- Uzilgan kassaning kaliti uzilgandan keyin **30 kun** saqlanadi (`pos:erase-sync-keys`, har kuni). Shu muddat ichida
  uning fayli qabul qilinadi; keyin — `key_unavailable`.
- Favqulodda o'chirgich `POS_SYNC_SIGNING=false` kassalarda «Navbatni faylga saqlash»ni yashiradi (`offline.export:
  false`), lekin allaqachon yozilgan fayllarni yuklash ishlaydi.
- Har yuklash auditda: do'kon jurnalida `pos.sync_imported` (kim, qaysi kassa, sonlar, birinchi va oxirgi `seq`;
  so'rov xato bilan uzilsa ham — shu paytgacha qilingani bilan), boshqaruv markazidan bo'lsa qo'shimcha
  `admin.pos.sync_imported` (sabab bilan). Rad etilgan urinish (`device_not_found`, `not_a_till`, `key_unavailable`,
  `file_mac_invalid`) — urinish qilgan do'kon jurnalida `pos.sync_import_refused` (sabab va fayldagi kassa uuid).
  Bir xil sabab bilan ko'p rad etish — kimdir begona yoki o'zgartirilgan fayllarni sinayapti: tekshiring. Kalit va
  amallar mazmuni auditda yo'q.
- Import kassaning «oxirgi xabari» hisoblanmaydi: jim kassa monitoringi (`sync_reported_at`) importdan keyin ham
  kassani jim deb ko'radi — bu to'g'ri, kassaning o'zi hali xabar bermagan.
- Faylni faqat **qaytib sinxronlamaydigan** kassa uchun yuklang (uzilgan, almashtirilgan, qayta o'rnatiladigan). Ishlab
  turgan kassa keyin o'zi sinxronlasa, ikki marta hech narsa yozilmaydi (`op_id`), lekin import raqamlarni qayta
  bergan bo'lsa (`renumbered_from`) kassa o'z navbatini qayta raqamlaydi va egaga bir marta yolg'on «kassa navbatidan
  amal yo'qolgan» (`chain_gap`) xabari kelishi mumkin. Amallar yo'qolmagan: menejer «Sinxronlash xatolari»ni ko'radi.
- Kassa navbatining **ko'zgu nusxasi** (mirror) kassa kompyuterida ochiq papkada turadi: Windows — `Hujjatlar\RIZQIM\<kassa
  uuid>\`, Android — ilovaning tashqi fayllar papkasi (`Android/data/.../files/RIZQIM/<uuid>`). Unda buyurtmalar, summalar
  va xodim raqamlari bor (kalit yo'q, qatorlar imzolangan: o'zgartirilsa server sezadi). Kim o'qiy olishini biling:
  Android 9 va undan eski qurilmalarda bu papkani boshqa ilovalar ham o'qiy oladi; Windows'da «Hujjatlar» OneDrive
  bilan sinxronlansa nusxa bulutga ham ketadi. Kassa kompyuterida OneDrive «Hujjatlar» zaxirasini o'chiring yoki bu
  papkani istisno qiling, kassa kompyuteriga begonalarni qo'ymang, eski Android kassalarga boshqa ilova o'rnatmang.
- Kassa umuman ishga tushmasa (disk buzilgan), fayl yozib bo'lmaydi: imzo kaliti shu kassaning himoyalangan
  xotirasida. Bu holat hozircha dasturchi orqali (M5 S4 ochiq masalasi).

## 26. POS ilovalarini chiqarish (M5 S5)

Kassa, Ofitsiant, Oshxona ekrani va Mijoz ekrani ilovalarining yangi versiyasi do'konsiz, pos.rizqim.uz orqali tarqatiladi.
Kassalar yangilanish xabarini serverdan oladi (reyestrdagi qator bo'yicha), shuning uchun fayl **va** reyestr qatori ikkalasi
bo'lishi kerak. Hech qanday xabar savdoni to'xtatmaydi.

**Bir martalik tayyorgarlik** (kassalar o'rnatilishidan oldin, keyinga qoldirib bo'lmaydi):

1. Android imzo kalitini yarating va xavfsiz joyda saqlang (parol menejeri, ikki nusxa): `keytool -genkeypair -v -keystore release.keystore -alias rizqim -keyalg RSA -keysize 4096 -validity 10000`. **Kalit yo'qolsa yoki o'zgarsa, o'rnatilgan kassalarni joyida yangilab bo'lmaydi** (ularni o'chirib qayta o'rnatish kerak, ma'lumot yo'qoladi). Debug kalit bilan chiqarilgan fayl ham yangilanmaydi.
2. GitHub repository secrets: `ANDROID_KEYSTORE_BASE64` (`base64 -w0 release.keystore`), `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD`. Ular bo'lmasa Android qadami «imzo kaliti hali yo'q» deb to'xtaydi va hech narsa chiqarilmaydi.
3. Serverda `.env` ga `POS_SITE_PORT` (standart 8090) va kerak bo'lsa `POS_FILES_DIR` (standart `./pos-files`) yozing, `docker compose up -d --build pos-site`. Tashqi TLS proksi `pos.rizqim.uz` ni shu portga yo'naltiradi (boshqa saytlar kabi). Sahifa `https://pos.rizqim.uz/` da ochilishini tekshiring.

**Har yangi versiya:**

1. **Versiyani ko'taring.** Chiqariladigan ilovaning `pos/apps/<ilova>/pubspec.yaml` dagi `version:` (masalan `1.4.0+7`; build raqami har safar oshadi) va shu ilovaning `lib/src/app_config.dart` dagi `version`/`build` bir xil bo'lsin (`scripts/check-app-versions.sh` tekshiradi). O'zgarishni main ga qo'shing.
2. **Tag qo'ying.** `git tag pos-v1.4.0 && git push origin pos-v1.4.0`. Har ilovaning versiyasi o'z pubspec'idan olinadi; tag faqat ishni boshlaydi va GitHub release nomi bo'ladi.
3. **Ishning tugashini kuting** (Actions > «Release POS apps»; taxminan 30–60 daqiqa). Android uchun to'rt ilova, Windows uchun to'rt ilova va oxirida «GitHub release» qadami. Qizil bo'lsa: «imzo kaliti hali yo'q» — yuqoridagi 2-qadam; boshqa xato bo'lsa jurnalni o'qing, tuzatib yangi tag qo'ying (eski tagni o'chirmang va ustidan yozmang).
4. **Fayllarni oling va tekshiring.** GitHub release'dan hamma faylni yuklab oling. Har fayl yonida `.sha256` bor: `sha256sum -c fayl.sha256`.
5. **pos.rizqim.uz ga ko'chiring.** Serverda `POS_FILES_DIR` ichiga `<ilova>/<platforma>/` papkalar bo'yicha: `cashier/android/`, `cashier/windows/`, `waiter/...`, `kds/...`, `customer_display/...` (ilova nomlari shu to'rtta, platforma `android` yoki `windows`). Papkada `rizqim-<ilova>-<versiya>+<build>-arm64-v8a.apk` (va `-armeabi-v7a.apk`) yoki `...-windows-x64.zip` bo'ladi. Eski fayllarni o'chirmang: kerak bo'lsa orqaga qaytarish mumkin. Fayl ochilishini tekshiring: `https://pos.rizqim.uz/files/cashier/android/<fayl>`.
6. **Reyestrga qator qo'shing.** Boshqaruv markazi > POS va obunalar > **Versiyalar** > «Versiya qo'shish»: ilova, platforma, versiya, build, fayl manzili (`https://pos.rizqim.uz/files/...`), SHA-256 (`.sha256` fayldan), hajmi baytda (`stat -c %s fayl`), izoh (nima o'zgardi, qisqa). Sabab yozing va 2FA kodini tasdiqlang. Har ilova va platforma uchun alohida qator. Android uchun reyestrga asosiy `arm64-v8a` faylni yozing; 32-bitli eski kassalar (`armeabi-v7a`) uchun fayl o'sha papkada turadi, havolasini qo'lda bering. Qator qo'shilishi bilan sahifada ko'rinadi va kassalarda «yangi versiya bor» xabari chiqadi (kuniga bir marta yopiladigan xabar).
7. **Tekshiring.** https://pos.rizqim.uz da yangi versiya ko'rinadi; Boshqaruv markazi > Qurilmalar da «Eskirgan» soni o'zgarganini ko'ring.

**Minimum qachon qo'yiladi.** Oddiy yangilanishda qo'ymang: kassalar faqat xabar oladi. «Minimal versiya» (qatorni tahrirlab yoki yaratishda yoqing) faqat eski versiya haqiqiy muammo bo'lganda: xavfsizlik yoki pul xatosi, server bilan mos kelmaslik. Minimum qo'yilsa:

- undan eski versiyadagi kassalarda qizil, yopilmaydigan xabar chiqadi (savdo davom etadi, «Davom etish» doim ishlaydi) va yangi kassani ulash 426 `app_outdated` bilan to'xtaydi;
- «Qachondan» maydoni bo'sh bo'lsa 14 kun beriladi; shundan keyin ega Telegram'da xabar oladi (qaysi kassalar eskirgan). Shoshilinch bo'lsa sanani qisqartiring, lekin kassa egalariga avval xabar bering;
- kassa hech qachon to'xtatilmaydi, ya'ni minimum «majburlash» emas, faqat qat'iy eslatma.

**Versiyani qaytarib olish.** Muammoli versiyani reyestrda «Olib tashlash» (sabab bilan): u sahifadan va xabardan chiqadi, avvalgi versiya yana «eng yangi» bo'ladi; o'rnatilgan kassalar ishlashda davom etadi. «O'chirish» faqat xato qo'shilgan qator uchun. Olib tashlangan minimum o'z kuchini yo'qotadi.

**Cheklovlar.** Windows hozircha o'rnatuvchisiz zip: papkani yoyib .exe faylini ishga tushiring (`cashier.exe`, `waiter.exe`, `kds.exe`, `customer_display.exe`), `%APPDATA%` va `Documents\RIZQIM` (kassaning ulanishi va navbati) ga tegmang. Zip eski papka ustiga yoyiladi. O'rnatuvchi (Inno Setup) keyingi qadam.

## 27. rizqim.uz landing (P)

Landing va "Hamkor bo'lish" sahifasi: statik sayt `infra/landing/` (nginx, build qadamisiz), compose xizmati `landing`. Sahifalar: `/`, `/hamkor`, `/maxfiylik`, `/shartlar`, 404.

1. Serverda `.env` ga `LANDING_PORT` (standart 8091), `docker compose up -d --build landing`. Tashqi TLS proksi `rizqim.uz` (va `www`) ni shu portga yo'naltiradi. Tekshiring: `https://rizqim.uz/`, `https://rizqim.uz/hamkor`.
2. Forma `POST /api/v1/store-applications` va `GET /api/v1/locations` ni bir xil origin orqali yuboradi: `landing` ichidagi nginx faqat shu ikki yo'lni `backend` ga uzatadi (boshqa `/api/` yopiq). IP bo'yicha limit (429) ishlashi uchun tashqi proksi `X-Forwarded-For` ni uzatsin.
3. **Egasi beradigan qiymatlar** (telefon, Telegram, manzil, bot havolasi, ijtimoiy tarmoqlar, FAQ javoblari, tariflar) hammasi bitta faylda: `infra/landing/site/site.config.js`. Matnlar: `site/i18n/uz.js` va `ru.js`. FAQ javobi `null` bo'lsa "Tez orada" ko'rinadi; tariflar `null` bo'lsa blok yashirin. Fayl o'zgargach `docker compose up -d --build landing`. Maxfiylik siyosati va foydalanish shartlari yakuniy matn (versiya 1.0, 23.11.2026; huquqiy asoslar: [legal/README.md](legal/README.md)); kompaniya rekvizitlari `site.config.js` dagi `legal.*` dan olinadi, bo'sh bo'lsa "[egasi kiritadi]" ko'rinadi.
4. Tekshiruv: `node infra/landing/tests/check.mjs` (bog'liqliksiz; `--sync` o'zbekcha matnni `uz.js` dan HTML ga yozadi), brauzer testi `node infra/landing/tests/smoke.mjs` (partner/web dagi Playwright'dan foydalanadi), mahalliy ko'rish `node infra/landing/tests/serve.mjs`.
5. Logo: `site/img/logo.svg`, `logo-light.svg`, `logo-mark.svg`, `favicon.svg` vaqtincha (konsept asosida); dizayner SVG'si bilan shu fayllar almashtiriladi.
6. **HSTS va TLS.** `landing` ichidagi nginx oddiy HTTP (root bo'lmagan `nginx-unprivileged`, port 8080) beradi; `Strict-Transport-Security` sarlavhasini tashqi TLS proksi qo'shadi (`rizqim.uz` va `www`), landing konfigida HSTS yo'q. Maxfiylik va shartlar sahifalari indekslanadi va `sitemap.xml` da bor. Aloqa maydonlari (`phone`, `telegramHandle`, `social.*`, `address`) bo'sh yuboriladi: egasi to'ldirmaguncha sahifada ko'rinmaydi.

## 28. SMS: sms.onlinegroup.uz (P)

Kirish kodlari (hamkor paneli, xodim va kassa telefon orqali kirishi, Mini App'da raqamni tasdiqlash) va boshqa SMS'lar `sms_driver` bo'yicha ketadi: `log` (standart — hech narsa yuborilmaydi, kod faqat server jurnalida), `eskiz` yoki `onlinegroup` (egasining gateway'i). Hujjat: https://sms.onlinegroup.uz/docs.

**Yoqish (egasi):**

1. sms.onlinegroup.uz kabinetida chap menyu → **Ilovalar** → yangi ilova (masalan `RIZQIM`), ruxsati **`sms:send`** (`otp:send` kerak emas). Kalit (`sk_...`) faqat bir marta ko'rsatiladi — darhol nusxalang. Webhook manzilini hozircha bermang.
2. Boshqaruv markazi → Tizim → Tizim sozlamalari → Sozlamalar → SMS: **«Onlinegroup API kaliti»** → «Almashtirish», kalitni qo'ying, sabab yozing, 2FA. Javobda va jurnalda faqat oxirgi 4 belgi.
3. Shu yerda **«SMS yuborish usuli»** → «Onlinegroup (haqiqiy SMS)», sabab bilan saqlang. Kalit kiritilmagan bo'lsa 422 `onlinegroup_not_configured`. Ishlab turganda kalitni o'chirib bo'lmaydi (422 `in_use`) — avval «Log» ga qaytaring.
4. Sinov: hamkor panelida telefon bilan kirish → o'z raqamingizga «RIZQIM tasdiqlash kodi: NNNNNN» kelishi kerak. Kabinetdagi «Xabarlar» sahifasida xabar holati (`queued` → `sent` → `delivered`) ko'rinadi. Kelmasa: `error_code` (`BLOCKED_NOT_ALLOWED` — gateway serverida raqam ruxsat ro'yxatida yo'q; `TTL_EXPIRED` — 5 daqiqada bo'sh SIM topilmadi), server jurnalida `Onlinegroup SMS` qatorlari (raqam yashirilgan, kod va kalit yozilmaydi).

**Qanday ishlaydi.** Kodni o'zimiz yaratamiz va tekshiramiz (`PhoneOtpService`: hash, 5 daqiqa, urinish va kunlik limitlar, `otp-send`/`otp-verify` throttle, audit — avvalgidek); gateway'ga faqat `POST /v1/sms` (`X-API-Key`, `kind: transactional`, kod uchun `ttl_seconds: 300`, raqam `+998XXXXXXXXX`). Har yuborishda bitta `Idempotency-Key`: javob kelmasa yoki 5xx bo'lsa shu kalit bilan yana 2 marta uriniladi, gateway ikkinchi SMS yaratmaydi. 401/403/422/429 qayta urinilmaydi. Redirect kuzatilmaydi, timeout 10 s (ulanish 5 s). Kalitni `.env` da `ONLINEGROUP_API_KEY` bilan ham berish mumkin (boshqaruv markazidagisi ustun); manzil `ONLINEGROUP_SMS_BASE_URL` (standart `https://sms.onlinegroup.uz`).

**Maxfiy kalitlar shifrlangan (review 17).** `onlinegroup_api_key` va `eskiz_password` `settings` jadvalida `APP_KEY` bilan shifrlangan holda saqlanadi (`Crypt::encryptString`); keshda (Redis) ham faqat shifrlangan matn turadi, `.env` dagi qiymat keshlanmaydi. Mavjud ochiq qiymatlarni `2026_12_06_100000_encrypt_secret_settings` migratsiyasi shifrlaydi (qayta ishga tushirish xavfsiz: shifrlangani qayta shifrlanmaydi; `migrate:rollback` ochiq matnga qaytaradi). **`APP_KEY` almashtirilsa** eski kalitni `APP_PREVIOUS_KEYS` ga qo'ying, aks holda kalitlar o'qilmaydi («sozlanmagan» deb hisoblanadi va `sms_failures` ogohlantirishi chiqadi) — kalitni boshqaruv markazida qayta kiriting.

**Ogohlantirish `sms_failures` (review 17).** Har yuborilmagan SMS keshda sanaladi (sabab bo'yicha, daqiqalik), Monitoring → Qoidalar → «SMS yuborilmayapti» (standart: 15 daqiqa oynasi, chegara 3) navbatchi guruhiga (yoki superadmin chatiga) yozadi:
- `config` (kritik, 1 tadan): API kalit yo'q, gateway kalitni rad etdi (401/403 — kalit bekor qilingan yoki `sms:send` ruxsati yo'q), manzil https emas, noma'lum `sms_driver`. Bu holatlar Sentry'ga ham yuboriladi (`SmsGatewayException`, kalitsiz). Barcha kirish kodlari to'xtaydi — darhol kalitni tekshiring;
- `disabled` (kritik): `local`/`testing` dan boshqa muhitda (production, staging ham) `sms_driver = log` — hech kimga SMS ketmaydi (staging'da ataylab `log` bo'lsa, u yerda bu qoidani o'chirib qo'ying). Jurnalga faqat yashirilgan raqam yoziladi (kod va matn yo'q); to'liq satr faqat `local`/`testing` da;
- `delivery` (diqqat, chegaradan): gateway limiti (429), 422, javob yo'q / 5xx (3 urinishdan keyin), gateway xabari bo'lmagan javob (muvaffaqiyat faqat 200/201 + `id` + ma'lum holat).
Oyna davomida xato bo'lmasa ogohlantirish o'zi yopiladi.

**Limitlar (review 17).** `otp-send` throttle'ida raqam va foydalanuvchi limitlaridan tashqari butun platforma uchun umumiy chegara: daqiqasiga `SMS_OTP_GLOBAL_PER_MINUTE` (standart 40; gateway kalit bo'yicha daqiqasiga 60 so'rov oladi) va kuniga `SMS_OTP_GLOBAL_PER_DAY` (standart 1000; SIM paketlari oylik). Oshsa 429 «Juda ko'p urinish...». Gateway'ning o'z 429 javobi ham foydalanuvchiga xuddi shunday 429 bo'lib qaytadi (avval 500 edi). Diqqat: umumiy chegarani bir kishi to'ldirib, boshqalarning kirishini bir daqiqaga to'xtatishi mumkin — hujum paytida `delivery`/429 ko'paysa chegarani vaqtincha oshiring yoki manzillarni bloklang.

**Navbat muddati.** Kod `ttl_seconds: 300` bilan ketadi (kod 5 daqiqa yashaydi); boshqa SMS (bron tasdig'i, Telegram havolasi) `ONLINEGROUP_SMS_MESSAGE_TTL` (standart 3600 s) gacha navbatda kutadi.

**Xavfsizlik.** `+` bilan yozilgan raqam faqat `+998...` bo'lsa qabul qilinadi (chet el raqami o'zbek raqamiga aylantirilmaydi). `ONLINEGROUP_SMS_BASE_URL` https bo'lishi shart (oddiy http faqat `local`/`testing` da `localhost`/`127.0.0.1` ga). Sentry filtri «kodi: 123456» kabi o'zbekcha shakllarni va `sk_...` kalitlarni, audit filtri `sk_...` kalitlarni yashiradi.

**Ma'lum cheklov.** Bitta yuborish ichidagi qayta urinishlar bir `Idempotency-Key` bilan ketadi; lekin chaqiruvchining o'zi qayta yuborsa (bron ishi qayta urinsa, foydalanuvchi kodni qayta so'rasa) bu yangi xabar, yangi kalit — gateway olib, javobi yo'qolgan xabar bo'lsa SMS ikki marta kelishi mumkin.

**Narx.** Matnda faqat oddiy `'` (U+0027) va lotin harflari: tipografik apostrof (`ʻ`, `’`) yoki kirill matnni UCS-2 ga o'tkazadi va segmentni 160 dan 70 belgiga tushiradi. Kod matni 30 belgi — bitta segment.

**Hali yo'q.** Yetkazilganlik webhook'i (`X-Signature` HMAC-SHA256) — keyingi qadam; hozir holat faqat kabinetda.

## 29. Payme kaliti dayjesti va to'lovning merchant hisobi (2026_12_09 migratsiyalari)

To'rt migratsiya (security review F5/F6, uchinchi review M1/L1/L3 va to'rtinchi review):

| Migratsiya | Nima qiladi | Qulf va vaqt |
|---|---|---|
| `2026_12_09_100000_add_payme_key_digest_to_stores_table` | `stores.payme_key_digest` — do'konning o'z Payme kassa kalitining HMAC'i (kalitning o'zi emas), indeks orqali Payme so'rovi qaysi do'konniki ekanini topish uchun | ustun: faqat metama'lumot, ACCESS EXCLUSIVE ≤ 0,5 s kutadi; indeks `CONCURRENTLY` (yozish to'xtamaydi); to'ldirish: **har bir** do'kon qatori qayta hisoblanadi (idempotent), to'g'ri bo'lganlari yozilmaydi, qolgani bitta qatorli `UPDATE` (faqat shu do'kon qatoriga qator qulfi, jadval qulfi yo'q), band qatorni ≤ 5 s kutadi. Vaqt: do'konlar soniga chiziqli (har do'konga shifrni ochish + bitta yozuv, millisekundlar); pilotda soniyalar |
| `2026_12_09_100100_scope_payment_transaction_ids_by_merchant_account` | `payments.merchant_scope` (`platform` yoki `store:<id>`), triggerlar, eski to'lovlarni to'ldirish, `(provider, provider_transaction_id, merchant_scope)` unikal indeksi, eski `(provider, provider_transaction_id)` cheklovi olib tashlanadi | ustun va triggerlar: qisqa qulf (≤ min(0,5 s, deadlock_timeout/2)); to'ldirish 20 000 talik bo'laklarda, band qatorlar o'tkazib yuborilib keyin bittadan (≤ 5 s); `VALIDATE` yozishni to'xtatmaydi; indeks `CONCURRENTLY`; eski cheklovni olib tashlash qisqa ACCESS EXCLUSIVE. Vaqt `payments` hajmiga chiziqli, `payments.store_id` migratsiyasiga (DEPLOY.md, 7-bo'lim: ~30 s / 1 mln) yaqin deb kutiladi, alohida o'lchanmagan |
| `2026_12_09_100200_inline_payments_parent_store_lookup` | `payments_store_id()` trigger funksiyasi buyurtma do'konini o'zi o'qiydi (SECURITY DEFINER); chaqirib bo'ladigan `payments_parent_store()` o'chiriladi (L1: har qanday rol istalgan buyurtmaning do'konini o'qiy olardi) | jadval qulfi yo'q |
| `2026_12_09_100300_add_payme_statement_index_to_payments` | to'rtinchi review: qisman indeks `payments_payme_statement_index` (`merchant_scope`, Payme `time`) `WHERE provider = 'payme' AND provider_transaction_id IS NOT NULL` — `GetStatement` endi barcha Payme to'lovlarini o'qimaydi (unikal indeks tartibi o'zgargach uning indeks yo'li qolmagan edi) | `CONCURRENTLY` (yozish to'xtamaydi); vaqt Payme to'lovlari soniga chiziqli |

Hammasi migratsiya paytida ishlaydi (backend so'rovlarga javob bermaydi, kassalar oflayn navbatga yozadi), keshga/Redis'ga tegmaydi, qayta ishga tushirish xavfsiz (to'xtagan joyidan davom etadi).

**Dayjest — faqat indeks yordamchisi.** Payme so'rovi hech qachon dayjest bilan tasdiqlanmaydi: topilgan har do'konning kaliti ochilib, doimiy vaqtda solishtiriladi. Dayjest eskirgan bo'lsa ham to'lov yo'qolmaydi: `CheckPerformTransaction`/`CreateTransaction` buyurtma kaliti bilan tasdiqlanadi va o'sha do'kon so'rovga qo'shiladi; tranzaksiya id'si bo'yicha usullar (`Perform`/`Cancel`/`Check`/`SetFiscalData`) shu id'ni saqlagan do'konlarni (ko'pi bilan 8 ta, indeks orqali) kalit bilan tekshiradi; shunday topilgan do'konning dayjesti o'sha zahoti tuzatiladi (logda «stores.payme_key_digest was stale and has been repaired»). Faqat `GetStatement` (na hisob, na id) eskirgan dayjestli do'kon uchun tuzatilguncha `-32504` oladi — Payme uni keyin qayta so'raydi, pul harakati yo'q. Sozlamasi ochilmaydigan do'kon (APP_KEY almashib, eskisi `APP_PREVIOUS_KEYS` da yo'q) dayjesti NULL qoladi (`none` emas): har so'rovda tekshiriladi va keyingi ishga tushishda qayta urinadi.

**`php artisan payments:payme-digests`.** Hamma do'kon dayjestini kalitdan qayta hisoblaydi (idempotent; to'g'rilariga yozmaydi). Backend entrypoint uni har deploy'da `migrate` dan keyin o'zi ishga tushiradi; qo'lda: `docker compose exec -u www-data backend php artisan payments:payme-digests`. Chiqish kodi 1 va «yozilmadi: N» — N ta do'konning sozlamasini o'qib bo'lmadi yoki qatori band edi: logdagi `store_id` larni tekshiring (kalitni egasi qayta kiritadi yoki `APP_PREVIOUS_KEYS`), so'ng qayta ishga tushiring.

**Koddan orqaga qaytish (faqat kod, baza o'zgarmaydi).** Bu migratsiyalar eski kod bilan ishlaydi: eski kod `merchant_scope` ni bilmaydi — trigger uni o'zi yozadi. Lekin:
- eski release'da `Store` modelida `payme_key_digest` yashirilmagan, shuning uchun do'kon JSON javoblarida (`payme_key_digest`) ko'rinadi. Bu kalitning HMAC'i (qat'iy yorliq bilan), kalitning o'zi emas va undan kalitni tiklab bo'lmaydi; eski kodga tegmasdan uni yashirib bo'lmaydi (ustun huquqini olib qo'ysak yangi kod ham o'qiy olmaydi);
- eski kod tranzaksiya id'sini hisobsiz qidiradi: bitta id ikki hisobda yozilgan bo'lsa birinchisini oladi (javobi «boshqa buyurtmaniki» bo'ladi, ikkinchi to'lov yaratilmaydi);
- eski kod kalit o'zgarganda dayjestni yangilamaydi. **Yangi kod qayta joylangach dayjestlarni qayta hisoblang**: entrypoint buni o'zi qiladi; deploy'siz qayta ishga tushirilgan bo'lsa yuqoridagi buyruqni qo'lda bajaring.

**`down()` ni yangi kod ishlab turganda bajarmang.** `100100` ning `down()` i `payments.merchant_scope` ni o'chiradi — yangi kod har to'lovga uni yozadi, to'lov yozuvlari xato bilan yiqiladi; bitta id ikki hisobda bo'lsa `down()` o'zi rad etadi (qaysi birini o'chirish — pul haqidagi qaror). `100000` ning `down()` i `stores.payme_key_digest` ni o'chiradi — yangi kodda har do'kon saqlash va do'konning o'z Payme kaliti bilan kelgan har so'rov yiqiladi. `100200` ning `down()` i xavfsiz, lekin L1 teshigini (`payments_parent_store()`) qaytaradi. Kerak bo'lsa avval kodni qaytaring, keyin (eski kod ishlayotganda) `migrate:rollback` qiling — DEPLOY.md, 7-bo'lim.

**Platforma kalitini o'z kaliti sifatida saqlagan do'kon.** Bu noto'g'ri sozlama: do'kon mijozlarining puli platforma kassasiga tushadi. Egasining 1-qaroridan beri kod uni saqlash paytida rad etadi (422 `platform_credential`, 30-bo'lim); undan oldin saqlanganlari o'zgartirilmaydi. Platforma kaliti bilan kelgan so'rov hech qachon shu do'konning POS QR'iga yo'naltirilmaydi (L2), va to'rtinchi review'dan beri shu do'kon QR'iga oid har bir Payme so'rovi (`CheckPerformTransaction`, `pos_qr_` hisobli `CreateTransaction` ham) darhol `-32504` oladi: QR hech qachon id ushlab qolmaydi (aks holda uning `Perform`/`Cancel` i QR'ga yetmasdi va QR 12 soat kutib qolardi). Bunday do'kon kassada Payme QR'ini ishlata olmaydi — o'z kalitini kiritishi kerak. Topish uchun: `payments:own-credentials-check` (30-bo'lim; Payme kalitidan tashqari hamma kalitlarni ham ko'radi), yoki platforma kalitining dayjestini oling — `docker compose exec -u www-data backend php artisan tinker --execute='echo App\Services\Payment\PaymeCashbox::digest(App\Models\PaymentProvider::platformCredential("payme", "secret_key"));'` — va `SELECT id, name FROM stores WHERE payme_key_digest = '<dayjest>';`.

**Dasturchi bazalari.** Shu branch'dagi `2026_12_09_100000` / `100100` migratsiyalarining oldingi versiyalarini ishga tushirgan dasturchi bazasini `php artisan migrate:fresh` bilan qayta yarating: o'sha versiyalar `main` ga hech qachon tushmagan va `migrate` ularni qayta bajarmaydi (production'ga tegishli emas).

**Vaqt farqi (qabul qilingan).** Mavjud tranzaksiya id'sini ko'rsatgan noto'g'ri kalitli so'rov noma'lum id'li so'rovdan biroz uzoqroq javob oladi (id'ni saqlagan ko'pi bilan 8 ta do'kon kaliti ochilib solishtiriladi). Bu faqat id mavjudligini taxmin qilishga imkon beradi, hech narsani tasdiqlamaydi; Payme id'lari tasodifiy — qabul qilingan.

## 30. Platforma kalitlari do'kon kaliti sifatida (egasining 1-qarori) va Paylov kabinet qaytarishi (2-qarori)

**Rad etish.** Do'kon o'z hisobi uchun RIZQIM platformasining shu to'lov tizimidagi kalitini (Tizim → To'lov tizimlari'dagi qiymat, bo'lmasa `.env`) saqlay olmaydi: Payme `merchant_id`/`secret_key`, Click `service_id`/`merchant_id`/`secret_key`, Paylov `merchant_id`/`consumer_key`/`consumer_secret`/`password`/`callback_password`, Uzum va Paynet `merchant_id`/`secret_key`. Saqlash 422 `platform_credential` oladi (maydon nomi bilan; qiymat javobda ham, logda ham yo'q). Har rad etish `warning` logiga yoziladi — faqat `store_id` va maydon nomlari.

**Past entropiyali loginlar tekshirilmaydi.** Paylov `username` va `callback_login`, Click `merchant_user_id` platformanikiga teng bo'lsa ham yolg'iz o'zi rad etish sababi emas: aks holda rad etish javobi (qaysi maydon) do'kon egasiga platformaning ochiq bo'lmagan, taxmin qilsa bo'ladigan qiymatini sinab ko'rish imkonini berardi. Ular `payments:own-credentials-check` da ham chiqmaydi.

**Rad etishlar chegarasi.** Bitta do'kon 24 soatda ko'pi bilan 5 marta `platform_credential` oladi; keyin oyna o'tguncha **kalitni o'zgartiradigan** har saqlash 429 `platform_credential_limited` (`data.retry_after` — soniya, `Retry-After` sarlavhasi) oladi, hatto to'g'ri kalit bilan ham. Kalitga tegmaydigan saqlash (switch, yetkazish narxi, bo'sh yoki yulduzchali maxfiy maydon) cheklanmaydi. Hisoblagich keshda (Laravel `RateLimiter`, `platform-credential-refusals:<do'kon id>`; production'da Redis), yangi jadval yo'q. Egasi haqiqatan o'z kalitini kiritolmay qolsa va kutib bo'lmasa: `docker compose exec -u www-data backend php artisan tinker --execute='Illuminate\Support\Facades\RateLimiter::clear("platform-credential-refusals:<id>");'`.

**Platformaning Paylov paroli va webhook paroli uzun tasodifiy bo'lishi shart.** Paylov `password` va `callback_password` (Tizim → To'lov tizimlari yoki `.env`: `PAYLOV_PASSWORD`, `PAYLOV_CALLBACK_PASSWORD`) — kamida 32 belgili tasodifiy qiymat (masalan `openssl rand -base64 32`). Rad etish chegarasi taxminlarni sekinlashtiradi, lekin qisqa yoki so'zli parolni himoya qilmaydi; login (`username`, `callback_login`) maxfiy emas deb hisoblanadi.

**Identifikatorlar kanonik solishtiriladi.** `merchant_id`, `service_id`, `terminal_id`, `consumer_key` ikkala tomonda ham har qanday Unicode bo'sh joy, nol kenglikdagi va boshqaruv belgilaridan tozalanib, katta-kichik harf farqisiz, faqat raqamli bo'lsa boshidagi nollarsiz solishtiriladi (platformaning id'si boshqa registrda, NBSP/ZWSP bilan yoki `0` bilan boshlab yozilsa ham — platformaniki). Maxfiy kalitlar aniq solishtiriladi (faqat boshi/oxiridagi bo'sh joy olib tashlanadi). Bundan tashqari `POST /owner/settings` yangi yuborilgan identifikatorda bo'sh joy, boshqaruv/ko'rinmas belgi yoki `& ; = ? # /` bo'lsa 422 (oddiy validatsiya xatosi, shu maydonda) qaytaradi — ular to'lov havolasiga to'g'ridan-to'g'ri qo'shiladi. Provayderga xos format (hex/UUID) talab qilinmaydi. Avval saqlangan va o'zgarmay qayta yuborilgan qiymat saqlashni to'xtatmaydi.

**O'qib bo'lmaydigan provayder qatori.** `payment_providers.credentials` qatorini ochib bo'lmasa (APP_KEY `APP_PREVIOUS_KEYS` siz almashtirilgan; faol bo'lmagan Uzum qatori ham), tekshiruv uni bo'sh deb oladi va `.env` qiymatlarini ishlatadi, logga `warning` (faqat `provider` slug) yozadi — do'kon saqlashlari 500 bermaydi. Kalitlarni Tizim → To'lov tizimlari'da qayta kiriting. Qoida `Store` modelining `saving` hook'ida (`App\Services\Payment\PlatformCredentialGuard`), shuning uchun hamma yozish yo'llari qamrab olingan: `POST /owner/settings` (hamkor paneli), Boshqaruv markazidagi do'kon yaratish (`StoreProvisioner`, ariza qabul qilish — boshlang'ich sozlamada kalit yo'q), seeder'lar (`PosStackSeeder`, `E2EStackSeeder`), `tinker`/import — Eloquent orqali yozadigan har narsa. Admin do'konning kalitlarini o'zi kiritadigan vosita yo'q (faqat hisob turini o'zgartiradi). Faqat xom SQL (migratsiyalar) chetlab o'tadi.

**Mavjud do'konlar.** Qoidadan oldin saqlangan qiymat o'zgartirilmaydi va boshqa saqlashlarni (egasi boshqa sozlamani o'zgartirsa, ledger yozuvi) to'xtatmaydi — faqat shu qiymatni qayta yuborish rad etiladi. Bunday do'konlarni ko'rish (faqat o'qiydi, hech narsa yozmaydi):

```
docker compose exec -u www-data backend php artisan payments:own-credentials-check
```

Chiqishda soni, har do'kon id'si va maydon nomlari (`payme.secret_key`...), qiymatlar hech qachon chiqmaydi. Chiqish kodi: **0** — hech narsa topilmadi; **1** — kamida bitta do'kon topildi; **2** — xato: biror do'kon sozlamasini yoki biror provayderning platforma kalitlari qatorini (`payment_providers`) o'qib bo'lmadi (`APP_KEY`/`APP_PREVIOUS_KEYS`; chiqishda «Xato: uzum to'lov tizimining platforma kalitlarini o'qib bo'lmadi (provider uzum credentials unreadable…)») — o'qib bo'lmagan provayder `.env` qiymatlari bilan tekshiriladi, topilgan do'konlar baribir ro'yxatda chiqadi. Xato ham, topilgan do'kon ham bo'lsa — 2. Platforma kaliti Tizim → To'lov tizimlari'da almashtirilgach ham ishga tushiring (yangi qiymat biror do'konnikiga teng bo'lib qolishi mumkin). Topilgan do'kon egasi hamkor panelida («Sozlamalar» → to'lov) o'z kabinetidagi kalitni kiritadi; shu paytgacha uning Payme QR'i ishlamaydi (29-bo'lim) va mijozlar puli platforma hisobiga tushadi — moliya jamoasi ularni qo'lda hisob-kitob qiladi.

**Paylov kabinet qaytarishi.** O'z Paylov hisobiga yig'adigan do'konning yetkazilgan buyurtmasi uchun Paylov'dan kelgan va Paylov tasdiqlagan `transaction.cancel` (RIZQIM o'zi `payment/cancel` so'ramagan bo'lsa) endi darhol qaytarilmaydi: Payme'dagidek Bugun → «Diqqat talab qiladi» → «Ovqatxona kassasidan qaytarishlar» ga tushadi, komissiya menejer qaroriga qadar saqlanadi, buyurtma kartasida «Ovqatxona kassasidan qaytarish» bloki (ikki xodim tasdiqlaydi yoki bittasi rad etadi). Tasdiqlashdan oldin pul haqiqatan qaytganini do'konning Paylov biznes panelida emas, RIZQIM Paylov'dan o'qigan holat (`cancel`/`refunded`) allaqachon tasdiqlagan; menejer hal qiladigani — komissiya qaytadimi (admin kartasidagi matn ham shuni aytadi: tasdiqlash — komissiya bekor qilinadi, rad etish — saqlanadi; Payme matni o'zgarmagan). Tekshiruvga webhook aytgan summa (`reported_amount_tiyin`) va Paylov'dan o'qilgan summa (`paylov_amount_tiyin`) yoziladi; ulardan biri to'lov summasidan farq qilsa `amount_mismatch: true` va kartada ogohlantirish: «Qaytarilgan summa to'lov summasidan farq qiladi — tasdiqlashdan oldin tekshiring» (qisman kabinet qaytarishi bo'lishi mumkin). Tasdiqlash avvalgidek to'lovni to'liq qaytgan deb hisoblab komissiyani to'liq bekor qiladi — farq bo'lsa, menejer Paylov bilan aniqlashtirguncha tasdiqlamasin. Bekor qilish birinchi marta buyurtma yetkazilishidan oldin xabar qilingan bo'lsa (Paylov o'shanda javob bermagan, keyin `payments:paylov-reconcile` tasdiqlagan), tekshiruvda `reported_before_delivery: true` va kartada bir qator. Ma'lumotlar to'lovning `payload.store_refund_review` ida — migratsiya yo'q, kesh/Redis'ga tegilmaydi (faqat rad etishlar hisoblagichi keshda).


## 31. Do'st taklifi bonusi faqat tasdiqlangan to'lov uchun (egasining 3-qarori) va ovqatxona o'z hisobidan qaytaradigan pul (4-qarori)

Migratsiya yo'q, kesh/Redis'ga tegilmaydi: hamma narsa mavjud ustunlardan (`orders.payment_owner`, `payment_method`, `payment_status`, `payments.payload`, `store_transactions.type` — oddiy satr) o'qiladi va yoziladi.

### 3-qaror: taklif bonusi

**Qoida (bitta joyda: `App\Services\Auth\ReferralService::qualifiesForReferralBonus()`).** Taklif qilganga 5 000 ball
do'stning **mos** yetkazilgan buyurtmasi uchun beriladi. Buyurtma mos, agar:

1. summasi kamida **50 000** so'm (`ReferralService::MIN_ORDER`);
2. to'lovini **platformaning o'zi tasdiqlagan** (`paymentVerifiedByPlatform`, jadval quyida);
3. **egasining A qoidasi:** buyurtmaning yozilgan komissiyasi (`orders.commission_amount`, checkout'da olingan va
   yetkazishda yechiladigan snapshot) bonusdan (`ReferralService::BONUS` = 5 000) **kam emas** — bonus platforma shu
   buyurtmadan oladigan puldan oshmaydi. 50 000 × 10% = 5 000 — beriladi; 50 000 × 9% = 4 500 — yo'q; 100 000 × 5% —
   beriladi; 0% kanal — hech qachon.

Bundan tashqari, bonus berish paytida (qulflar ostida, `LedgerService::grantReferralReward`):

4. mijozning bonusi hali turmagan bo'lishi kerak (hamma ovqatxonalar bo'yicha, qaytarib olinganlar ayirilgan —
   `LedgerService::referralRewardedNet`; o'chirilgan (soft-delete) buyurtmalar ham hisobga olinadi);
5. **egasining B qoidasi:** taklif qiluvchiga shu Toshkent oyida **berilgan** bonuslar (oylik hisobotdagi chegara bilan —
   `MonthlyRevenueReport::monthBounds`) `ReferralService::MONTHLY_CAP_PER_REFERRER` (**10**) dan kam bo'lishi kerak.
   Bu yalpi chegara (review L1): qaytarib olish faqat o'zi qaytargan bonus berilgan oydagi o'rinni bo'shatadi —
   oktyabrdagi 10 bonusdan 3 tasi noyabrda qaytarib olinsa, noyabr baribir faqat 10 ta beradi (avval −3 dan
   boshlanib 13 ta berardi); oktyabr ichida qaytarilgan oktyabr bonusi esa oktyabrda bitta o'rin bo'shatadi.

**Kim taklif qiluvchi bo'la oladi (`ReferralService::validReferrerId`).** Har qanday mavjud foydalanuvchi, xodimlar ham
(ular do'stlarini taklif qilishi mumkin) — faqat o'zi emas (bir xil telegram id) va **qabul qilinmagan xodim taklifi
qatori emas** (`telegram_id` `temp_` bilan boshlanadi): u hech qayerga kira olmaydi, unga yozilgan bonusni ishlatib
bo'lmaydi. Bunday `ref_<id>` havolasi `referred_by` ni o'rnatmaydi. Eski ma'lumotda shunday qatorni ko'rsatgan do'stlar
qolishi mumkin — u qator birlashtirilganda o'chirilmaydi, nofaol qilinadi (§32 yonidagi keshbek qoidasi, quyida).

**Mos bo'lmagan buyurtma taklifni «ishlatmaydi».** Tasdiqlanmagan to'lov, 50 000 dan kam summa, bonusdan kam komissiya,
oylik chegara to'lgan — bularning hech biri bonus bermaydi va taklif kutishda qoladi: keyingi mos buyurtma (oylik
chegara bo'lsa — keyingi oyda ham) bonus oladi. Taklif faqat **turgan bonus** bilan yopiladi. (Bu tarmoqdan oldin
birinchi yetkazilgan buyurtma 50 000 dan kam bo'lsa ham taklif yopilardi — endi yopilmaydi; deploy oldidan hisob quyida.)

| To'lov | Tasdiqlangan |
|---|---|
| Platformaning o'z merchant hisobiga onlayn (Click, Payme, Paylov, Uzum, Paynet — `payment_owner: PLATFORM`), `paid` | ha |
| Ovqatxonaning o'z Paylov hisobiga, Paylov'dan o'qib tasdiqlangan (`payload.paylov.state: confirmed`, `refused` yo'q) | ha |
| **Keshbek** | **yo'q** (keshbek faqat shu bonusdan keladi: hisobga olinsa zanjir — A → B → C, B C uchun olgan keshbek bilan to'laydi) |
| Naqd | yo'q |
| Ovqatxonaning o'z Click / Payme / Uzum / Paynet hisobiga | yo'q (ovqatxona o'z kalitini biladi va «to'landi» xabarini o'zi yuborishi mumkin) |

**Konstantalar va ularni o'zgartirish.** Hammasi `App\Services\Auth\ReferralService` da (config/.env'da emas, ataylab):
`BONUS = 5000`, `MIN_ORDER = 50000`, `MONTHLY_CAP_PER_REFERRER = 10`; A qoidasi — `commissionCoversBonus()`.
O'zgartirish — shu qiymatni o'zgartirib, testlarni (`tests/Feature/Referral/ReferralRulesTest`,
`ReferralConcurrencyTest`) yangilab deploy qilish; migratsiya kerak emas. `LedgerService::REFERRAL_REWARD` /
`REFERRAL_MIN_ORDER` shulardan olinadi.

**Qachon beriladi.** Yetkazilganda (`SettlementRecoveryService::book`, hisob-kitob bilan bir savepoint'da, qismlar catch-up'idan keyin; xato bo'lsa hisob-kitob
kabi qayta uriniladi), yoki to'lov yetkazilgandan keyin tushsa — `MarkOrderAsPaid` da (Paylov: Paylov'dan o'qib
tasdiqlangan paytda). Kechikkan to'lovda (review 3, review H1) to'lov holati va do'konga hisob-kitob **avval o'z
tranzaksiyasida yoziladi va commit qilinadi**, bonus esa shundan keyin **alohida tranzaksiyada**
(`SettlementRecoveryService::bookAfterPayment` → `book`): bonus xatosi (deadlock, lock timeout) to'lov holatini ham,
hisob-kitobni ham bekor qila olmaydi — buyurtma `settlement_failed_at` bilan belgilanadi, superadminga xabar ketadi va
`RetryFailedSettlementsJob` uni keyinroq bir marta beradi (provayder javob olgan to'lovni qayta yubormaydi, boshqa qayta
urinish yo'q edi). Muvaffaqiyatli `book` avvalgi `settlement_failed_at` ni ham shu savepoint'da tozalaydi (review L5).

**Yo'qolgan bonusni qidirish (egasining C qarori).** Jarayon ikki tranzaksiya orasida to'xtasa (to'lov va hisob-kitob
commit qilingan, bonus tranzaksiyasi boshlanmagan), belgi ham yozilmaydi (`settlement_failed_at` ni faqat ishlab,
yiqilgan yozuv yozadi) — `RetryFailedSettlementsJob` buyurtmani ko'rmaydi, provayder esa qayta yubormaydi. Shuning
uchun `App\Jobs\SweepMissedReferralBonusesJob` **har soatda** (scheduler, navbatsiz; `withoutOverlapping`) shunday
buyurtmalarga **faqat bonusni** beradi (`LedgerService::grantMissedReferralBonus`).

- **Faqat shu bo'shliq (review M1).** Nomzod — to'lovi **yetkazilgandan keyin** tushgan buyurtma (`payments.paid_at`
  `order_status_histories` dagi birinchi `delivered` qatorining `created_at` idan keyin; bunday qatori yo'q eski
  buyurtma nomzod emas) va buyurtmaning o'z bonus yozuvi yo'q. Yetkazilishdan oldin to'langan buyurtmaning bonusi
  yetkazilishda hal qilingan, rad etilgani ham (oylik chegara, mijozning bonusi allaqachon turgan): **rad etilgan buyurtma
  hech narsa olmaydi, taklif KEYINGI buyurtma uchun ochiq qoladi** — qidiruv uni hech qachon bermaydi (masalan,
  31 oktyabrda chegarada rad etilgan 11-buyurtma 1 noyabrdagi birinchi qidiruvda ham, oktyabr bonusi qaytarib olinib joy
  bo'shaganda ham berilmaydi). Kechikkan to'lovning ikkinchi tranzaksiyasi ishlab rad etgan bo'lishi ham mumkin (u hech
  narsa yozmaydi): shuning uchun qaror **to'lov paytidagi holat bo'yicha** ham tekshiriladi — o'sha paytgacha yozilgan
  bonus va qaytarib olishlar bo'yicha mijozning bonusi turmagan bo'lishi va taklif qiluvchining to'lov oyidagi chegarasida
  joy bo'lishi kerak (`referralRewardedNet` / `ReferralService::monthlyBonusesOf`, `$asOf` bilan), hozirgi qoidalar
  ham (hozirgi oy chegarasi, bonus turmagani, komissiya ≥ bonus, to'liq qaytarilmagan). Keyingi qaytarib olish, qayta
  ochilgan taklif yoki yangi oy rad etishni bekor qilmaydi.
- **Faqat bonus (review L1+L2).** Har nomzod alohida tranzaksiyada: avval buyurtma qatori qulflanadi (hamma joydagi
  «buyurtma → ovqatxona → users» tartibi), qulf ostida qayta tekshiriladi — hali yetkazilgan, to'langan, belgisiz,
  komissiyasi va (platforma yig'gan bo'lsa) hisob-kitobi yozilgan; keyin faqat bonus (`grantReferralReward` bilan bir xil
  qulflar: ovqatxona, mijoz va taklif qiluvchi). Komissiya yoki hisob-kitob hech qachon yozilmaydi: yetishmasa — logga
  `warning`, ular `RetryFailedSettlementsJob` yoki moliya tekshirgandan keyin `orders:retry-settlements
  --confirm-unbooked` ishi. Berish yiqilsa (lock timeout, deadlock) — faqat `error` log (`Missed referral bonus sweep of
  order <uuid> could not run…`): `settlement_failed_at` belgisi ham, «hisob-kitob yozilmadi» xabari ham yo'q; keyingi
  soatdagi ishga tushish yana urinadi.
- **Nomzodlar (review L3).** Bitta indeksli so'rov (`payments_paid_at_index`), ko'pi bilan `MAX_PER_RUN` = 200, **eng
  yangisi birinchi**: yetkazilgan, hali to'langan, belgisiz, taklif bilan kelgan mijozning (`users.referred_by`)
  buyurtmasi; to'lovi so'nggi **7 kun** (`WINDOW_DAYS`) ichida, kamida 15 daqiqa oldin (listener o'zi yozishga
  ulgursin) va yetkazilgandan keyin; o'z bonus yozuvi yo'q; mijozning bonusi turmagan (sof), eski ovqatxona to'lagan
  bonusi yo'q. Hech qachon bonus olmaydigan shakllar SQL da chiqarib tashlanadi: 50 000 dan kam, komissiya bonusdan kam,
  naqd va keshbek, ovqatxonaning o'z hisobiga (o'qib tasdiqlangan Paylov to'lovidan boshqa), keshbek ulushi bor platforma
  buyurtmasi, qismlari bilan to'liq qaytarilgan buyurtma (`order_ledger_reversals.is_full`). Qolgan «abadiy rad»
  holatlari (to'lov paytidagi chegara yoki turgan bonus) berishning o'zida hal qilinadi; holat saqlanmaydi (kursor
  yo'q). Chegara: haqiqiy nomzod faqat 7 kun ichida undan **yangiroq** 200 dan ortiq rad etiladigan nomzod bo'lsa
  kechikadi (oyiga 10 bonusli chegara bilan bu bitta taklif qiluvchining 200 dan ortiq kechikkan to'lovli do'stini
  talab qiladi). Partiya ichida bir mijozning buyurtmalari eskisidan, bir ishga tushishda ko'pi bilan bitta bonus.

Bergan har bonusi logda: `Missed referral bonus of order <uuid> booked by the sweep` (`info`) va oxirida jami
(`warning`). Monitoring topilmasi qo'shilmadi. Qidiruv chegaralangan: 7 kundan eski yo'qolgan bonus (masalan, bu
o'zgarishdan oldingisi) o'zi topilmaydi — uni ko'rib chiqib qo'lda hal qilish kerak. `ReferralMissedBonusSweepTest`.

**Kechiktirilgan qismlar va qaytarishlar.** Hisob-kitob qisman turgan paytda (masalan, kechikkan to'lovning hisob-kitobi
yozilgan, komissiya esa hali ham yozilmayapti) tasdiqlangan qisman qaytarish faqat turgan qismni qaytaradi va
`order_ledger_reversals` qatorini yozadi. Avval qayta urinish bunday so'rovni «qaytarilgan» deb o'tkazib yuborardi —
keyin yozilgan komissiyaning qaytarilgan ulushi do'konga qaytmasdi (100 000 li buyurtma, 10 000 komissiya, yarmi
qaytarilgan → do'kondan 5 000 emas, 10 000 olinardi). Endi `book` (`reverseDeferredParts` →
`LedgerService::reverseOutstandingShare`) yetkazilgandan keyin tasdiqlangan har so'rov uchun, tasdiqlangan tartibda,
hisob-kitobning har qismidan (komissiya, hisob-kitob, qarz to'lovi) **qolgan** ulushni qaytaradi: hozir yozilgandan
so'rovning jamlangan ulushi minus buyurtmaning reversal qatorlari allaqachon qaytargani. Qolgan narsa bo'lmasa hech narsa
yozilmaydi (hammasi buyurtma qulfi ostida). O'z qatori bor so'rovning qo'shimcha qatori `order_refund_request_id = null`,
`components.catch_up_for_request = <id>` bilan yoziladi; `is_full` buyurtmada bittadan ko'p bo'lmaydi. So'rovga
`execution.ledger_reversal_caught_up_at` (vaqt) va `ledger_reversal_catch_up_reason` (`retry` — belgili buyurtmaning
qayta urinishi; `late_payment` — kechikkan to'lovning hisob-kitobi; `replay` — provayderning takroriy xabari; `manual` —
belgisiz buyurtmani `orders:retry-settlements --confirm-unbooked` qo'lda yozdi) yoziladi;
eski `ledger_reversal_on_retry: true` endi yozilmaydi (eski so'rovlarda qoladi, uni hech narsa o'qimaydi).
`FinanceDeferredLedgerPartsTest`.

**Qachon ishlaydi (review M1).** Faqat sabab bo'lsa: buyurtmada `settlement_failed_at` belgisi bor edi (yozuv yiqilgan,
o'shandan beri tasdiqlangan qismlar ledger'da hech narsa topmagan bo'lishi mumkin), yoki shu yozuv komissiyani yoki
hisob-kitobni **birinchi marta** yozdi (`LedgerService::bookCommissionAndSettlement` nimani yozganini qaytaradi; kechikkan
to'lovning hisob-kitobi `SettlementRecoveryService::settleLatePayment` da xuddi shunday). To'liq yozilgan buyurtmaning
takroriy `PaymentPaid` i, ikkinchi qayta urinish hech narsani qimirlatmaydi — eski so'rov ham, qo'lda tuzatish ham (moliya
tuzatishida `order_id` yo'q) ikkinchi marta qaytarilmaydi. **Eski so'rovlar qoidasi:** P4-23 (6ce13ffb, 28.10.2026) dan
oldin tasdiqlangan qism ledger'ga tegmagan va bu haqda hech narsa yozmagan; bunday so'rov — `execution` da
`ledger_reversal` kaliti umuman yo'q **va** uni hech bir `order_ledger_reversals` qatori (`order_refund_request_id` yoki
`components.catch_up_for_request`) nomlamaydi — catch-up'da hech qachon qaytarilmaydi (P4-23 dan beri yetkazilgan
buyurtma qismining har tasdig'i bu kalitni yozadi, qiymati null bo'lsa ham). U keyingi qismlarning jamlangan ulushida
hisoblanadi — o'sha qismlar tasdiqlanganda ham shunday hisoblangan (`deliveryShareOf`).

**Referal bonusi va to'liq qaytgan qismlar (review L1).** `book` tartibi: komissiya va hisob-kitob → qismlarning
catch-up'i → referal bonusi. Qismlar jami to'langanga yetgan bo'lsa catch-up komissiyani to'liq qaytaradi va `is_full`
qatorini yozadi; bonus shundan keyin ko'riladi va `is_full` qatori bor buyurtmaga berilmaydi — bonus qatori umuman
yozilmaydi (avval yozuv paytida hech narsa turmagan bo'lsa +5 000 / −5 000 juftligi yozilardi). Qismlar to'liqqa
yetmagan bo'lsa bonus odatdagidek bir marta beriladi.

**Kechikkan to'lovning hisob-kitobi yiqilsa (review H1).** Click, Payme va Paylov to'lov commit qilingandan keyin
`PaymentPaid` ni yuboradi va tinglovchi xatosini faqat log'ga yozadi — javob baribir muvaffaqiyat (go-live review M1),
ya'ni provayder qayta yubormaydi. Avval `settlePlatformOrder` xatosi tinglovchi tranzaksiyasini butunlay qaytarardi:
`payment_status = paid` qolardi, belgi yo'q — hech qanday qayta urinish uni yozmasdi, hech narsa ogohlantirmasdi. Endi
hisob-kitob o'z savepoint'ida (`settleLatePayment`); xato tutiladi va `bookAfterPayment` → `book` uni qayta yozadi, yana
yiqilsa buyurtma `settlement_failed_at` bilan belgilanadi (superadminga xabar, `RetryFailedSettlementsJob` 10 daqiqada).
Belgining o'zi ham yozilmasa `bookAfterPayment` o'z tranzaksiyasida yana urinadi, bo'lmasa **`SettlementNotMarked`
tashlaydi** (critical log «could not be marked for a retry», superadminga «avtomatik qayta urinish bo'lmaydi» xabari —
bir marta, ikkinchi urinish natijasi ma'lum bo'lgandan keyin);
hisob-kitobdan hech narsa commit qilinmagan. Provayder javobi o'zgarmaydi (u log'ga `... PaymentPaid listener failed`
yozadi): Complete/Perform'ga xato javob provayderga to'lovni muvaffaqiyatsiz deb ko'rsatishi mumkin edi, bizda esa u
allaqachon `paid`. Bunday buyurtmani monitoring topadi: `settlement_failures` qoidasining ikkinchi topilmasi (subject
`unmarked`) va «Bugun»dagi «To'langan, lekin do'konga yozilmagan» guruhi (`UnbookedSettlementsSource`) — yetkazilgan,
`payment_owner = PLATFORM`, `payment_status = paid`, belgisiz, settlement / qarz to'lovi qatori yo'q, to'lovi
(`payments.paid_at`) 15 daqiqadan oldin va 30 kundan keyin tushgan buyurtmalar
(`SettlementRecoveryService::unbookedLatePayments`; oyna `payments_paid_at_index` orqali o'qiladi — har monitoring
yurishida barcha yetkazilgan buyurtmalar ko'rib chiqilmaydi; to'lov qatorisiz «to'langan» buyurtma bu yerda chiqmaydi).
30 kundan eskilarini quyidagi (ii) so'rov topadi. Admin paneldagi «qayta urinish» tugmasi bunday buyurtmani **yozmaydi**
(u faqat belgili buyurtmalar uchun) — ataylab: pastdagi tekshiruvsiz bir bosishda ikki marta yozish xavfi bor.

**Belgisiz buyurtmani qo'lda yozish (ikki marta yozmaslik uchun).** Moliya bunday buyurtmaning pulini do'konga
allaqachon qo'lda yozgan bo'lishi mumkin: qo'lda tuzatishda (`finance_adjustments`, uning ledger qatori) `order_id`
yo'q, buyurtma faqat erkin matnda (`reason`, `notes`) tilga olinishi mumkin — shuning uchun monitoring ularni
ajrata olmaydi va buyurtma ro'yxatda qolaveradi. Avval sababini bartaraf eting, so'ng:

1. `php artisan orders:retry-settlements <id>` — **bayroqsiz** belgisiz buyurtmani yozmaydi: buyurtmani (do'kon,
   summa, to'lov vaqti) va buyurtma berilgandan beri do'konning qo'lda tuzatishlarini (Moliya → Qo'lda tuzatish:
   rad etilmaganlari; buyurtmasiz qo'lda `adjustment` qatorlari qarz va hamyon ledger'ida; eski paneldan qo'lda
   top-up'lar) chiqaradi va 1 kodi bilan tugaydi.
2. Ularni va do'konning qarz / hamyon ledger'ini (Moliya → do'kon) shu buyurtma vaqtidan beri tekshiring: shu
   buyurtmaning puli (masalan, `wallet_credit` 90 000 «Click to'lovi yozilmagan») qo'lda yozilgan bo'lsa, buyruqni
   **ishga tushirmang** — buyurtma ro'yxatda qoladi, buni moliya bilan kelishing.
3. Faqat hech biri bu buyurtmani yozmagan bo'lsa: `php artisan orders:retry-settlements <id> --confirm-unbooked` —
   bir marta yozadi (`bookAfterPayment`, catch-up sababi `manual`), qayta ishga tushirilsa hech narsa qilmaydi.

Belgili buyurtmalar (`settlement_failed_at`) avvalgidek bayroqsiz yoziladi. Provayderning takroriy Complete/Perform'i
ham belgisiz buyurtmani yozadi. `FinanceLatePaymentSettlementFailureTest`.

**Deploydan oldin (faqat o'qish).** Shu o'zgarish bilan nimaga tegishini oldindan sanash uchun:

```sql
-- (i) tasdiqlangan, yetkazilgan buyurtma qismi, lekin uni hech bir order_ledger_reversals qatori nomlamaydi
--     (legacy: execution'da ledger_reversal kaliti ham yo'q — catch-up ularni hech qachon qaytarmaydi)
SELECT count(*) AS approved_delivered_without_reversal_row,
       count(*) FILTER (WHERE NOT (r.execution ? 'ledger_reversal')) AS of_them_legacy
FROM order_refund_requests r
WHERE r.status = 'approved'
  AND r.execution->>'order_status_before' = 'delivered'
  AND NOT EXISTS (
      SELECT 1 FROM order_ledger_reversals l
      WHERE l.order_refund_request_id = r.id
         OR l.components->>'catch_up_for_request' = r.id::text);

-- (ii) yetkazilgan, platforma yig'gan, to'langan buyurtma: hisob-kitob qatori ham, belgi ham yo'q (review H1 holati)
SELECT count(*) AS delivered_paid_platform_without_settlement_or_marker
FROM orders o
WHERE o.status = 'delivered'
  AND o.payment_owner = 'PLATFORM'
  AND o.payment_status = 'paid'
  AND o.settlement_failed_at IS NULL
  AND o.total_price > 0
  AND NOT EXISTS (SELECT 1 FROM store_wallet_transactions w WHERE w.order_id = o.id AND w.type = 'settlement')
  AND NOT EXISTS (SELECT 1 FROM store_transactions t WHERE t.order_id = o.id AND t.type = 'topup');
```

(i) dagi legacy bo'lmaganlari — yozuvi yiqilgan paytda tasdiqlangan qismlar (`ledger_reversal_deferred: true`): ular
o'sha buyurtma yozilganda qaytariladi. (ii) dagilar — to'lovi oxirgi 30 kunda tushganlarini deploydan keyin 15 daqiqada
monitoring ko'rsatadi (eskilari faqat shu so'rovda). Ularning puli moliya tomonidan qo'lda yozilgan bo'lishi mumkin
(tuzatishda `order_id` yo'q): har biri uchun **avval** do'konning moliya tuzatishlarini tekshiring —
`php artisan orders:retry-settlements <id>` (bayroqsiz) buyurtmani va do'konning o'sha vaqtdan beri qo'lda
tuzatishlarini chiqaradi, hech narsa yozmaydi; yuqoridagi «Belgisiz buyurtmani qo'lda yozish». Faqat hech biri bu
buyurtmani yozmagan bo'lsa: `php artisan orders:retry-settlements <id> --confirm-unbooked` (u yozilgandan keyin o'sha
orada tasdiqlangan qismlarning ulushini ham qaytaradi, sababi `manual`).

**Deadlock savepoint'da (review H1).** Laravel ichki (nested) tranzaksiyadagi deadlock / serialization xatosida
savepoint'ga qaytmasdan `DeadlockException` tashlardi; PostgreSQL esa butun tashqi tranzaksiyani «aborted» holatda
qoldirardi: belgi (`settlement_failed_at`) yozilmasdi, keyingi «commit» esa hech narsani saqlamay muvaffaqiyat
qaytarardi (yetkazish holati, komissiya, hisob-kitob jimgina yo'qolardi, after-commit hodisalari esa ketardi). Endi
`RevisionPostgresConnection::handleTransactionException` bunday darajani avval o'z savepoint'iga qaytaradi
(`NestedTransactionDeadlockTest`): yetkazishda faqat hisob-kitob savepoint'i (komissiya, hisob-kitob, bonus) bekor
bo'ladi, yetkazish belgi bilan commit qilinadi va qayta urinishda hammasi bir marta yoziladi. Agar tashqi tranzaksiya
baribir aborted bo'lsa (belgi yozilmasa, 25P02), `book` xatoni tashlaydi — tranzaksiya butunlay va ochiq bekor bo'ladi,
hech narsa commit qilinmaydi va hodisa ketmaydi (`ReferralLatePaymentFailureTest`: haqiqiy PostgreSQL 40P01 bilan).

**Bir marta va poyga yo'q.** Qulf tartibi boshqa ledger yo'llari kabi: avval ovqatxona qatori, keyin mijoz va taklif
qiluvchining `users` qatorlari (bitta so'rovda, id tartibida — bir-birini taklif qilgan ikki akkaunt ham deadlock
bermaydi). Shundan keyin bonus turganmi va oylik son o'qiladi — bir vaqtda ikki yetkazish (bir mijozniki) yoki bir
taklif qiluvchining ikki do'sti oxirgi (10-) o'rinni ikki marta ololmaydi (`ReferralConcurrencyTest`, ikkala holat ham
fork qilingan jarayonlarda). Hamma ovqatxonalar bo'yicha o'qish RLS'dan login roli bilan o'tadi (`TenantRls::asApp`,
bitta chaqiriq: faqat ha/yo'q va son qaytadi). `RefundRequestService::approve` ham endi ovqatxona qatorini mijoz
balansidan (keshbek ulushi) oldin qulflaydi — avval teskari edi va bonus bilan deadlock berishi mumkin edi.

**Ovqatxona qatori `FOR NO KEY UPDATE` bilan qulflanadi (review H1).** Keshbek bilan checkout (`CreateOrderAction`)
avval mijozning `users` qatorini `FOR UPDATE` qulflaydi, keyin buyurtmani qo'shadi — uning foreign key tekshiruvi
ovqatxona qatoriga `FOR KEY SHARE` oladi. Bonus/qaytarib olish esa avval ovqatxonani, keyin `users` ni qulflaydi:
ovqatxona `FOR UPDATE` bo'lganda ikkalasi bir-birini kutib deadlock (40P01) berardi. Endi hamma pul yo'llari
(`LedgerService` ning barcha ovqatxona qulflari, `RefundRequestService::approve`, `AdjustmentService`,
`PayoutService::complete`) ovqatxona qatorini `Store::ledgerLock()` — `FOR NO KEY UPDATE` bilan oladi: u o'zi bilan va
`FOR UPDATE` bilan to'qnashadi (ledger yo'llari bir-birini avvalgidek kutadi, ovqatxonani o'chirish ham kutadi), lekin
foreign key tekshiruvini kutdirmaydi. Bu yo'llar ovqatxonaning faqat kalit bo'lmagan ustunlarini (`debt_balance`,
`wallet_balance`, holat...) yozadi; yangi buyurtma qo'shilishini to'xtatib turishga hech bir yo'l tayanmaydi.
Tekshiruv: `ReferralConcurrencyTest` (ikki ulanish: keshbek checkout va bir vaqtdagi yetkazish bonusi).

**To'liq qaytarilgan buyurtma: bonus qaytarib olinadi (review 1).** Bonus bergan buyurtma to'liq qaytarilsa — qaysi yo'l
bilan bo'lmasin (butun buyurtma uchun qaytarish so'rovi, tasdiqlangan ovqatxona kassasi qaytarishi, provayder tasdiqlagan
qaytarish, naqd/platforma buyurtmasini bitta xodim qaytarishi, yoki tasdiqlangan qismlar to'langan hammasiga yetsa) —
`LedgerService::reverseDelivery` ning o'sha tranzaksiyasida `clawBackReferralReward`: taklif qiluvchi balansidan −5 000
va qarama-qarshi `referral_bonus` yozuvi (`amount` −5 000, ovqatxona balansiga ta'sirsiz, izohi `Referral bonus taken
back for referred user #<id>: order #<kod> refunded`). Bir marta: buyurtmaning bonus yozuvlari yig'indisi musbat
bo'lgandagina, grant bilan bir xil qulflar ostida. Oylik hisobot uni qaytarish bo'lgan oyda ayiradi (`referral_bonuses`
yig'indi, `referral_count` sof son). Shundan so'ng taklif **yana ochiq**: keyingi haqiqiy mos buyurtma bonusni bir marta
oladi; qaytarib olingan buyurtmaning o'zi esa (yoki to'lovi endi `paid` emas) hech qachon olmaydi. Qisman qaytarish
bonusni qaytarib olmaydi. Eski, ovqatxona to'lagan bonus (`commission` + izoh) qaytarib olinmaydi (RIZQIM uni
to'lamagan) va taklifni yopiq saqlaydi. Raqamlar (`ReferralClawbackTest`): 50 000 / komissiya 5 000 / bonus 5 000 →
to'liq qaytarish → taklif qiluvchi 0, bitta +5 000 va bitta −5 000 yozuv, hisobot sof 0; qayta ishlash hech narsani
o'zgartirmaydi; keyingi mos buyurtma yana bir marta beradi; «buyurtma → bonus → qaytarish» aylanishi taklif qiluvchini
hech qachon 0 dan yuqorida qoldirmaydi.

**Taklif qiluvchi balansi manfiy bo'lishi mumkin (tanlov va sababi).** Bonusni ishlatib bo'lgan taklif qiluvchidan
qaytarib olinsa, `users.balance` manfiy bo'ladi (masalan −5 000). Bu xavfsiz: ustunda CHECK cheklovi yo'q; keshbek bilan
to'lash balans ≥ buyurtma summasini talab qiladi (`CreateOrderAction`: qulf ostida tekshiradi va
`where balance >= total` bilan yechadi) — manfiy balans hech narsaga to'lamaydi; akkaunt birlashtirish balans 0 bo'lmasa
rad etiladi. Qarz keyingi bonuslardan yopiladi (+5 000 avval qarzni qoplaydi). Mini App va boshqaruv markazi manfiy
keshbekni «qarz» sifatida ko'rsatadi. Shuning uchun «bor bo'lganini yechish va kamomadni yozish» varianti tanlanmadi —
u taklifni yopiq qoldirishni talab qilardi.

**Admin.** Mijoz kartasi (`GET /admin/users/{id}`, «Profil»): `profile.referral_bonus` — `{status: granted|pending,
reason, label, order, unverified_deliveries}`. `pending` sabablari (`reason`): `booking_pending` (mos buyurtmaning
hisob-kitobi qayta urinishda), `referrer_cap` («taklif qiluvchining oylik chegarasi to'lgan»), `taken_back` (bonus
qaytarib olingan, keyingi mos buyurtmada beriladi), `not_granted` (mos buyurtma taklif ochiq bo'lmagan paytda
yetkazilgan), `commission_below_bonus` («buyurtma komissiyasi bonusdan kam»), `below_minimum`, `no_qualifying_order`.
Taklif qiluvchining o'z kartasida `profile.referral_month` — `{count, cap, label: "Bu oy: N/10"}`. Keshbek tarixida
bonus «referral» qatori (plus), qaytarib olingani ham (minus, «… qaytarib olindi»).

**Naqdni ham qo'shish (egasining tanlovi).** `ReferralService::paymentVerifiedByPlatform()` ning birinchi qatoriga:

```php
if ($order->payment_method === 'cash') { return true; }
```

Shu bitta qator bonusni ham, admin kartasini ham naqd buyurtmalarga kengaytiradi (A va B qoidalari baribir amal qiladi).
Testlar (`ReferralVerifiedPaymentTest`) naqd uchun `false` kutadi — ularni ham o'zgartiring. Ovqatxona o'z Click/Payme
hisobini qo'shish ham shu joyda, lekin u soxta «to'landi» bilan bonus olish yo'lini ochadi.

**Ochiq qolgan xavflar.** O'zini o'zi taklif qilish ikkinchi Telegram akkaunti bilan (`referrerIdFromParam` faqat bir
xil telegram id'ni rad etadi) — endi oylik chegara bilan cheklangan: bitta taklif qiluvchiga oyiga ko'pi bilan
10 × 5 000 = **50 000 so'm**. Qurilma yoki telefon tekshiruvi yo'q.

### Deploy oldidan (faqat o'qiydigan SQL, mahalliy bazada sinab ko'rilgan)

(a) Yangi qoida bilan taklifi **qayta ochiladigan** mijozlar: taklif bilan kelgan, yetkazilgan (yoki qaytarilgan)
buyurtmasi bor, lekin hech qanday bonus yozuvi yo'q (eski kodda birinchi yetkazish taklifni yopardi). Maksimal
majburiyat = son × 5 000 (oylik chegara bilan bundan ham kam):

```sql
SELECT count(*) AS reopened, count(*) * 5000 AS max_liability_som
FROM users u
WHERE u.referred_by IS NOT NULL
  AND EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.id AND o.status IN ('delivered', 'refunded'))
  AND NOT EXISTS (
    SELECT 1 FROM store_transactions st JOIN orders o2 ON o2.id = st.order_id
    WHERE o2.user_id = u.id
      AND (st.type = 'referral_bonus'
        OR (st.type = 'commission' AND st.notes = 'Referral cashback reward cost for referred user #' || u.id)));
```

(b) Bonussiz taklif qilingan mijozlarning yetkazilgan, lekin hali to'lanmagan onlayn buyurtmalari (to'lov tushsa
`MarkOrderAsPaid` bonus berishi mumkin; naqd va keshbek chiqarib tashlangan — ular hech qachon bonus bermaydi):

```sql
SELECT o.payment_method, o.payment_owner, count(*) AS orders, count(DISTINCT o.user_id) AS customers
FROM orders o JOIN users u ON u.id = o.user_id
WHERE u.referred_by IS NOT NULL
  AND o.status = 'delivered' AND o.payment_status <> 'paid' AND o.payment_method NOT IN ('cash', 'keshbek')
  AND o.deleted_at IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM store_transactions st JOIN orders o2 ON o2.id = st.order_id
    WHERE o2.user_id = u.id
      AND (st.type = 'referral_bonus'
        OR (st.type = 'commission' AND st.notes = 'Referral cashback reward cost for referred user #' || u.id)))
GROUP BY o.payment_method, o.payment_owner ORDER BY orders DESC;
```

(c) 2026-10-18 dan oldin yaratilgan, `STORE` deb belgilangan yetkazilgan eski Click / Payme buyurtmalari (ularning
qaytarishi endi ikki kishilik tekshiruvga tushadi va `store_own_account` belgisini oladi; qayta belgilash — egasining
qarori, kod buni qilmaydi):

```sql
SELECT payment_method, count(*) AS orders, min(created_at) AS first, max(created_at) AS last
FROM orders
WHERE payment_owner = 'STORE' AND payment_method IN ('click', 'payme') AND status = 'delivered'
  AND created_at < '2026-10-18' AND deleted_at IS NULL
GROUP BY payment_method;
```

### Bonusni platforma to'laydi (3-qarorga qo'shimcha)

**Nima o'zgardi.** 5 000 ballni RIZQIM o'z komissiya daromadidan to'laydi; ovqatxona qarzi va hamyoni bonusdan o'zgarmaydi. Har bonus — bonusni olib kelgan buyurtma ovqatxonasi ledger'ida (`store_transactions`) **bitta** `type: referral_bonus` yozuvi: `amount` — platforma to'lagan summa (5 000), `balance_before` = `balance_after` = o'sha paytdagi qarz (ta'siri yo'q), izohi `Referral bonus paid by the platform for referred user #<id>`, `order_id` — buyurtma. Shu yozuv bir martalik belgisi ham (avvalgi `commission` yozuvi o'rniga, xuddi shu qulflar ostida); to'liq qaytarishda
unga qarama-qarshi −5 000 yozuv qo'shiladi (yuqorida). Migratsiya yo'q: `type` oddiy satr ustuni, yangi qiymat `StoreTransactionType::REFERRAL_BONUS`.

Raqamlar (`ReferralVerifiedPaymentTest`): 50 000 so'mlik buyurtma, komissiya 5 000 — birinchi tasdiqlangan yetkazishdan keyin ovqatxona qarzi **5 000** (faqat komissiya, 10 000 emas), taklif qilgan balansi +5 000, platforma xarajati 5 000 bir marta yozilgan; qayta urinish, ikkinchi hisob-kitob va keyingi yetkazish hech narsani o'zgartirmaydi.

**Kim nimani ko'radi.**
- Ovqatxona: `GET /owner/finance` (`ledger_transactions`), `GET /owner/finance/ledger` (hamkor paneli, `type=referral_bonus` filtri — 422) — `referral_bonus` yozuvlari chiqmaydi. Boshqaruv markazida ovqatxona kartasi (oxirgi 10 yozuv) va Moliya → ovqatxona ledger'i (`/admin/finance/stores/{id}/ledger`) ham ularni ko'rsatmaydi (ovqatxona hisobi emas).
- Platforma: Moliya → Oylik hisobot (`GET /admin/finance/reports/monthly`, Excel ham): `totals.referral_bonuses` (oyda yozilgan `referral_bonus` summasi — qaytarib olinganlar ayirilgan), `referral_count` (sof son: bonus +1, qaytarib olish −1), `net` = `total` − `referral_bonuses`. `total` (komissiya + obuna) o'zgarmagan.
- Platforma: Moliya → Oylik hisobot → "Keshbek" (`GET /admin/finance/reports/keshbek`, Excel ham; API.md "Keshbek hisoboti"): oyda berilgan (`issued`, faqat `referral_bonus` +), qaytarib olingan (`clawed_back`), eski ovqatxona to'lagan bonuslar alohida (`legacy_store_paid`), ishlatilgan va qaytgan keshbek, hozirgi majburiyat va harakatlar bilan `SUM(users.balance)` farqi (`reconciliation.difference`; 0 bo'lmasa — yozuvsiz balans o'zgarishi, tekshirish kerak; yonida o'qib bo'lmagan to'lov payload qiymatlari soni — `unrecorded.invalid_payload_values`). Keshbegi bor (balans 0 emas yoki taklif qilgan do'sti bor) `users` qatori o'chirilmaydi (`User::holdsKeshbek`): xodim ovqatxonadan chiqariladi; birlashtirilgan taklif qatori esa avval balansini va do'stlarini uni qabul qilgan hisobga o'tkazadi, keyin o'chiriladi (pastda, «Keshbek yozuvlari», A). Ovqatxonadan chiqarishda `User::retireFromStore`: mijoz roli; ovqatxona, filial, xodim kodi, taklif tokeni, PIN yo'q; barcha sessiyalar (Sanctum, refresh, push tokenlar) bekor qilinadi — qolgan qurilma tokeni bilan mijoz sifatida kirib bo'lmaydi; balans saqlanadi. Tekshiruv va o'chirish/nofaol qilish bitta tranzaksiyada, `users` qatori(lar)i avval qulflanadi (`lockForUpdate`, id tartibida); ovqatxona qatori qulflanmaydi, shuning uchun bonus berishning «ovqatxona → users» tartibi buzilmaydi.
- Komissiya hisoblari (`CommissionStats`, 50% chegirma, qaytarishdagi komissiya qaytarish `LedgerService::reverseDelivery`, `hasCommission`) faqat `commission` turini o'qiydi — `referral_bonus` ularga kirmaydi; eski izohli `commission` yozuvlari avvalgidek chiqarib tashlanadi.
- Admin mijoz kartasi (`referral_bonus`), keshbek tarixi va «bir marta» tekshiruvi ikkala shaklni o'qiydi (`LedgerService::referralRewardedNet`, `whereReferralRewardOf` / `whereAnyReferralReward`).

**Eski yozuvlar.** O'zgartirilmaydi: ovqatxonadan haqiqatan olingan. Ular — `type = 'commission'` va izohi `Referral cashback reward cost for referred user #…`; har taklif qilingan mijozga ko'pi bilan bitta (lekin 3-qarordan oldingi kodda ham har mijozga bir marta edi). Soni — taklif bilan kelib birinchi yetkazilgan buyurtmasi 50 000 dan katta bo'lgan mijozlar soni, ko'pi bilan `SELECT count(*) FROM users WHERE referred_by IS NOT NULL`. Topish (faqat o'qiydi):

```sql
SELECT store_id, count(*) AS rows, sum(amount) AS charged
FROM store_transactions
WHERE type = 'commission' AND notes LIKE 'Referral cashback reward cost%'
GROUP BY store_id ORDER BY charged DESC;
```

Ularni ovqatxonaga qaytarish (masalan Moliya → tuzatish, ikki kishilik) — egasining qarori; kod buni o'zi qilmaydi.

**Platforma buyurtmada zarar ko'rmaydi (A qoidasi).** Komissiya = buyurtma summasi × stavka (`orders.commission_amount`, buyurtma paytida `CommissionPolicy::rateFor` bilan olingan: tarif rejasidagi kanal stavkasi — kuryer, o'z yetkazishi, olib ketish — yoki ovqatxonaning o'z `commission_rate` i; docs/BILLING.md «Komissiya»). Bonus faqat komissiya ≥ 5 000 bo'lsa beriladi: S summali buyurtmada stavka ≥ 5 000 / S (50 000 da ≥ 10%, 100 000 da ≥ 5%, 200 000 da ≥ 2,5%); 0% kanal hech qachon bermaydi. Bunday buyurtma taklifni ishlatmaydi.

### 4-qaror: ovqatxona o'z hisobidan qaytaradigan pul

**«Ovqatxona hisobidagi» buyurtma** (`StoreOwnRefund::isStoreOwned`): `payment_owner: STORE` va to'lov usuli naqd emas — pul ovqatxonaning o'z Click/Payme/Uzum/Paynet/Paylov hisobiga tushgan. Naqd buyurtmalar (POS buyurtmalari ham) bunga kirmaydi va o'zgarmagan.

**Belgi.** RIZQIM pulni o'zi qaytara olmasa (Click/Payme/Uzum/Paynet — qaytarish faqat ovqatxona kabinetida; Paylov — RIZQIM'ning `payment/cancel` i butunlay bajarilmagan, `manual_refund_required`) yoki ovqatxona o'z kabinetida qaytargan bo'lsa (`store_refund_review`), qaytarish `refund_by: store_own_account` oladi, matni **«Pulni ovqatxona o'z hisobidan qaytaradi»**:

| Qayerda | Maydon |
|---|---|
| Admin buyurtma kartasi | `refund_by`, `payments[].refund_by`, tarix izohida matn |
| Qaytarish so'rovi (admin) | `refund_by`, `execution.refund_by`, tasdiq xabari |
| Admin bekor qilish javobi | «Buyurtma bekor qilindi. Pulni ovqatxona o'z hisobidan qaytaradi: ovqatxonaga xabar bering.» |
| Hamkor paneli buyurtma tafsiloti | `GET /staff/orders/{uuid}` → `refund_by` (uz/ru/en matn) |
| Mijoz (Mini App) | `GET /orders` → `refund {refund_by, returned}`; `returned: false` bo'lsa «Pulni ovqatxona o'z hisobidan qaytaradi» |
| Mijozga Telegram / push | bekor qilish va qaytarish xabarida shu qator; push sarlavhasi «Pulni ovqatxona qaytaradi» (pul qaytmaguncha «qaytarildi» yo'q) |

**Qaytarildi (review 5).** Har bir shunday to'lov qaytgan bo'lsa — provayderning bekor qilishi kelgan (`refunded` /
`cancelled`: Payme, Paylov) yoki xodim qo'lda belgilagan (quyida) — qaytarish **tugagan**: admin kartasida
`refund_returned: true` (`payments[].refund_returned`), hamkor paneli tafsilotida `refund_returned: true` va matn
**«Pulni ovqatxona o'z hisobidan qaytardi»** (qaytarish ko'rsatmasi emas: qaytarib bo'lgan ovqatxonaga yana «qaytaring»
deyilmaydi — ikki marta qaytarish xavfi), Mini App'da `refund.returned: true` («Qaytarildi»).

**«Ovqatxona pulni qaytardi» (bitta xodim).** Click, Uzum, Paynet (va `manual_refund_required` dan keyingi Paylov)
qaytarish tasdig'ini yubormaydi — mijoz abadiy «Qaytarilmoqda» ko'rardi. `POST /admin/orders/{uuid}/store-own-refund/returned`
(`orders.refund_approve`, step-up, `reason`, Idempotency-Key; jurnal `admin.orders.store_own_refund.returned`: kim,
qachon, qaysi to'lovlar): ovqatxona pulni qaytarganini (chek, kabinet) tekshirgan xodim belgilaydi. To'lovga
`payload.refunded_manually_by` / `_at` / `_covers` yoziladi (to'lovning `status` i provayder aytganicha qoladi — bu
provayder tasdig'i emas), mijozda «qaytarildi», kartadagi ko'rsatmalar tozalanadi. Ledger va komissiyaga **tegmaydi**
(ularni ikki kishilik oqim hal qilgan). Qayta bosish hech narsani o'zgartirmaydi (200). Rad etiladi: platforma yig'gan
pul (422 `not_store_own_refund`) va qaytarishi hal qilinmagan buyurtma (422 `no_approved_refund`: tasdiqlangan so'rov
ulushi yoki bekor qilingan/qaytarilgan buyurtmadagi `manual_refund_required` yo'q). Ovqatxona tomonida bunday tugma
**yo'q** (ovqatxona mijozga «qaytardim» deya olmasin). Admin buyurtma kartasida «Ovqatxona pulni qaytardi» tugmasi
(`actions.can_mark_store_refunded`).

**Komissiya faqat ikki kishilik tekshiruvdan keyin qaytadi** (yetkazilgan buyurtma, 50 000 / komissiya 5 000 misolida — testlar `StoreOwnAccountRefundTest`):

| Yo'l | Bugun | Natija |
|---|---|---|
| `OrderStatusService::refund` bitta xodim (superadmin, tinker) | ilgari komissiyani darhol qaytarardi | 422 `two_person_check`, qarz 5 000 qoladi |
| Admin «holatni tuzatish» → `refunded` | 422 `use_refund_request` | o'zgarmagan |
| Admin «bekor qilish» yetkazilgan buyurtmada | 422 (holat bekor qilinmaydi) | o'zgarmagan |
| Ovqatxona/xodim (`/staff/orders/{uuid}/status`, `/orders/{uuid}/status`, Telegram tugmalari) | `refunded` qabul qilinmaydi, OrderPolicy rad etadi | o'zgarmagan |
| Qaytarish so'rovi, to'liq | ikki kishi | birinchi xodim so'raydi — qarz 5 000; o'zi tasdiqlay olmaydi (403 `four_eyes`); boshqa xodim tasdiqlaydi — 0, bitta reversal; takror tasdiq hech narsa qilmaydi |
| Qaytarish so'rovi, qisman (20 000) | ikki kishi | tasdiqdan keyin 3 000 (5 000 × 20/50 qaytdi, `deliveryShareOf`), qolgan 30 000 — 0; har so'rov bir marta |
| Ovqatxona kassasidan qaytarish (Payme `CancelTransaction`, Paylov tasdiqlangan `transaction.cancel`) | taklif + boshqa xodim | taklifdan keyin 5 000, tasdiqdan keyin 0; rad etish — 5 000 qoladi |
| Paylov: RIZQIM so'ragan qaytarishning tasdig'i (`payment/cancel` javobi yoki webhook) | so'rov tasdiqlangandan keyin keladi | butun buyurtma uchun so'rov: buyurtma allaqachon `refunded`, yangi qaytarish yo'q. Buyurtmaning **bir qismi** uchun tasdiqlangan so'rov (`payload.paylov.refund.reason: refund_request_<id>`, `execution.mode` ≠ `order_refunded`; masalan Paylov 30 000 + Payme 20 000): faqat to'lov `refunded` bo'ladi, buyurtma `delivered` qoladi, komissiya o'zgarmaydi (o'z ulushi tasdiqda qaytgan) — review 4, `PaylovPayments::applyCancel` |
| `SettlementRecoveryService` qayta urinishi | tasdiqlangan qismlarni qaytaradi | o'zgarmagan (har biri ikki kishi tasdiqlagan) |
| Moliya tuzatishlari (`/admin/finance/adjustments`) | o'z ikki kishilik tasdig'i bor | o'zgarmagan, buyurtma komissiyasi emas |

`OrderStatusService` nomlangan tekshiruvni (`refund_request:<id>`, `store_refund_review`) qulf ostida tekshiradi: so'rov shu buyurtmaniki va kutilmoqda, so'ragan/taklif qilgan — boshqa odam. So'ragan xodimning akkaunti o'chirilgan bo'lsa (`requested_by` NULL) bu «boshqa odam» emas: 422 `requester_missing` — «… So'rovni rad eting va yangi qaytarish so'rovini yarating» (to'liq va qisman so'rov ham). Tizim (aktyorsiz) faqat provayder tasdiqlagan qaytarishni (`refunded_by_provider`) o'tkazadi: Paylov'da RIZQIM o'zi so'ragan `payment/cancel` (u esa tasdiqlangan qaytarish so'rovidan keladi); ovqatxonaning o'z kabinetidagi qaytarishlar avvalgidek tekshiruvga tushadi.

**Yetkazilmagan buyurtma.** Ovqatxona hisobiga to'langan buyurtma bekor qilinsa komissiya yozilmagan bo'ladi (yetkazishda yoziladi) — faqat belgi qo'yiladi; Paylov'ni RIZQIM o'zi qaytaradi (belgi faqat u bajarilmasa).

**O'zgarmaganlar.** Platforma yig'gan buyurtmalar (`payment_owner: PLATFORM`) qaytarishi, naqd buyurtma qaytarishi, Payme/Paylov kabinet qaytarishini ushlab turish (29–30-bo'limlar).

### Keshbek yozuvlari (egasining A, B, C qarorlari)

**A: taklif qatori birlashtirilganda balans ko'chiriladi.** Qabul qilinmagan xodim taklifi qatori (`temp_…`) uni qabul
qilgan mavjud hisobga birlashtirilganda (bot `/start invite_…` yoki Mini App `start_param`, `User::mergeInviteInto`)
qatorda keshbek bo'lsa, u endi muzlab qolmaydi: balans (qarz — manfiy — ham) hisobga o'tkaziladi, qator 0 bo'ladi va
`keshbek_transfers` jadvaliga yozuv qo'shiladi (`from_user_id` — taklif qatori, `to_user_id` — hisob, `amount`,
`reason: invite_merge`, `moved_friend_ids`). Qatorni taklif qiluvchi deb ko'rsatgan do'stlar endi hisobni ko'rsatadi
(kelgusi bonuslar kira oladigan qatorga tushadi). Istisnolar: do'stning o'zi shu hisob bo'lsa — `referred_by` NULL
(o'zini o'zi taklif qilish yo'q); review L5: hisobni o'zi taklif qilgan do'st F bo'lsa (hisobning `referred_by` = F) —
F ning `referred_by` i ham NULL (ikki kishilik aylana yo'q), hisob F ni saqlaydi.

**Hisobning o'z buyurtmasi uchun bonus (review M2).** Hisob A ning o'zini taklif qatori P taklif qilgan bo'lsa va A ning
o'z buyurtmasi P ga hali turgan bonus bergan bo'lsa, birlashtirishda avval shu bonus odatdagi qaytarib olish bilan
qaytariladi (`LedgerService::clawBackReferralReward`: P dan −5 000, o'sha buyurtmada −5 000 lik `referral_bonus` yozuvi,
izoh `Referral bonus taken back for referred user #A: order #…, its referrer's invite row merged into this customer`) —
keshbek hisoboti (`clawed_back`), oylik hisobot va oylik chegara uni boshqa qaytarib olish kabi sanaydi —, so'ng qolgan
balans ko'chiriladi. Aks holda A o'z buyurtmasi uchun bonusni olib qolardi va `referred_by` tozalangani uchun keyingi
to'liq qaytarish uni hech qachon qaytarib ololmasdi. Raqamlar (`InviteMergeKeshbekTransferTest`): A ni P taklif qilgan;
A ning 60 000 lik buyurtmasi P ga 5 000 berdi → birlashtirish: P ning 0 qoldig'i (ko'chadigan narsa yo'q, o'tkazma
yozuvi yo'q), A balansi **0** (5 000 emas), bitta +5 000 va bitta −5 000 yozuv, hisobot `difference` 0; keyin buyurtma
to'liq qaytarilsa hech narsa o'zgarmaydi, ikkinchi marta qaytarib olinmaydi. P ning boshqa do'stlari bergan bonuslar
avvalgidek A ga ko'chadi.

**Qator o'chiriladimi.** Shundan keyin qator, tarixi bo'lmasa, o'chiriladi. Tarixi bo'lsa (`User::hasHistory` — xodimni
o'chirishdagi bir xil tekshiruv: istalgan ovqatxonadagi o'z buyurtmasi — `orders.user_id` ON DELETE CASCADE, buyurtmalar
u bilan o'chib ketardi —, shikoyat, baho, audit, yetkazish, ish sessiyasi, choy puli, kassa yozuvlari yoki hali keshbek)
— o'chirilmaydi, `retireFromStore` bilan nofaol qilinadi (mijoz roli, ovqatxona, taklif tokeni, PIN, sessiyalar yo'q).

Hammasi bitta tranzaksiyada. Qulflar tartibi: avval (M2 holatida) A ning bonusli buyurtmalari ovqatxonalari
(`Store::ledgerLock`, id tartibida), so'ng o'zgaradigan har `users` qatori (ikkala qator va do'stlar) bitta so'rovda id
tartibida — bonus berish va qaytarib olishdagi «ovqatxona → mijoz va taklif qiluvchi» tartibi. Qulflar ostida hammasi
qayta o'qiladi. Do'stining bonusi aynan shu paytda berilayotgan bo'lsa, u qulfni kutadi, so'ng taklif qiluvchi
o'zgarganini ko'rib bermaydi — bonusni do'stning keyingi buyurtmasi yangi taklif qiluvchiga beradi (yetkazilishdan oldin
to'langan buyurtmani C qidiruvi bermaydi). Do'stning keyin qaytarilgan buyurtmasi bonusi yangi hisobdan qaytarib olinadi
(pul ham o'sha yerda). Keshbek hisobotida `transferred` («Taklif hisobidan ko'chirilgan»): bir qatordan chiqib boshqasiga
kiradi, platforma bo'yicha 0 — `movement` ga kirmaydi, `difference` 0 qoladi. Mijoz kartasida «Taklif hisobidan
ko'chirildi» (`kind: transfer`); ko'chgan do'stlarning o'tkazmadan oldingi bonuslari taklif qatoriga tushgan va o'tkazma
ichida — qayta ko'rsatilmaydi, ro'yxat balansga mos keladi. `InviteMergeKeshbekTransferTest`,
`InvitePlaceholderKeshbekTest`.

**Migratsiya `2026_12_12_100000_create_keshbek_transfers_table`** (nega kerak: keshbek ledger'i yo'q;
`store_transactions` yozuvi ovqatxona va buyurtmaga bog'liq, o'tkazma esa ikkalasiga ham emas; `audit_logs` oylarga
bo'lingan va eski oylari arxivlanib o'chiriladi — 20-bo'lim —, hisobot esa barcha davr harakatlarini o'qiydi). Yangi bo'sh
jadval: mavjud jadvallarni bloklamaydi (faqat `users` ga tashqi kalit — bir lahzalik). `store_id` yo'q — RLS ostida emas;
RevisionMap: `NONE`. Faqat qo'shiladi (`KeshbekTransfer` o'zgartirish va o'chirishni rad etadi). Kesh/Redis'ga
tegilmaydi. Qaytarish: `php artisan migrate:rollback --step=1` jadvalni o'chiradi — o'tkazmalar yozuvi yo'qoladi (balanslar
joyida qoladi, `difference` 0 qoladi, faqat `transferred` va kartadagi yozuv yo'qoladi).

**B: yozuvsiz eski keshbek buyurtmasi qaytarilganda yozuv yaratiladi.** Keshbek to'lovi yozuvi **umuman yo'q** (hech
qanday holatda; review L4: `paid` bo'lmagan — masalan, allaqachon qaytarilgan — yozuvi bor buyurtmaga hech narsa
qaytarilmaydi va yozuv yaratilmaydi, ikki marta berilmaydi) eski keshbek buyurtmasi (o'sha davr kodi balansni yechib, hech narsa yozmagan) bekor qilinsa yoki qaytarilsa,
`OrderStatusService::refundKeshbek` avvalgidek to'liq summani balansga qaytaradi va shu tranzaksiyada yo'q yozuvni
yaratadi: keshbek to'lovi, `refunded`, summa = jami, `payload`: `refunded_to_balance`, `refunded_at` (offset bilan),
`backfilled_without_debit_record: true`, `debited_at` (buyurtma berilgan vaqt). Hisobot uni `spent` da `debited_at`
bo'yicha, `returned` da `refunded_at` bo'yicha sanaydi. Raqamlar (`KeshbekLiabilityReportTest`): avgustda 50 000 yozilgan
balans; 15 oktyabrda 50 000 lik eski buyurtma yozuvsiz yechildi → `live` 0, `expected_now` 50 000, farq **−50 000**,
`keshbek_orders_without_payment` 1; 10 noyabrda qaytarildi → balans 50 000, oktyabr: `spent` 1 ta / 50 000, `returned` 0,
`movement` −50 000, `after_period` +50 000, farq **0**; noyabr: `returned` 1 ta / 50 000; `keshbek_orders_without_payment`
0. Yechish o'tgan oyga tushsa, o'sha oyning hisoboti ham o'zgaradi (haqiqatda o'sha oyda yechilgan). Bu o'zgarishdan oldin
qaytarilgan bunday buyurtmalar (hech narsa yozilmagan, farqi 0) `keshbek_orders_without_payment` da qoladi. Migratsiya
kerak emas (mavjud `payments` ustunlari).

**C: yo'qolgan bonusni qidirish** — yuqorida, «Qachon beriladi» dan keyin.

## 32. Maxfiylik so'rovlari: statistikani o'chirish

Mijoz (yoki uning nomidan kimdir) Mini App'dagi foydalanish statistikasini o'chirishni so'rasa (maxfiylik siyosati,
"Huquqlaringiz"; so'rov siyosatdagi aloqa e-pochtasi yoki Telegram orqali keladi):

1. **Shaxsni tasdiqlang.** So'rov hisobga bog'langan Telegram yoki telefon raqamidan kelganini tekshiring (siyosatdagi
   tartib). Boshqa raqamdan kelsa, hisob egasidan tasdiq so'rang.
2. **Hisobni toping.** Boshqaruv markazi → Mijozlar va yordam → Foydalanuvchilar (ism yoki to'liq telefon), kartani oching.
   "Foydalanish statistikasi" blokida saqlangan yozuvlar soni ko'rinadi.
3. **O'chiring.** "Statistikasini o'chirish" (ruxsat `support.manage`, yangi 2FA kodi), sababga so'rov qanday kelganini
   yozing (masalan, "Telegram orqali so'radi, 26.11.2026"). Javobda nechta yozuv o'chgani chiqadi; jurnalda
   `admin.users.analytics_forgotten` (kim, qaysi hisob, soni, sabab). Bosish takrorlansa ikki marta bajarilmaydi
   (Idempotency-Key).
   Panelga kira olmasangiz, serverda: `docker compose exec -u www-data backend php artisan analytics:forget-user <users.id>
   --reason="..."` — xuddi shu o'chirish, sonini chiqaradi, jurnalda `cli_analytics_forgotten`.
4. **Javob bering.** Mijozga yozuvlar o'chirilganini va zaxiralar haqidagi izohni (pastda) qonunchilikdagi muddatda
   yozing.

**Nima o'chadi.** Faqat shu hisobning `analytics_events` yozuvlari, hamma oylarda (`App\Domain\Analytics\AnalyticsErasure`).
Buyurtmalar, to'lovlar, cheklar, keshbek, harakatlar jurnali va shaxssiz kunlik raqamlar (keyingi qadamlardagi
`analytics_funnel_daily` kabi) o'zgarmaydi — ular boshqa asos bilan saqlanadi (siyosat 6-bo'lim). O'chirish statistika
yig'ishni to'xtatmaydi: mijoz Mini App'dan foydalanishda davom etsa, yangi yozuvlar yana paydo bo'ladi (to'liq to'xtatish
uchun hisobni o'chirish so'rovi kerak).

**Zaxiralar.** O'chirilgan yozuvlar zaxira nusxalarda o'z muddati tugaguncha qoladi va ulardan alohida o'chirilmaydi:
uzluksiz zaxira (PITR, 17-bo'lim) — serverda ~14 kun, S3 da ~28 kun; kunlik `pg_dump` (4-bo'lim) — `BACKUP_RETENTION_DAYS`
(14) kun va S3 dagi saqlash muddati (statistika ma'lumotini dump'dan chiqarib tashlash maxfiylik siyosati tarmog'ida
alohida). Bazani vaqtga qaytarib tiklasangiz (18-bo'lim), tiklangan nuqtadan keyin qilingan o'chirishlar qaytib keladi:
tiklashdan keyin jurnaldan `admin.users.analytics_forgotten` va `cli_analytics_forgotten` yozuvlarini topib, shu
hisoblar uchun o'chirishni qaytadan bajaring. Mijozga javobda yozing: "zaxira nusxalarda yozuvlar ko'pi bilan 28 kun
qolishi va keyin avtomatik o'chirilishi mumkin".

**Muddat bo'yicha.** Batafsil yozuvlar baribir 3 oydan keyin o'zi o'chadi (20-bo'lim, `ANALYTICS_EVENTS_MONTHS`).

