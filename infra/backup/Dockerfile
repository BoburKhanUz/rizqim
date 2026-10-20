# `backup` xizmati obrazi (docker-compose.yml): postgres bilan bir xil versiya (pg_dump server versiyasidan eski bo'lmasligi shart)
# + S3-mos xotiraga yuklash uchun rclone (bitta statik binar, aws-cli'dan ancha yengil). Binar rasmiy obrazdan olinadi:
# versiya qat'iy belgilangan, apk narxlariga bog'liq emas. S3 o'zgaruvchilari bo'sh bo'lsa rclone ishlatilmaydi.
FROM rclone/rclone:1.68.2 AS rclone

FROM postgres:17-alpine
RUN apk add --no-cache ca-certificates
COPY --from=rclone /usr/local/bin/rclone /usr/local/bin/rclone
RUN rclone version
