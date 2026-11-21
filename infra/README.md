# infra

Infratuzilma: docker, nginx va deploy fayllari.
Hozircha `docker-compose.yml` ildizda, `backend/docker/` va `customer/miniapp/docker/` joyida turibdi; keyinroq shu papkaga ko'chiriladi.
Stek: Docker Compose (docs/DEPLOY.md).

Hozirgi tarkib:
- `staging/` — staging uchun fayllar (`docker-compose.staging.yml` ildizda): `robots.txt`, nginx `X-Robots-Tag` qismi. docs/DEPLOY.md, 10-bo'lim.
- `backup/` — `backup` xizmati obrazi (postgres:17-alpine + rclone, S3'ga nusxa; M3: + pgBackRest, tiklash va tiklash mashqi). docs/OPERATIONS.md, 4-bo'lim.
- `postgres/` — `postgres` xizmati obrazi (M3): rasmiy postgres:17-alpine + pgBackRest (uzluksiz zaxira, PITR).
  `archive.sh` (obrazda `pgbackrest-archive.sh`) — Postgres'ning archive_command'i: `PITR_ENABLED=1` bo'lmasa hech narsa
  qilmaydi; `pitr-env.sh` — `PITR_*` kalitlaridan pgBackRest sozlamasi (ikkala obrazda bir xil fayl).
  `backup` obrazi ham shu yerdan quriladi (build context `./infra`). docs/OPERATIONS.md, «Uzluksiz zaxira (PITR)».
- `ci/` — faqat CI uchun: `docker-compose.pitr.yml` (PITR tekshiruvi, MinIO bilan; `scripts/pitr-ci.sh`). Serverda ishlatmang.
- `edge/` — edge proksi (Caddy, avtomatik TLS; `docker-compose.edge.yml`): `Caddyfile` (rizqim.uz, app., api., business., admin., pos., links. va staging uchun `stg.` ostida bir xil fayl), `links/` (vaqtincha sahifa), `conf.d/` (serverga xos qismlar: IP ro'yxati, qo'shimcha domenlar; `*.caddy` git'da emas), `staging/robots.txt`. docs/DEPLOY.md, 11-bo'lim.
- `deploy/` — serverdagi deploy skripti `deploy.sh` (GHCR'dan tortish, sog'liq tekshiruvi, avtomatik orqaga qaytarish; `docker-compose.images.yml` bilan). GitHub Actions: `.github/workflows/deploy.yml`. docs/SERVER_SETUP.md.
