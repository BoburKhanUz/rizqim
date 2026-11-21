# Serverni tayyorlash va birinchi deploy (egasi va texnik yordamchi uchun)

Bu qo'llanma oddiy qadamlar bilan yozilgan: server sotib olishdan birinchi deploygacha. Kod tomoni tayyor: edge proksi (80/443 va
avtomatik HTTPS), GitHub Actions orqali deploy, avtomatik orqaga qaytarish, zaxira. **Server paydo bo'lgan kuni faqat ikki narsa
kerak: DNS yozuvlari va sozlamalar** (SSH kalit, parollar, bot tokeni). Qolgani shu hujjatdagi buyruqlar.

**Hozirgi qaror (egasi, 21.11.2026): faqat PRODUCTION server** (pilotni egasi o'zi sinaydi). 1-12-bo'limlar shu yo'l. Staging
server haqiqiy restoranlar ulanishidan oldin qo'shiladi: [13-bo'lim](#13-staging-keyinroq-ixtiyoriy). Staging yoqilmaguncha
`main` ga push hech narsani joylamaydi.

Texnik tafsilotlar: [DEPLOY.md](DEPLOY.md) (11-bo'lim), [OPERATIONS.md](OPERATIONS.md), [LAUNCH_PLAN.md](LAUNCH_PLAN.md).

> **Qoida: sirlarni hech qachon chatga, e-pochtaga, Telegram'ga yoki git'ga yozmang.** SSH maxfiy kaliti, parollar, bot tokeni,
> `.env` fayllari faqat ikki joyda turadi: serverdagi `.env` fayllarida va GitHub'dagi `production` environment sirlarida.
> Yaratilgan maxfiy kalitning nusxasi parol menejerida (1Password, Bitwarden) saqlanadi. Tokenlarni buyruq satriga ham yozmang
> (shell tarixida va `ps` da qoladi): kerak bo'lsa ular serverdagi konteyner ichidan o'qiladi (7.2, 6-qadam).

## 0. Umumiy rasm

```
GitHub (Actions -> Deploy -> Run workflow -> production; egasi "Approve" bosadi)
   |  1. obrazlar yig'iladi va GHCR'ga yuklanadi (ghcr.io/<egasi>/<repo>/..., PRIVATE)
   |  2. fayllar to'plami SSH orqali serverga: /opt/rizqim/.deploy/releases/<tag>/ (ishlayotgan fayllarga tegmaydi)
   v
Server (Docker Compose)
   deploy.sh:  tekshiruvlar (.env, parollar, yangi compose fayllari) -> obrazlarni tortish -> zaxira (backup.sh)
               -> SHUNDAN KEYIN fayllarni almashtirish -> xizmatlarni yangilash -> edge: caddy reload
               -> hammasi healthy -> migrate --force -> HTTP tekshiruvi
               -> xato bo'lsa: AVTOMATIK avvalgi versiyaga (uning fayllari va obrazlariga) qaytadi
   edge (Caddy): 80/443, sertifikatlarni o'zi oladi; subdomenlarni xizmatlarga yo'naltiradi
```

- **Production** (`rizqim.uz`): faqat qo'lda (Actions -> Deploy -> Run workflow -> `production`), faqat `main` dan va egasi
  tasdiqlagandan keyin. Teg yoki boshqa branch'dan production deploy qilinmaydi.
- `main` ga push: staging yoqilmaguncha (13-bo'lim) hech narsa qilmaydi.

## 1. Server sotib olish (VPS)

Provayderni egasi tanlaydi. Talablar: **Ubuntu 24.04 LTS**, ommaviy IPv4 manzil, 80 va 443 portlar ochiq, SSH `root` yoki sudo bilan
kirish, NVMe/SSD disk. Server joylashuvi: pilot uchun mayli; shaxsiy ma'lumotlar qonuni bo'yicha yurist fikri **ochiq ishga
tushirishdan oldin** kerak ([LAUNCH_PLAN.md](LAUNCH_PLAN.md), «Egasidan kerak bo'ladiganlar»).

Pilot (1-3 do'kon) uchun o'lcham. Asos: LAUNCH_PLAN «Maqsadli hajm» (M2 dan keyingi o'lchov: 100 do'konda ~2 PHP yadro va
~0,7 baza yadrosi; bu raqamlar sinov mashinasidan olingan taxmin, haqiqiy serverda o'lchanmagan):

| Server | vCPU | RAM | Disk | Izoh |
|---|---|---|---|---|
| **Production** (pilot) | 4 | 8 GB | 100 GB NVMe | 100 do'konga yetadi (taxminan 2 PHP + 0,7 baza yadrosi, qolgani zaxira). Xizmatlar ~4-5 GB xotira oladi, qolgani OS va kesh uchun |
| Disk o'sishi | | | | Baza taxminan 75 GB/yil 100 do'konda; pilotda juda kam. PITR zaxirasi shu diskda ~14 kun turadi: disk 70 % dan oshsa kattalashtiring |

Provayder panelida: server avtomatik **snapshot/backup** xizmatini yoqing (qo'shimcha himoya; asosiy zaxira pastda, 8-bo'lim).
Server nomi: `rizqim-prod`. IP manzilni yozib qo'ying: `PROD_IP`.

## 2. DNS yozuvlari (faqat production)

Domen ro'yxatga olingan joyda (registrator yoki DNS provayderi) A yozuvlari qo'shiladi. TTL: 300 (5 daqiqa; xato bo'lsa tez
tuzatiladi). **Sertifikat faqat DNS serverga qaragandan keyin olinadi**, shuning uchun avval DNS, keyin deploy.

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
| `rizqim.com`, `www.rizqim.com`, `rizq.im`, `www.rizq.im` | A | PROD_IP | asosiy domenga yo'naltirish (6.2, ixtiyoriy) |

Staging yozuvlari (`stg`, `*.stg`) hozir **kerak emas**: 13-bo'lim.

Tekshirish (o'z kompyuteringizda; hammasi server IP'sini ko'rsatishi kerak):

```bash
dig +short rizqim.uz app.rizqim.uz api.rizqim.uz business.rizqim.uz admin.rizqim.uz pos.rizqim.uz links.rizqim.uz
```

**IPv6 (AAAA) yozuvlarini pilotda QO'YMANG** (faqat A, IPv4). Sabab: Docker standart sozlamada IPv6 ulanishni konteynerga o'z
ichki IPv4 manzilidan (172.x) uzatadi. Shunda Caddy IPv6 mijozning haqiqiy manzilini ko'rmaydi: admin IP ro'yxati (6.1) ularni
rad etadi (yoki 172.16.0.0/12 qo'shib yuborilsa, hammaga ochiladi), kirish limitlari esa hamma IPv6 mijozni bitta IP deb
hisoblaydi. Bundan tashqari AAAA bo'lib, IPv6 ishlamasa, Let's Encrypt tekshiruvi va ba'zi mijozlar ulanishi buziladi.
IPv6 kerak bo'lsa: avval Docker'da IPv6 ni to'liq yoqish (`/etc/docker/daemon.json`: `ipv6`, `ip6tables`, compose tarmog'iga
IPv6 subnet), firewall'da 80/443 ni IPv6 uchun ochish va `curl -6` bilan haqiqiy IP ko'rinishini tekshirish, keyin AAAA. Bu alohida ish.

## 3. Serverga birinchi kirish: deploy foydalanuvchisi va SSH kalit

### 3.1. SSH kalitni o'z kompyuteringizda yarating

Faqat production uchun kalit (staging paydo bo'lganda unga **alohida** kalit yaratiladi, 13-bo'lim). Parolsiz (CI parol kiritolmaydi):

```bash
ssh-keygen -t ed25519 -N "" -C "rizqim-deploy-prod" -f ~/rizqim-deploy-prod
```

Kalit ikki fayl beradi: `~/rizqim-deploy-prod` (**maxfiy**, GitHub'dagi `production` environment siriga boradi, hech kimga
ko'rsatilmaydi) va `~/rizqim-deploy-prod.pub` (ochiq, serverga qo'yiladi). Maxfiy kalit GitHub'ga kiritilgach: parol menejeriga
nusxa saqlab, kompyuterdagi faylni o'chirib tashlang.

### 3.2. Serverda foydalanuvchi yarating (provayder bergan `root` kirishi bilan)

```bash
ssh root@PROD_IP
adduser --disabled-password --gecos "" deploy
mkdir -p /home/deploy/.ssh
# ochiq kalitni (rizqim-deploy-prod.pub fayli ichidagi bitta qatorni) shu faylga qo'ying:
nano /home/deploy/.ssh/authorized_keys
chmod 700 /home/deploy/.ssh && chmod 600 /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
mkdir -p /opt/rizqim && chown deploy:deploy /opt/rizqim
# Server kalitining barmoq izi (5.1 da SSH_KNOWN_HOSTS ni tekshirish uchun yozib qo'ying):
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
```

Boshqa terminalda tekshiring (kalit bilan kirish ishlashi kerak): `ssh -i ~/rizqim-deploy-prod deploy@PROD_IP`.
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

## 5. GitHub: `production` environment, Variables

Repozitoriy sahifasi -> **Settings**. SSH sozlamalari **repozitoriy sirlari emas, `production` environment'ida** turadi: ularni
faqat environment himoyasidan (egasining tasdig'i, faqat `main`) o'tgan deploy ishi ko'radi. Boshqa branch'dagi o'zgartirilgan
workflow ularni o'qiy olmaydi.

### 5.1. Environment `production` (Settings -> **Environments** -> New environment -> `production`)

**Himoya qoidalari (shart):**
1. **Required reviewers**: yoqing va o'zingizni (egasini) qo'shing. Production deploy siz "Approve" bosmaguncha boshlanmaydi.
   Faqat siz bo'lsangiz, "Prevent self-review" ni o'chirib qo'ying, aks holda o'zingiz tasdiqlay olmaysiz.
   "Allow administrators to bypass configured protection rules" belgilanmagan bo'lsin.
2. **Deployment branches and tags**: *Selected branches and tags* -> **Add deployment branch or tag rule** -> turi *Branch*,
   nomi `main`. Teg qoidasi qo'shmang (deploy faqat `main` dan; workflow ham teg va boshqa branch'ni rad etadi).

**Environment secrets** (shu sahifada, *Environment secrets* -> Add environment secret). Nomlar aynan shunday:

| Nom | Qiymat | Majburiy |
|---|---|---|
| `SSH_KEY` | `~/rizqim-deploy-prod` faylining **butun matni** (`-----BEGIN OPENSSH PRIVATE KEY-----` dan `-----END ...` gacha) | ha |
| `SSH_KNOWN_HOSTS` | server kaliti (pastdagi buyruq natijasi, barmoq izi tekshirilgan) | **ha**: bo'lmasa deploy xato bilan to'xtaydi |

`SSH_KNOWN_HOSTS` ni olish (o'z kompyuteringizda), so'ng barmoq izini 3.2 da serverda ko'rganingiz bilan solishtiring:

```bash
ssh-keyscan -t ed25519 -p 22 PROD_IP > prod_known_hosts     # port 22 bo'lmasa -p da o'z portingiz
ssh-keygen -lf prod_known_hosts                             # SHA256:... 3.2 dagi bilan BIR XIL bo'lishi shart
cat prod_known_hosts                                        # shu matnni SSH_KNOWN_HOSTS ga qo'ying
```

Barmoq izi mos kelmasa, kiritmang (yo'lda boshqa server bo'lishi mumkin): provayder konsolida qayta tekshiring.
Server qayta o'rnatilsa (yangi host kaliti) shu qadamni takrorlang.

**Environment variables** (shu sahifada, *Environment variables* -> Add environment variable). Sir emas:

| Nom | Qiymat | Majburiy |
|---|---|---|
| `SSH_HOST` | `PROD_IP` (yoki host nomi) | ha |
| `SSH_USER` | `deploy` | ha |
| `SSH_PORT` | SSH porti; `22` bo'lsa kerak emas | yo'q |

Eski sozlama: repozitoriy darajasida `PROD_SSH_*` yoki `STAGING_SSH_*` sirlari qo'shilgan bo'lsa (Settings -> Secrets and
variables -> Actions -> *Repository secrets*), ularni **o'chiring**: workflow endi ularni o'qimaydi.

### 5.2. Repozitoriy Variables (Settings -> Secrets and variables -> Actions -> *Variables* -> *Repository variables*)

| Nom | Qiymat | Izoh |
|---|---|---|
| `PROD_DEPLOY_ENABLED` | `true` | **Majburiy.** Bo'lmasa production deploy toza o'tkazib yuboriladi (xato emas). Server tayyor bo'lganda qo'ying |
| `PROD_VITE_BOT_USERNAME` | `rizqimbot` (standart) | haqiqiy bot (`@` siz) |
| `PROD_VITE_PARTNER_WEB_URL` | `https://business.rizqim.uz` (standart) | |
| `PROD_VITE_MAP_TILE_URL`, `PROD_VITE_MAP_ATTRIBUTION` | bo'sh | production'da xarita provayderi (ochiq OSM faqat sinov uchun) |
| `PROD_VITE_SENTRY_DSN` | bo'sh | Sentry bo'lsa |

`PROD_VITE_*` sir emas (Mini App ichiga yoziladi, brauzerda ko'rinadi) va ixtiyoriy: berilmasa standart ishlaydi. Ular
repozitoriy darajasida turadi, chunki obraz yig'ish ishi environment'dan oldin bajariladi.

### 5.3. GHCR paketlari PRIVATE bo'lsin

GHCR uchun alohida sir kerak emas: workflow `GITHUB_TOKEN` ni o'zi ishlatadi. Obrazlarda manba kodi bor (backend va boshqalar),
shuning uchun birinchi deploy'dan keyin tekshiring: GitHub -> profilingiz (yoki tashkilot) -> **Packages** -> har bir `rizqim...`
paketi -> *Package settings* -> **Danger Zone** -> *Visibility* = **Private**. Public bo'lsa: *Change visibility* -> Private.
Shu sahifada *Manage Actions access* da repozitoriy "Read" (yoki "Write") bilan bo'lsin.

## 6. Birinchi marta: fayllarni yuklash va `.env` yaratish

Server hali bo'sh: `.env` fayllari va compose fayllari yo'q. Fayllar to'plamini GitHub yuklaydi (5-bo'lim bajarilgan bo'lsin):

1. GitHub -> Actions -> **Deploy** -> *Run workflow* -> branch `main`, `target = production`, **`only_upload` belgilang** -> Run.
   "Review deployments" -> **Approve and deploy**. (Obraz yig'ilmaydi va deploy qilinmaydi: fayllar `/opt/rizqim` ga tushadi.
   Bu faqat birinchi marta ishlaydi: reliz joylangan serverda `only_upload` rad etiladi.)
2. Serverda:

```bash
ssh deploy@PROD_IP
cd /opt/rizqim
cp .env.production.example .env
cp backend/.env.production.example backend/.env
chmod 600 .env backend/.env
mkdir -p pos-files backups
nano .env backend/.env          # quyidagi jadval bo'yicha to'ldiring
```

**To'ldiriladigan qiymatlar** (qolgani namunada tayyor, domenlar ham: `rizqim.uz`):

| Fayl | Kalit | Qanday olinadi |
|---|---|---|
| `.env` | `DB_PASSWORD` | `openssl rand -hex 24` (`$` belgisi bo'lmasin) |
| `.env` | `REDIS_PASSWORD` | `openssl rand -hex 24`. **Shart**: bo'sh yoki `CHANGE_ME...` bo'lsa deploy ishga tushmaydi |
| `.env` | `EDGE_ACME_EMAIL` | sizning e-pochtangiz (Let's Encrypt sertifikat muddati haqida yozadi) |
| `.env` | `PITR_CIPHER_PASS` | `openssl rand -base64 48`; **server tashqarisida (parol menejerida) saqlang**: yo'qolsa S3 dagi zaxiralar o'qilmaydi |
| `.env` | `BACKUP_S3_*`, `HEALTHCHECKS_PING_URL`, `PITR_PING_URL` | 8 va 9-bo'limlar (pilotdan oldin tavsiya) |
| `backend/.env` | `APP_KEY` | `echo "base64:$(openssl rand -base64 32)"`; nusxasi parol menejerida |
| `backend/.env` | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME` | BotFather (haqiqiy bot) |
| `backend/.env` | `TELEGRAM_WEBHOOK_SECRET` | `openssl rand -hex 32` |
| `backend/.env` | `CLICK_*`, `PAYME_*` | haqiqiy merchant kalitlari (sinab ko'rish davrida sandbox ham bo'ladi) |
| `backend/.env` | `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` | ixtiyoriy (birinchi superadmin odatda `rizqim:admin:create` bilan, 7-qadam); parol kamida 12 belgi |
| `backend/.env` | `SENTRY_LARAVEL_DSN`, `TELEGRAM_OPS_CHAT_ID`, `HEALTH_TOKEN`, `NOMINATIM_EMAIL` | ixtiyoriy, ma'nosi `backend/.env.example` da |

Reverb kalitlari (`REVERB_APP_*`) bo'sh qoladi: backend birinchi ishga tushishda o'zi yaratadi (DEPLOY.md, 9-bo'lim).

### Domen qiymatlari (namunada tayyor, tekshirish uchun)

| Qiymat | Production |
|---|---|
| `EDGE_DOMAIN`, `EDGE_PROFILE` (`.env`) | `rizqim.uz`, `production` |
| `APP_URL` (`backend/.env`) | `https://api.rizqim.uz` |
| `TELEGRAM_WEBAPP_URL` | `https://app.rizqim.uz` |
| Telegram webhook (`TELEGRAM_WEBHOOK_URL` bo'sh = `APP_URL` + yo'l) | `https://api.rizqim.uz/api/v1/telegram/webhook` |
| `PARTNER_WEB_URL` | `https://business.rizqim.uz` |
| `ADMIN_APP_URL` | `https://admin.rizqim.uz` |
| `CORS_ALLOWED_ORIGINS` | `https://business.rizqim.uz,https://app.rizqim.uz` |
| `REVERB_ALLOWED_ORIGINS` (ikkala faylda bir xil) | `app.rizqim.uz,business.rizqim.uz,api.rizqim.uz` |
| `PARTNER_PUBLIC_API_BASE_URL` (`.env`) | `https://api.rizqim.uz/api` |
| `VITE_API_URL` (Mini App) | `/api/v1` (nisbiy) |
| `VITE_PARTNER_WEB_URL` | `https://business.rizqim.uz` |
| `VITE_REVERB_HOST` | bo'sh (Mini App domeni) |
| `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS` | `null` / o'rnatilmaydi (API Bearer token bilan ishlaydi, cookie-sessiya yo'q) |
| Click/Payme webhook manzillari (merchant kabineti) | `https://api.rizqim.uz/api/v1/payments/click/webhook` va `.../payme/webhook` |

`VITE_*` qiymatlari avtomatik deploy'da serverdagi `.env` dan emas, GitHub *Variables*'dan olinadi (5.2).

### 6.1. admin.rizqim.uz ni IP bilan cheklash (tavsiya)

Boshqaruv markazi parol va 2FA bilan himoyalangan; ofis/uy IP'si aniq bo'lsa qo'shimcha qulf qo'ying. Admin API'ning o'zi
(`/api/v1/admin/*`) `api.rizqim.uz` va `app.rizqim.uz` orqali baribir **yopiq** (404): unga faqat `admin.rizqim.uz` orqali,
ya'ni shu IP ro'yxatidan o'tib boriladi.

```bash
cd /opt/rizqim
cp infra/edge/admin/allow.caddy.example infra/edge/admin/allow.caddy
nano infra/edge/admin/allow.caddy          # sizning IP/CIDR manzillaringiz (o'zingizniki bo'lsin!)
echo 'EDGE_ADMIN_ACCESS=/etc/caddy/admin/allow.caddy' >> .env
docker compose up -d edge
```

- Fayl `infra/edge/admin/` da bo'lsin, **`conf.d/` da emas** (u yerdagi fayllar Caddyfile'ning eng yuqori darajasida o'qiladi
  va ro'yxat u yerda bo'lsa Caddy umuman ishga tushmaydi).
- O'z IP'ingiz: `curl -4 https://ifconfig.co` (IPv4). Telefon yoki uy internetidan IPv6 bilan chiqsangiz `curl -6 https://ifconfig.co`
  ni ham ro'yxatga yozing. Pilotda DNS'da AAAA yo'q (2-bo'lim), shuning uchun odatda IPv4 yetadi.
- `172.16.0.0/12`, `10.0.0.0/8`, `0.0.0.0/0` kabi keng diapazonlarni yozmang.
- Ro'yxatni keyin o'zgartirsangiz: `docker compose exec -T edge caddy reload --config /etc/caddy/Caddyfile`. Fayl serverda qoladi:
  deploy unga tegmaydi.

Qaytarish: `.env` dan `EDGE_ADMIN_ACCESS` qatorini o'chirib `docker compose up -d edge`.

### 6.2. rizqim.com va rizq.im ni asosiyga yo'naltirish (ixtiyoriy)

```bash
cp infra/edge/conf.d/aliases.caddy.example infra/edge/conf.d/aliases.caddy
docker compose exec -T edge caddy reload --config /etc/caddy/Caddyfile
```

Ikkala domenning DNS yozuvlari (2-bo'lim) shu serverga qaragan bo'lishi kerak. `conf.d/` ga faqat shunday to'liq sayt bloklari qo'yiladi.

## 7. Birinchi deploy (production)

### 7.1. Deploy

1. DNS tayyorligini tekshiring (2-bo'lim). 5-bo'lim (environment, Variables) va 6-bo'lim (`.env` fayllar) bajarilgan.
2. Actions -> **Deploy** -> *Run workflow* -> branch **`main`** -> `target = production` (`only_upload` belgilanmagan) -> Run.
3. Workflow "Deploy to production" ishida to'xtaydi: **Review deployments** -> *production* ni belgilab **Approve and deploy**.
   (Tasdiqlovchi: siz.) Obrazlar shu paytgacha yig'ilgan bo'ladi (birinchi marta ~15-25 daqiqa, keyin kesh bilan tezroq).
4. Serverda `deploy.sh`: tekshiruvlar, obrazlarni tortish, **zaxira** (`backup.sh`; birinchi deploy'da baza hali yo'q, shuning
   uchun o'tkazib yuboriladi). Keyingi deploy'larda `backup` xizmati ishlamasa yoki zaxira xato bersa deploy **rad etiladi** va
   hech narsa o'zgarmaydi. Birinchi ishga tushishda backend migratsiyalarni o'tkazadi (~2 daqiqa). Sertifikatlar birinchi
   so'rovda olinadi (1-2 daqiqa).
5. Tekshirish:

```bash
curl -fsS https://api.rizqim.uz/up
curl -I https://app.rizqim.uz/                               # strict-transport-security bo'lishi kerak
curl -s -o /dev/null -w '%{http_code}\n' https://api.rizqim.uz/api/v1/admin/auth/me   # 404: admin API tashqaridan yopiq
ssh deploy@PROD_IP 'cd /opt/rizqim && docker compose ps'     # hamma xizmat healthy
ssh deploy@PROD_IP 'cd /opt/rizqim && infra/deploy/deploy.sh status'
```

6. Birinchi superadmin va hududlar (DEPLOY.md, 5-bo'lim), serverda `/opt/rizqim` da:

```bash
docker compose exec -u www-data backend php artisan rizqim:admin:create boss@rizqim.uz "Bosh admin"
docker compose exec -u www-data backend php artisan db:seed --class=LocationSeeder --force
```

Chiqqan taklif havolasi (`https://admin.rizqim.uz/invite/...`) orqali parol va 2FA o'rnatiladi.

### 7.2. Keyingi qadamlar

1. Saytlar: `https://rizqim.uz/`, `https://app.rizqim.uz/`, `https://api.rizqim.uz/up`, `https://admin.rizqim.uz/login`.
2. GHCR paketlari Private ekanini tekshiring (5.3).
3. 8-bo'lim (zaxira) va 9-bo'lim (monitoring).
4. Telegram: BotFather'da botning Mini App manzili `https://app.rizqim.uz`.
5. DEPLOY.md, 10-bo'limdagi tekshiruv ro'yxatini (staging uchun yozilgan) hozircha production'da o'zingiz, haqiqiy restoranlar
   ulanishidan oldin o'tkazing.
6. Webhook'ni deploy o'zi o'rnatadi. Holatini tekshirish (serverda; token konteyner ichidan o'qiladi, buyruq satriga va shell
   tarixiga tushmaydi):

```bash
cd /opt/rizqim
docker compose exec -T -u www-data backend php -r 'echo file_get_contents("https://api.telegram.org/bot".getenv("TELEGRAM_BOT_TOKEN")."/getWebhookInfo"), PHP_EOL;'
```

Javobda `url` = `https://api.rizqim.uz/api/v1/telegram/webhook` va `last_error_message` bo'lmasligi kerak. Tokenni `curl` ga
o'zingiz yozmang.

### 7.3. Orqaga qaytarish

- **Avtomatik:** deploy paytida biror xizmat sog'lom bo'lmasa, Caddyfile xato bo'lsa (`caddy reload` rad etsa), `/health` yoki
  migratsiya xato bersa, skript oxirgi yaxshi versiyaga qaytadi: **uning fayllari** (compose, `scripts/`, Caddyfile) va
  **obrazlari** bilan, keyin `caddy reload`. Workflow **qizil** bo'ladi. Birinchi deploy'da qaytariladigan versiya yo'q: loglarni ko'ring.
- **Qo'lda:** `ssh deploy@PROD_IP 'cd /opt/rizqim && infra/deploy/deploy.sh rollback'` (avvalgi versiyaga). Yana bir marta
  ishlatsangiz oldingi versiyaga qaytadi.
- **Eski versiyani qayta joylash:** `main` ni kerakli commit'ga qaytaring (revert commit) va oddiy deploy qiling. Teg yoki boshqa
  branch'dan production deploy qilinmaydi.
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
   `ARCHIVE_S3_*`. Keyinroq staging bucket'iga production kalitini bermang.
2. `PITR_ENABLED=1` (namunada yoqilgan) va `PITR_CIPHER_PASS` parol menejerida.
3. Birinchi deploydan keyin **tiklash mashqini** o'tkazing: `scripts/restore-drill.sh` (OPERATIONS.md, 19-bo'lim) va tiklash vaqtini yozib qo'ying.
   Tiklab ko'rilmagan zaxira zaxira emas.
4. Provayderning server snapshot'ini yoqing (1-bo'lim): bu qo'shimcha qatlam.
5. Tekshirish: `ls /opt/rizqim/backups` da yangi `rizqim-*.dump`; PITR holati: `scripts/pitr-restore.sh --info` (OPERATIONS.md, 17-bo'lim).

`backup` xizmati doim ishlab tursin: production deploy undan zaxira oladi va u to'xtagan bo'lsa deploy rad etiladi
(`docker compose up -d backup`, sababi: `docker compose logs --tail=50 backup`).

## 9. Monitoring

Hammasi bepul darajada boshlanadi (OPERATIONS.md, 21-bo'lim):

| Nima | Qanday |
|---|---|
| Sayt ishlayaptimi | UptimeRobot (yoki shunga o'xshash): `https://api.rizqim.uz/api/v1/health`, `https://rizqim.uz/`, `https://app.rizqim.uz/`; Telegram yoki e-pochta orqali ogohlantirish |
| Server o'chib qolsa | healthchecks.io: ping manzilini `.env` dagi `HEALTHCHECKS_PING_URL` ga (scheduler har daqiqa yuboradi) va `PITR_PING_URL` ga yozing |
| Ichki ogohlantirishlar (baza, navbat, Reverb, disk) | `backend/.env` da `TELEGRAM_OPS_CHAT_ID` (botni guruhga qo'shing); boshqaruv markazi -> Monitoring -> Holat |
| Xatolar | Sentry: `SENTRY_LARAVEL_DSN` (`backend/.env`) va `PROD_VITE_SENTRY_DSN` (GitHub Variables) |
| Sertifikat muddati | Caddy o'zi yangilaydi; backend `MONITORING_TLS_HOST` ni tekshiradi (boshqaruv markazida ogohlantirish) |
| Disk | provayder panelida disk 80 % ogohlantirishi; serverda `df -h`, `docker system df` |

## 10. Kundalik buyruqlar (serverda, `/opt/rizqim` da)

```bash
docker compose ps                              # holat
docker compose logs -f --tail=100 backend      # backend logi (edge, queue, scheduler, reverb ham shunday)
docker compose logs --tail=100 edge            # sertifikat va marshrutlash xatolari
infra/deploy/deploy.sh status                  # joriy va avvalgi versiya, reliz papkalari
infra/deploy/deploy.sh rollback                # avvalgisiga qaytarish (fayllari va obrazlari bilan)
docker compose exec -T edge caddy reload --config /etc/caddy/Caddyfile   # infra/edge dagi o'zgarishni qo'llash
docker compose exec -u www-data backend php artisan <buyruq>     # ops buyruqlari (DEPLOY.md, 8-bo'lim)
docker image prune -f                          # keraksiz obrazlar (deploy buni o'zi qiladi)
```

Reliz fayllari `.deploy/releases/<tag>/` da turadi (joriy, avvalgi va yana 3 tasi; eskilari o'zi o'chadi). Ishlayotgan
fayllarni (`docker-compose*.yml`, `scripts/`, `infra/`) qo'lda tahrirlamang: keyingi deploy ularni relizdagi bilan almashtiradi.
Serverga xos fayllar (`.env`, `backend/.env`, `infra/edge/admin/*.caddy`, `infra/edge/conf.d/*.caddy`, `backups/`, `pos-files/`)
deploy'da hech qachon o'zgartirilmaydi.

Qo'lda `docker compose pull` qilsangiz GHCR'ga kirish kerak bo'ladi (deploy tokeni faqat o'sha deploy davomida, vaqtinchalik
papkada turadi va deploy tugashi bilan o'chiriladi): yangi versiyalarni doim workflow orqali joylang.

## 11. Muammolar

| Alomat | Sabab va yechim |
|---|---|
| Workflow "skipped" (kulrang), deploy bo'lmadi | repozitoriy Variable `PROD_DEPLOY_ENABLED` = `true` emas (5.2). Xato emas. `main` ga push: staging yoqilmagan (13-bo'lim), bu normal |
| `deploy faqat main branch'dan` | Run workflow'da branch `main` ni tanlang (teg ham rad etiladi) |
| `production environment'ida yetishmaydi: ... SSH_KNOWN_HOSTS` | 5.1: environment'da `SSH_HOST`, `SSH_USER` (variables), `SSH_KEY`, `SSH_KNOWN_HOSTS` (secrets) bo'lishi shart |
| Deploy "Waiting" da turibdi | Review deployments -> Approve (5.1). Branch `main` emas bo'lsa environment uni umuman o'tkazmaydi |
| `Permission denied (publickey)` | maxfiy kalit to'liq kiritilmagan (BEGIN/END qatorlari bilan) yoki ochiq kalit serverdagi `authorized_keys` da yo'q |
| `Host key verification failed` | `SSH_KNOWN_HOSTS` noto'g'ri yoki server qayta o'rnatilgan: 5.1 dagi `ssh-keyscan` va barmoq izi tekshiruvini qaytadan |
| `deploy.sh`: `.env yo'q` / `DB_PASSWORD to'ldirilmagan` / `REDIS_PASSWORD to'ldirilmagan` / `EDGE_ACME_EMAIL bo'sh` | 6-bo'lim: `.env` fayllarni to'ldiring |
| `deploy.sh`: `backup xizmati ishlamayapti ..., zaxirasiz deploy qilinmaydi` | `docker compose up -d backup`, sababi `docker compose logs --tail=50 backup`; keyin deploy'ni qayta ishga tushiring. Hech narsa o'zgarmagan |
| `deploy.sh`: `reliz 'staging' uchun, bu serverning .env i 'production'` | noto'g'ri server yoki `.env` da `DEPLOY_TARGET` xato |
| `only_upload`: `reliz allaqachon joylangan` | `only_upload` faqat birinchi marta; keyin oddiy deploy fayllarni o'zi yangilaydi |
| Obrazlarni tortib bo'lmadi (`denied`) | GHCR paketi repozitoriyga bog'lanmagan: GitHub -> Packages -> paket -> *Package settings* -> *Manage Actions access* -> repozitoriyni "Read" bilan qo'shing; yoki paket egasi boshqa |
| `caddy reload muvaffaqiyatsiz` (deploy qaytarildi) | yangi Caddyfile yoki `infra/edge/admin`/`conf.d` dagi fayl xato: `docker compose logs --tail=50 edge`. Eski sozlama ishlashda davom etgan |
| `https://...` ochilmaydi, brauzer sertifikat xatosi | DNS hali serverga qaramaydi yoki 80/443 yopiq. `docker compose logs edge` da ACME xatosi ko'rinadi. Caddy o'zi qayta urinadi; Let's Encrypt xato sonini cheklaydi (soatiga 5 ta): DNS ni tuzatib bir necha daqiqa kuting |
| 502 Bad Gateway | xizmat qayta ishga tushyapti (deploy paytida normal) yoki yiqilgan: `docker compose ps`, `docker compose logs backend` |
| Deploy qizil, "orqaga qaytarildi" | yangi versiyada muammo; log: workflow'dagi "Deploy" qadami va `docker compose logs --tail=100 backend` |
| Admin sahifasi 403 "Forbidden" | `infra/edge/admin/allow.caddy` da sizning IP'ingiz yo'q (6.1; IPv6 bilan kirayotgan bo'lishingiz mumkin); ro'yxatni yangilab `caddy reload` |
| Edge ishga tushmaydi: `request matchers may not be defined globally` | IP ro'yxati `conf.d/` ga qo'yilgan: uni `infra/edge/admin/allow.caddy` ga ko'chiring (6.1) |

## 12. Egasi beradigan narsalar (qisqa ro'yxat)

1. Bitta VPS (production) va uning IP manzili.
2. DNS yozuvlarini qo'yish huquqi (2-bo'lim, faqat production yozuvlari).
3. GitHub repozitoriyda *Admin* huquqi: `production` environment (Required reviewers, faqat `main`, `SSH_*` sozlamalari) va
   repozitoriy Variables (`PROD_DEPLOY_ENABLED`, `PROD_VITE_*`).
4. SSH kaliti (o'z kompyuteringizda yaratiladi, maxfiy qismi faqat `production` environment siriga).
5. Telegram: haqiqiy bot (BotFather), token faqat serverdagi `backend/.env` ga.
6. Click/Payme kalitlari.
7. S3-mos saqlash (zaxira) va uning kalitlari; healthchecks.io / UptimeRobot hisoblari; (ixtiyoriy) Sentry.
8. `EDGE_ACME_EMAIL` uchun e-pochta.

## 13. Staging (keyinroq, ixtiyoriy)

Haqiqiy restoranlar ulanishidan oldin alohida staging server qo'shiladi (egasi qarori). Ungacha bu bo'lim kerak emas. Staging
production bilan bir xil stek, faqat `docker-compose.staging.yml` qo'shiladi (DEPLOY.md, 10-bo'lim). Staging va production
bir-biridan butunlay ajratilgan: boshqa server, boshqa baza, boshqa bot, boshqa kalitlar.

1. **Server:** 2 vCPU, 4 GB RAM, 50 GB disk (funksional sinov uchun; yuk sinovi uchun vaqtincha kattaroq server alohida qaror).
   Nomi `rizqim-staging`, IP: `STG_IP`. 3 va 4-bo'limlarni `STG_IP` bilan takrorlang, **alohida** kalit bilan:
   `ssh-keygen -t ed25519 -N "" -C "rizqim-deploy-staging" -f ~/rizqim-deploy-staging`.
2. **DNS:** `stg` va `*.stg` -> `STG_IP` (yulduzcha qo'llab-quvvatlanmasa alohida: `stg`, `www.stg`, `app.stg`, `api.stg`,
   `business.stg`, `admin.stg`, `pos.stg`, `links.stg`). AAAA yo'q (2-bo'limdagi sabab). Tekshirish:
   `dig +short stg.rizqim.uz app.stg.rizqim.uz api.stg.rizqim.uz`.
3. **GitHub environment `staging`** (Settings -> Environments -> New environment -> `staging`): Deployment branches -> faqat `main`.
   Reviewer shart emas. Nomlar production'dagi bilan **bir xil**, qiymatlari staging'niki:
   - Environment secrets: `SSH_KEY` (`~/rizqim-deploy-staging` matni), `SSH_KNOWN_HOSTS` (`ssh-keyscan -t ed25519 -p 22 STG_IP`, barmoq izi tekshirilgan)
   - Environment variables: `SSH_HOST` (`STG_IP`), `SSH_USER` (`deploy`), `SSH_PORT` (ixtiyoriy)
4. **Repozitoriy Variables:** `STAGING_DEPLOY_ENABLED` = `true`; ixtiyoriy `STAGING_VITE_BOT_USERNAME` (standart
   `rizqim_staging_bot`, TEST bot), `STAGING_VITE_PARTNER_WEB_URL` (standart `https://business.stg.rizqim.uz`),
   `STAGING_VITE_MAP_TILE_URL`, `STAGING_VITE_MAP_ATTRIBUTION`, `STAGING_VITE_SENTRY_DSN`.
5. **Birinchi yuklash:** Actions -> Deploy -> Run workflow -> `main`, `target = staging`, `only_upload` -> Run. Serverda:
   `cp .env.staging.example .env`, `cp backend/.env.staging.example backend/.env`, `chmod 600 .env backend/.env`,
   `mkdir -p pos-files backups`, 6-bo'lim jadvali bo'yicha to'ldiring. Farqlar: **alohida TEST bot** (haqiqiy botni staging'ga
   bermang: webhook bitta botga bitta manzil), Click/Payme faqat sandbox, `APP_KEY` va parollar production'dan boshqa,
   domenlar `stg.rizqim.uz` (`EDGE_DOMAIN=stg.rizqim.uz`, `EDGE_PROFILE=staging`, `APP_URL=https://api.stg.rizqim.uz`, va hokazo:
   namunada tayyor). IP ro'yxati kerak bo'lsa: 6.1 (`admin.stg.rizqim.uz`).
6. **Deploy:** Actions -> Deploy -> Run workflow -> `main`, `target = staging` (yoki `main` ga push: endi har push staging'ga
   o'zi joylanadi). Tekshirish: `curl -fsS https://api.stg.rizqim.uz/up`; `curl -I https://app.stg.rizqim.uz/`
   (`x-robots-tag: noindex` bo'lishi kerak); birinchi superadmin 7.1, 6-qadamdagidek. Keyin DEPLOY.md, 10-bo'limdagi
   «Staging'da tekshiruv ro'yxati».
7. Shundan keyin tartib: har o'zgarish avval staging'da (push), so'ng production'ga qo'lda (7.1).
