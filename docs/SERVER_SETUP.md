# Serverni tayyorlash va birinchi deploy (egasi va texnik yordamchi uchun)

Bu qo'llanma oddiy qadamlar bilan yozilgan: server sotib olishdan birinchi deploygacha. Kod tomoni tayyor: edge proksi (80/443 va
avtomatik HTTPS), GitHub Actions orqali deploy, avtomatik orqaga qaytarish, zaxira. **Server paydo bo'lgan kuni faqat ikki narsa
kerak: DNS yozuvlari va sirlar** (SSH kalit, parollar, bot tokeni). Qolgani shu hujjatdagi buyruqlar.

Texnik tafsilotlar: [DEPLOY.md](DEPLOY.md) (11-bo'lim), [OPERATIONS.md](OPERATIONS.md), [LAUNCH_PLAN.md](LAUNCH_PLAN.md).

> **Qoida: sirlarni hech qachon chatga, e-pochtaga, Telegram'ga yoki git'ga yozmang.** SSH maxfiy kaliti, parollar, bot tokeni,
> `.env` fayllari faqat ikki joyda turadi: serverdagi `.env` fayllarida va GitHub Secrets'da. Yaratilgan maxfiy kalitning
> nusxasi parol menejerida (1Password, Bitwarden) saqlanadi.

## 0. Umumiy rasm

```
GitHub (main'ga push)
   |  1. obrazlar yig'iladi va GHCR'ga yuklanadi (ghcr.io/<egasi>/<repo>/...)
   |  2. fayllar to'plami SSH orqali serverga: /opt/rizqim
   v
Server (Docker Compose)
   infra/deploy/deploy.sh:  obrazlarni tortadi -> (production: zaxira oladi) -> xizmatlarni yangilaydi
                            -> hammasi healthy -> migrate --force -> HTTP tekshiruvi
                            -> xato bo'lsa: AVTOMATIK avvalgi versiyaga qaytadi
   edge (Caddy): 80/443, sertifikatlarni o'zi oladi; subdomenlarni xizmatlarga yo'naltiradi
```

- **Staging** (`stg.rizqim.uz`): `main` ga har bir push o'zi joylanadi.
- **Production** (`rizqim.uz`): faqat qo'lda (Actions -> Deploy -> Run workflow -> `production`) va egasi tasdiqlagandan keyin.
- Ikkita alohida server: staging va production bir-biridan butunlay ajratilgan (boshqa baza, boshqa bot, boshqa kalitlar).

## 1. Server sotib olish (VPS)

Provayderni egasi tanlaydi. Talablar: **Ubuntu 24.04 LTS**, ommaviy IPv4 manzil, 80 va 443 portlar ochiq, SSH `root` yoki sudo bilan
kirish, NVMe/SSD disk. Server joylashuvi: pilot uchun mayli; shaxsiy ma'lumotlar qonuni bo'yicha yurist fikri **ochiq ishga
tushirishdan oldin** kerak ([LAUNCH_PLAN.md](LAUNCH_PLAN.md), «Egasidan kerak bo'ladiganlar»).

Pilot (1-3 do'kon) uchun o'lchamlar. Asos: LAUNCH_PLAN «Maqsadli hajm» (M2 dan keyingi o'lchov: 100 do'konda ~2 PHP yadro va
~0,7 baza yadrosi; bu raqamlar sinov mashinasidan olingan taxmin, haqiqiy serverda o'lchanmagan) va `docker-compose.staging.yml`
dagi xotira chegaralari:

| Server | vCPU | RAM | Disk | Izoh |
|---|---|---|---|---|
| **Production** (pilot) | 4 | 8 GB | 100 GB NVMe | 100 do'konga yetadi (taxminan 2 PHP + 0,7 baza yadrosi, qolgani zaxira). Xizmatlar ~4-5 GB xotira oladi, qolgani OS va kesh uchun |
| **Staging** | 2 | 4 GB | 50 GB | Funksional sinov uchun. Yuk sinovi (L bosqichi, 1 000 do'kon) uchun vaqtincha ancha katta server olinadi, bu alohida qaror |
| Disk o'sishi | | | | Baza taxminan 75 GB/yil 100 do'konda; pilotda juda kam. PITR zaxirasi shu diskda ~14 kun turadi: disk 70 % dan oshsa kattalashtiring |

Provayder panelida: server avtomatik **snapshot/backup** xizmatini yoqing (qo'shimcha himoya; asosiy zaxira pastda, 8-bo'lim).
Server nomi: `rizqim-staging`, `rizqim-prod`. IP manzillarni yozib qo'ying: `STG_IP`, `PROD_IP`.

## 2. DNS yozuvlari

Domen ro'yxatga olingan joyda (registrator yoki DNS provayderi) A yozuvlari qo'shiladi. TTL: 300 (5 daqiqa; xato bo'lsa tez
tuzatiladi). **Sertifikat faqat DNS serverga qaragandan keyin olinadi**, shuning uchun avval DNS, keyin deploy.

**Production** (`PROD_IP` o'rniga server manzili):

| Nom (host) | Tur | Qiymat | Nima uchun |
|---|---|---|---|
| `rizqim.uz` (`@`) | A | PROD_IP | landing |
| `www` | A | PROD_IP | `rizqim.uz` ga yo'naltiriladi |
| `app` | A | PROD_IP | Telegram Mini App |
| `api` | A | PROD_IP | backend API, webhook'lar, WebSocket |
| `business` | A | PROD_IP | hamkor paneli |
| `admin` | A | PROD_IP | boshqaruv markazi |
| `pos` | A | PROD_IP | POS ilovalarini yuklab olish |
| `links` | A | PROD_IP | vaqtincha sahifa |
| `rizqim.com`, `www.rizqim.com`, `rizq.im`, `www.rizq.im` | A | PROD_IP | asosiy domenga yo'naltirish (5.3-qadam, ixtiyoriy) |

**Staging** (`STG_IP`): `stg` va `*.stg` (yulduzcha: bitta yozuv hamma `*.stg.rizqim.uz` uchun). Yulduzcha qo'llab-quvvatlanmasa,
alohida: `stg`, `www.stg`, `app.stg`, `api.stg`, `business.stg`, `admin.stg`, `pos.stg`, `links.stg`.

Tekshirish (o'z kompyuteringizda; hammasi server IP'sini ko'rsatishi kerak):

```bash
dig +short rizqim.uz app.rizqim.uz api.rizqim.uz business.rizqim.uz admin.rizqim.uz pos.rizqim.uz links.rizqim.uz
dig +short stg.rizqim.uz app.stg.rizqim.uz api.stg.rizqim.uz
```

IPv6 (AAAA) yozuvini faqat serverda IPv6 sozlangan va firewall'da ochilgan bo'lsa qo'shing; aks holda qo'shmang.

## 3. Serverga birinchi kirish: deploy foydalanuvchisi va SSH kalit

### 3.1. SSH kalitni o'z kompyuteringizda yarating

Staging va production uchun **alohida** kalit (bittasi chiqib ketsa ikkinchisi xavfsiz qoladi). Parolsiz (CI parol kiritolmaydi):

```bash
ssh-keygen -t ed25519 -N "" -C "rizqim-deploy-staging" -f ~/rizqim-deploy-staging
ssh-keygen -t ed25519 -N "" -C "rizqim-deploy-prod" -f ~/rizqim-deploy-prod
```

Har kalit ikki fayl beradi: `...` (**maxfiy**, GitHub Secrets'ga boradi, hech kimga ko'rsatilmaydi) va `....pub` (ochiq, serverga
qo'yiladi). Maxfiy kalit GitHub'ga kiritilgach: parol menejeriga nusxa saqlab, kompyuterdagi faylni o'chirib tashlang.

### 3.2. Serverda foydalanuvchi yarating (provayder bergan `root` kirishi bilan)

```bash
ssh root@STG_IP
adduser --disabled-password --gecos "" deploy
mkdir -p /home/deploy/.ssh
# ochiq kalitni (rizqim-deploy-staging.pub fayli ichidagi bitta qatorni) shu faylga qo'ying:
nano /home/deploy/.ssh/authorized_keys
chmod 700 /home/deploy/.ssh && chmod 600 /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
mkdir -p /opt/rizqim && chown deploy:deploy /opt/rizqim
```

Boshqa terminalda tekshiring (kalit bilan kirish ishlashi kerak): `ssh -i ~/rizqim-deploy-staging deploy@STG_IP`.
Ishlagandan keyin parol bilan kirishni o'chiring: `/etc/ssh/sshd_config` da `PasswordAuthentication no` va
`PermitRootLogin prohibit-password`, so'ng `systemctl reload ssh`. (Eslatma: Docker guruhiga kirgan foydalanuvchi serverda
amalda `root` huquqiga ega: kalit shu darajada himoyalansin.)

## 4. Docker, firewall, asosiy sozlamalar

Serverda (`root` yoki `sudo` bilan):

```bash
apt update && apt -y upgrade
# Docker Engine va Compose v2 (rasmiy o'rnatish skripti; docs.docker.com/engine/install/ubuntu dagi apt usuli ham bo'ladi)
curl -fsSL https://get.docker.com -o /root/get-docker.sh && sh /root/get-docker.sh
usermod -aG docker deploy
systemctl enable --now docker
docker compose version          # v2.24 yoki yangi bo'lishi shart (compose fayllari `!reset` va `!override` ishlatadi)
```

Konteyner loglari diskni to'ldirib qo'ymasin (`/etc/docker/daemon.json`), keyin `systemctl restart docker`:

```json
{ "log-driver": "json-file", "log-opts": { "max-size": "20m", "max-file": "5" } }
```

Firewall (avval ruxsatlar, keyin yoqish: aks holda o'zingizni qulflab qo'yasiz):

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp      # HTTP/3
ufw enable
ufw status
```

Muhim: Docker o'zi ochgan portlarni `ufw` dan chetlab o'tadi. Shuning uchun compose fayllarida hamma ichki port
`127.0.0.1` ga bog'langan va tashqariga faqat `edge` (80/443) chiqadi. Bu sozlamani o'zgartirmang (CI buni tekshiradi).

Qo'shimcha (tavsiya): `apt -y install unattended-upgrades fail2ban`; 2 GB swap fayl; `timedatectl set-timezone UTC` (zaxira vaqtlari UTC
da hisoblangan: LAUNCH_PLAN va `.env.example` dagi `BACKUP_TIME`).

Production serverda ham xuddi shu qadamlar (3 va 4-bo'limlar) `PROD_IP` va `rizqim-deploy-prod` kaliti bilan takrorlanadi.

## 5. GitHub: sirlar, o'zgaruvchilar, environment'lar

Repozitoriy sahifasi -> **Settings**.

### 5.1. Secrets (Settings -> Secrets and variables -> Actions -> *Secrets* -> New repository secret)

| Nom | Qiymat | Majburiy |
|---|---|---|
| `STAGING_SSH_HOST` | staging server IP yoki host nomi | ha |
| `STAGING_SSH_USER` | `deploy` | ha |
| `STAGING_SSH_KEY` | `~/rizqim-deploy-staging` faylining **butun matni** (`-----BEGIN OPENSSH PRIVATE KEY-----` dan oxirigacha) | ha |
| `STAGING_SSH_KNOWN_HOSTS` | `ssh-keyscan -p 22 STG_IP` natijasi (serverga ulanayotganingizga ishonch hosil qiladi) | tavsiya |
| `STAGING_SSH_PORT` | SSH porti, standart `22` bo'lsa kerak emas | yo'q |
| `PROD_SSH_HOST`, `PROD_SSH_USER`, `PROD_SSH_KEY` | production uchun xuddi shunday (`rizqim-deploy-prod`) | ha |
| `PROD_SSH_KNOWN_HOSTS`, `PROD_SSH_PORT` | yuqoridagidek | tavsiya / yo'q |

GHCR (obrazlar ombori) uchun alohida sir kerak emas: workflow `GITHUB_TOKEN` ni o'zi ishlatadi. **Sirlar bo'lmaguncha deploy
workflow'i hech narsa qilmaydi va xato ham bermaydi** (o'tkazib yuboriladi).

### 5.2. Variables (Settings -> Secrets and variables -> Actions -> *Variables*)

Bular sir emas (Mini App ichiga yoziladi). Hech biri majburiy emas: berilmasa standart ishlaydi.

| Nom | Standart | Izoh |
|---|---|---|
| `STAGING_VITE_BOT_USERNAME` | `rizqim_staging_bot` | staging TEST boti (`@` siz) |
| `PROD_VITE_BOT_USERNAME` | `rizqimbot` | haqiqiy bot |
| `STAGING_VITE_PARTNER_WEB_URL`, `PROD_VITE_PARTNER_WEB_URL` | `https://business.stg.rizqim.uz`, `https://business.rizqim.uz` | |
| `STAGING_VITE_MAP_TILE_URL`, `PROD_VITE_MAP_TILE_URL`, `*_VITE_MAP_ATTRIBUTION` | bo'sh | production'da xarita provayderi (ochiq OSM faqat sinov uchun) |
| `STAGING_VITE_SENTRY_DSN`, `PROD_VITE_SENTRY_DSN` | bo'sh | Sentry bo'lsa |

### 5.3. Environment'lar va production himoyasi

Settings -> **Environments** -> New environment: `staging` va `production`.

**`production` uchun (shart):**
1. **Required reviewers** ni yoqing va o'zingizni (egasini) qo'shing. Shundan keyin production deploy siz "Approve" bosmaguncha
   boshlanmaydi. (Faqat siz bo'lsangiz, "Prevent self-review" ni o'chirib qo'ying, aks holda o'zingiz tasdiqlay olmaysiz.)
2. **Deployment branches and tags**: *Selected branches* -> `main` (workflow o'zi ham production'ni faqat `main` yoki teg'dan qabul qiladi).

`staging` uchun qoidalar kerak emas.

## 6. Birinchi marta: fayllarni yuklash va `.env` yaratish

Server hali bo'sh: `.env` fayllari va compose fayllari yo'q. Fayllar to'plamini GitHub yuklaydi:

1. GitHub -> Actions -> **Deploy** -> *Run workflow* -> `target = staging`, **`only_upload` belgilang** -> Run.
   (Obraz yig'ilmaydi va deploy qilinmaydi: faqat fayllar `/opt/rizqim` ga tushadi.)
2. Serverda:

```bash
ssh deploy@STG_IP
cd /opt/rizqim
cp .env.staging.example .env
cp backend/.env.staging.example backend/.env
chmod 600 .env backend/.env
mkdir -p pos-files backups
nano .env backend/.env          # quyidagi jadval bo'yicha to'ldiring
```

Production uchun: `.env.production.example` va `backend/.env.production.example`.

**To'ldiriladigan qiymatlar** (qolgani namunada tayyor, domenlar ham: `stg.rizqim.uz` / `rizqim.uz`):

| Fayl | Kalit | Qanday olinadi |
|---|---|---|
| `.env` | `DB_PASSWORD` | `openssl rand -hex 24` (har serverga alohida; `$` belgisi bo'lmasin) |
| `.env` | `REDIS_PASSWORD` | `openssl rand -hex 24` |
| `.env` | `EDGE_ACME_EMAIL` | sizning e-pochtangiz (Let's Encrypt sertifikat muddati haqida yozadi) |
| `.env` | `PITR_CIPHER_PASS` | `openssl rand -base64 48`; **server tashqarisida (parol menejerida) saqlang**: yo'qolsa S3 dagi zaxiralar o'qilmaydi |
| `.env` | `BACKUP_S3_*`, `HEALTHCHECKS_PING_URL`, `PITR_PING_URL` | 8 va 9-bo'limlar (pilotdan oldin tavsiya) |
| `backend/.env` | `APP_KEY` | `echo "base64:$(openssl rand -base64 32)"`; production kaliti parol menejerida; staging va production kalitlari **har xil** |
| `backend/.env` | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME` | BotFather. **Staging: alohida test bot**; haqiqiy botni staging'ga bermang (webhook bitta botga bitta manzil) |
| `backend/.env` | `TELEGRAM_WEBHOOK_SECRET` | `openssl rand -hex 32` |
| `backend/.env` | `CLICK_*`, `PAYME_*` | staging: faqat sinov (sandbox) kalitlari; production: haqiqiy merchant kalitlari |
| `backend/.env` | `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` | ixtiyoriy (birinchi superadmin odatda `rizqim:admin:create` bilan, 7-qadam); parol kamida 12 belgi |
| `backend/.env` | `SENTRY_LARAVEL_DSN`, `TELEGRAM_OPS_CHAT_ID`, `HEALTH_TOKEN`, `NOMINATIM_EMAIL` | ixtiyoriy, ma'nosi `backend/.env.example` da |

Reverb kalitlari (`REVERB_APP_*`) bo'sh qoladi: backend birinchi ishga tushishda o'zi yaratadi (DEPLOY.md, 9-bo'lim).

### Har domen uchun qiymatlar (namunalarda tayyor, tekshirish uchun)

| Qiymat | Production | Staging |
|---|---|---|
| `EDGE_DOMAIN`, `EDGE_PROFILE` (`.env`) | `rizqim.uz`, `production` | `stg.rizqim.uz`, `staging` |
| `APP_URL` (`backend/.env`) | `https://api.rizqim.uz` | `https://api.stg.rizqim.uz` |
| `TELEGRAM_WEBAPP_URL` | `https://app.rizqim.uz` | `https://app.stg.rizqim.uz` |
| Telegram webhook (`TELEGRAM_WEBHOOK_URL` bo'sh = `APP_URL` + yo'l) | `https://api.rizqim.uz/api/v1/telegram/webhook` | `https://api.stg.rizqim.uz/api/v1/telegram/webhook` |
| `PARTNER_WEB_URL` | `https://business.rizqim.uz` | `https://business.stg.rizqim.uz` |
| `ADMIN_APP_URL` | `https://admin.rizqim.uz` | `https://admin.stg.rizqim.uz` |
| `CORS_ALLOWED_ORIGINS` | `https://business.rizqim.uz,https://app.rizqim.uz` | `https://business.stg.rizqim.uz,https://app.stg.rizqim.uz` |
| `REVERB_ALLOWED_ORIGINS` (ikkala faylda bir xil) | `app.rizqim.uz,business.rizqim.uz,api.rizqim.uz` | `app.stg.rizqim.uz,business.stg.rizqim.uz,api.stg.rizqim.uz` |
| `PARTNER_PUBLIC_API_BASE_URL` (`.env`) | `https://api.rizqim.uz/api` | `https://api.stg.rizqim.uz/api` |
| `VITE_API_URL` (Mini App) | `/api/v1` (nisbiy) | `/api/v1` |
| `VITE_PARTNER_WEB_URL` | `https://business.rizqim.uz` | `https://business.stg.rizqim.uz` |
| `VITE_REVERB_HOST` | bo'sh (Mini App domeni) | bo'sh |
| `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS` | `null` / o'rnatilmaydi | xuddi shunday (API Bearer token bilan ishlaydi, cookie-sessiya yo'q) |
| Click/Payme webhook manzillari (merchant kabineti) | `https://api.rizqim.uz/api/v1/payments/click/webhook` va `.../payme/webhook` | `https://api.stg.rizqim.uz/...` (sandbox) |

`VITE_*` qiymatlari avtomatik deploy'da serverdagi `.env` dan emas, GitHub *Variables*'dan olinadi (5.2).

### 6.1. admin.<domen> ni IP bilan cheklash (tavsiya)

Boshqaruv markazi parol va 2FA bilan himoyalangan; ofis/uy IP'si aniq bo'lsa qo'shimcha qulf qo'ying:

```bash
cd /opt/rizqim
cp infra/edge/conf.d/admin-allow.caddy.example infra/edge/conf.d/admin-allow.caddy
nano infra/edge/conf.d/admin-allow.caddy          # sizning IP/CIDR manzillaringiz (o'zingizniki bo'lsin!)
echo 'EDGE_ADMIN_ACCESS=/etc/caddy/conf.d/admin-allow.caddy' >> .env
docker compose up -d edge
```

Qaytarish: `.env` dan `EDGE_ADMIN_ACCESS` qatorini o'chirib `docker compose up -d edge`.

### 6.2. rizqim.com va rizq.im ni asosiyga yo'naltirish (faqat production, ixtiyoriy)

```bash
cp infra/edge/conf.d/aliases.caddy.example infra/edge/conf.d/aliases.caddy
docker compose up -d edge
```

Ikkala domenning DNS yozuvlari (2-bo'lim) shu serverga qaragan bo'lishi kerak.

## 7. Birinchi deploy

### 7.1. Staging

1. DNS tayyorligini tekshiring (2-bo'lim). Sirlar 5-bo'limdagidek kiritilgan, `.env` fayllar to'ldirilgan.
2. Actions -> **Deploy** -> Run workflow -> `staging` (yoki `main` ga biror o'zgarish push qiling).
3. Nima bo'ladi: obrazlar yig'iladi (birinchi marta ~15-25 daqiqa, keyin kesh bilan tezroq) -> serverga yuklanadi ->
   `deploy.sh` ishlaydi. Birinchi ishga tushishda backend migratsiyalarni o'tkazadi (~2 daqiqa). Sertifikatlar birinchi
   so'rovda olinadi (1-2 daqiqa).
4. Tekshirish:

```bash
curl -fsS https://api.stg.rizqim.uz/up
curl -I https://app.stg.rizqim.uz/            # strict-transport-security va x-robots-tag: noindex bo'lishi kerak
ssh deploy@STG_IP 'cd /opt/rizqim && docker compose ps'     # hamma xizmat healthy
ssh deploy@STG_IP 'cd /opt/rizqim && infra/deploy/deploy.sh status'
```

5. Birinchi superadmin va hududlar (DEPLOY.md, 5-bo'lim), serverda `/opt/rizqim` da:

```bash
docker compose exec -u www-data backend php artisan rizqim:admin:create boss@rizqim.uz "Bosh admin"
docker compose exec -u www-data backend php artisan db:seed --class=LocationSeeder --force
```

Chiqqan taklif havolasi (`https://admin.stg.rizqim.uz/invite/...`) orqali parol va 2FA o'rnatiladi. Keyin: DEPLOY.md, 10-bo'limdagi
«Staging'da tekshiruv ro'yxati».

### 7.2. Production

1. Staging'da tekshiruv ro'yxati o'tgan, kerakli commit `main` da. Production serverda ham 3-6 qadamlar bajarilgan.
2. Actions -> **Deploy** -> Run workflow -> `main` (yoki teg) -> `target = production` -> Run.
3. Workflow qadamda to'xtaydi: **"Review deployments"** -> *production* ni belgilab **Approve and deploy**. (Tasdiqlovchi: siz.)
4. Production'da `deploy.sh` migratsiyadan oldin **zaxira oladi** (`backup.sh`). Zaxira xato bersa deploy boshlanmaydi.
5. Deploydan keyin: `https://rizqim.uz/`, `https://app.rizqim.uz/`, `https://api.rizqim.uz/up`, `https://admin.rizqim.uz/login`.
6. Telegram: BotFather'da haqiqiy botning Mini App manzili `https://app.rizqim.uz`; webhook'ni deploy o'zi o'rnatadi
   (tekshirish: `curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"`; tokenni shu buyruqqa faqat o'zingiz, o'z terminalingizda yozing).

### 7.3. Orqaga qaytarish

- **Avtomatik:** deploy paytida biror xizmat sog'lom bo'lmasa, `/health` yoki migratsiya xato bersa, skript oxirgi yaxshi
  versiyaga qaytadi va workflow **qizil** bo'ladi. Birinchi deploy'da qaytariladigan versiya yo'q: loglarni ko'ring.
- **Qo'lda:** `ssh deploy@IP 'cd /opt/rizqim && infra/deploy/deploy.sh rollback'` (avvalgi versiyaga). Yana bir marta
  ishlatsangiz oldingi versiyaga qaytadi.
- **Eski versiyani qayta joylash:** Actions -> Deploy -> Run workflow, "Use workflow from" da eski teg/branch.
- **Diqqat:** migratsiyalar faqat oldinga ketadi. Qaytarish kod obrazini qaytaradi, bazani emas. Yangi migratsiya eski kod bilan
  ishlay oladigan bo'lsin (avval ustun qo'shish, keyingi relizda eskisini olib tashlash). Baza buzilgan bo'lsa zaxiradan tiklash:
  OPERATIONS.md, 4 va 18-bo'limlar.
- **Uzilish:** yangilash paytida backend ~0,5-3 daqiqa javob bermaydi. edge shu vaqtda so'rovlarni 502 o'rniga 60 soniyagacha
  ushlab turadi, undan uzoq bo'lsa 502. Shuning uchun production'ni kam yuk paytida (tunda) joylang. To'liq uzilishsiz
  yangilash (ikki nusxa) hozir yo'q.

## 8. Zaxira nusxalar

Tayyor: `backup` xizmati (kunlik `pg_dump`) va uzluksiz zaxira (PITR, istalgan daqiqaga qaytarish). To'liq tavsif:
OPERATIONS.md, 4 va 17-21-bo'limlar. Siz qiladigan ish:

1. **Tashqi saqlash (S3-mos):** Cloudflare R2, Backblaze B2 yoki boshqa. Production uchun alohida bucket (yoki prefiks) va **alohida kalitlar**:
   PITR uchun (o'chirish huquqi bilan) va jurnallar arxivi uchun (o'chirish huquqisiz). `.env` dagi `BACKUP_S3_*`, `PITR_S3_*`,
   `ARCHIVE_S3_*`. Staging bucket'iga production kalitini bermang.
2. `PITR_ENABLED=1` (namunalarda yoqilgan) va `PITR_CIPHER_PASS` parol menejerida.
3. Birinchi deploydan keyin **tiklash mashqini** o'tkazing: `scripts/restore-drill.sh` (OPERATIONS.md, 19-bo'lim) va tiklash vaqtini yozib qo'ying.
   Tiklab ko'rilmagan zaxira zaxira emas.
4. Provayderning server snapshot'ini yoqing (1-bo'lim): bu qo'shimcha qatlam.
5. Tekshirish: `ls /opt/rizqim/backups` da yangi `rizqim-*.dump`; PITR holati: `scripts/pitr-restore.sh --info` (OPERATIONS.md, 17-bo'lim).

## 9. Monitoring

Hammasi bepul darajada boshlanadi (OPERATIONS.md, 21-bo'lim):

| Nima | Qanday |
|---|---|
| Sayt ishlayaptimi | UptimeRobot (yoki shunga o'xshash): `https://api.rizqim.uz/api/v1/health`, `https://rizqim.uz/`, `https://app.rizqim.uz/`; Telegram yoki e-pochta orqali ogohlantirish |
| Server o'chib qolsa | healthchecks.io: ping manzilini `.env` dagi `HEALTHCHECKS_PING_URL` ga (scheduler har daqiqa yuboradi) va `PITR_PING_URL` ga yozing |
| Ichki ogohlantirishlar (baza, navbat, Reverb, disk) | `backend/.env` da `TELEGRAM_OPS_CHAT_ID` (botni guruhga qo'shing); boshqaruv markazi -> Monitoring -> Holat |
| Xatolar | Sentry: `SENTRY_LARAVEL_DSN` (`backend/.env`) va `*_VITE_SENTRY_DSN` (GitHub Variables) |
| Sertifikat muddati | Caddy o'zi yangilaydi; backend `MONITORING_TLS_HOST` ni tekshiradi (boshqaruv markazida ogohlantirish) |
| Disk | provayder panelida disk 80 % ogohlantirishi; serverda `df -h`, `docker system df` |

## 10. Kundalik buyruqlar (serverda, `/opt/rizqim` da)

```bash
docker compose ps                              # holat
docker compose logs -f --tail=100 backend      # backend logi (edge, queue, scheduler, reverb ham shunday)
docker compose logs --tail=100 edge            # sertifikat va marshrutlash xatolari
infra/deploy/deploy.sh status                  # joriy va avvalgi versiya
infra/deploy/deploy.sh rollback                # avvalgisiga qaytarish
docker compose exec -u www-data backend php artisan <buyruq>     # ops buyruqlari (DEPLOY.md, 8-bo'lim)
docker image prune -f                          # keraksiz obrazlar (deploy buni o'zi qiladi)
```

Qo'lda `docker compose pull` qilsangiz GHCR'ga kirish kerak bo'ladi (deploy tokeni faqat o'sha deploy davomida amal qiladi):
yangi versiyalarni doim workflow orqali joylang.

## 11. Muammolar

| Alomat | Sabab va yechim |
|---|---|
| Workflow "skipped" (kulrang), deploy bo'lmadi | `STAGING_SSH_*` / `PROD_SSH_*` sirlari yo'q (5.1). Xato emas |
| `Permission denied (publickey)` | maxfiy kalit to'liq kiritilmagan (BEGIN/END qatorlari bilan) yoki ochiq kalit serverdagi `authorized_keys` da yo'q |
| `Host key verification failed` | `*_SSH_KNOWN_HOSTS` noto'g'ri; server qayta o'rnatilgan bo'lsa `ssh-keyscan` ni yangidan oling |
| `deploy.sh`: `.env yo'q` / `DB_PASSWORD to'ldirilmagan` / `EDGE_ACME_EMAIL bo'sh` | 6-bo'lim: `.env` fayllarni to'ldiring |
| `deploy.sh`: `reliz 'staging' uchun, bu serverning .env i 'production'` | noto'g'ri server yoki `.env` da `DEPLOY_TARGET` xato |
| Obrazlarni tortib bo'lmadi (`denied`) | GHCR paketi repozitoriyga bog'lanmagan: GitHub -> Packages -> paket -> *Package settings* -> *Manage Actions access* -> repozitoriyni "Read" bilan qo'shing; yoki paket egasi boshqa |
| `https://...` ochilmaydi, brauzer sertifikat xatosi | DNS hali serverga qaramaydi yoki 80/443 yopiq. `docker compose logs edge` da ACME xatosi ko'rinadi. Caddy o'zi qayta urinadi; Let's Encrypt xato sonini cheklaydi (soatiga 5 ta): DNS ni tuzatib bir necha daqiqa kuting |
| 502 Bad Gateway | xizmat qayta ishga tushyapti (deploy paytida normal) yoki yiqilgan: `docker compose ps`, `docker compose logs backend` |
| Deploy qizil, "orqaga qaytarildi" | yangi versiyada muammo; log: workflow'dagi "Deploy" qadami va `docker compose logs --tail=100 backend` |
| Admin sahifasi 403 "Forbidden" | `admin-allow.caddy` ro'yxatida sizning IP'ingiz yo'q (6.1); IP o'zgargan bo'lsa ro'yxatni yangilang |

## 12. Egasi beradigan narsalar (qisqa ro'yxat)

1. Ikkita VPS (staging, production) va ularning IP manzillari.
2. DNS yozuvlarini qo'yish huquqi (2-bo'lim).
3. GitHub repozitoriyda *Admin* huquqi: sirlar, variables va `production` environment (Required reviewers).
4. SSH kalitlari (o'z kompyuteringizda yaratiladi, maxfiy qismi faqat GitHub Secrets'ga).
5. Telegram: staging TEST boti va haqiqiy bot (BotFather), tokenlar faqat serverdagi `backend/.env` ga.
6. Click/Payme kalitlari (staging: sandbox).
7. S3-mos saqlash (zaxira) va uning kalitlari; healthchecks.io / UptimeRobot hisoblari; (ixtiyoriy) Sentry.
8. `EDGE_ACME_EMAIL` uchun e-pochta.
