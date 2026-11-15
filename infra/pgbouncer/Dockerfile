# `pgbouncer` xizmati obrazi (docker-compose.yml): PHP-FPM va Postgres orasidagi ulanishlar puli (tranzaksiya rejimi).
# postgres:17-alpine asosida (postgres va backup xizmatlari bilan bir xil obraz, qatlamlar xostda allaqachon bor):
# pg_isready/psql healthcheck uchun tayyor, pgbouncer esa Alpine community paketidan. Sozlama faylini konteyner
# ishga tushganda entrypoint.sh muhit o'zgaruvchilaridan yozadi (parol obrazga kirmaydi). docs/DEPLOY.md, docs/OPERATIONS.md
FROM postgres:17-alpine
# hadolint ignore=DL3018
RUN apk add --no-cache pgbouncer && pgbouncer --version
COPY entrypoint.sh /usr/local/bin/pgbouncer-entrypoint.sh
RUN chmod 755 /usr/local/bin/pgbouncer-entrypoint.sh
# The image's own non-root user (PgBouncer refuses to run as root)
USER postgres
EXPOSE 6432
ENTRYPOINT ["/usr/local/bin/pgbouncer-entrypoint.sh"]
