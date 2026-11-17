# `backup` xizmati obrazi (docker-compose.yml): postgres bilan bir xil versiya (pg_dump server versiyasidan eski bo'lmasligi shart)
# + S3-mos xotiraga yuklash uchun rclone (bitta statik binar, aws-cli'dan ancha yengil). Binar rasmiy obrazdan olinadi:
# versiya qat'iy belgilangan, apk narxlariga bog'liq emas. S3 o'zgaruvchilari bo'sh bo'lsa rclone ishlatilmaydi.
# M3: pgBackRest (PITR: to'liq/farq zaxiralari, tiklash, tiklash mashqi) infra/postgres/Dockerfile bilan AYNAN bir xil
# asos va paketlar: ikkala konteynerda pgBackRest versiyasi bir xil bo'lishi shart (CI tekshiradi).
# Build context is ./infra (docker-compose.yml) so the PITR helpers are copied from infra/postgres, the single source.
FROM rclone/rclone:1.68.2 AS rclone

FROM postgres:17-alpine
# Same pgBackRest line as infra/postgres/Dockerfile (see the comment there for the postgresql17 pin); ssl_client lets
# busybox wget reach https (PITR_PING_URL)
# hadolint ignore=DL3018
RUN apk add --no-cache ca-certificates ssl_client pgbackrest postgresql17 \
    && pgbackrest version \
    && test "$(command -v postgres)" = /usr/local/bin/postgres \
    && command -v gosu \
    && install -d -o postgres -g postgres -m 0750 /var/lib/pgbackrest /var/spool/pgbackrest \
    && install -d -o postgres -g postgres -m 0700 /tmp/pgbackrest-log /tmp/pgbackrest-spool \
    && install -d -o postgres -g postgres -m 0700 /restore
COPY --from=rclone /usr/local/bin/rclone /usr/local/bin/rclone
COPY postgres/pitr-env.sh /usr/local/bin/pitr-env.sh
COPY postgres/archive.sh /usr/local/bin/pgbackrest-archive.sh
RUN chmod 0644 /usr/local/bin/pitr-env.sh && chmod 0755 /usr/local/bin/pgbackrest-archive.sh && rclone version
