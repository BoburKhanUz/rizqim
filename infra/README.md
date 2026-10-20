# infra

Infratuzilma: docker, nginx va deploy fayllari.
Hozircha `docker-compose.yml` ildizda, `backend/docker/` va `customer/miniapp/docker/` joyida turibdi; keyinroq shu papkaga ko'chiriladi.
Stek: Docker Compose (docs/DEPLOY.md).

Hozirgi tarkib:
- `staging/` — staging uchun fayllar (`docker-compose.staging.yml` ildizda): `robots.txt`, nginx `X-Robots-Tag` qismi. docs/DEPLOY.md, 10-bo'lim.
- `backup/` — `backup` xizmati obrazi (postgres:17-alpine + rclone, S3'ga nusxa). docs/OPERATIONS.md, 4-bo'lim.
