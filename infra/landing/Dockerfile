# rizqim.uz: landing va "Hamkor bo'lish" sahifasi (statik, build qadamisiz). docs/OPERATIONS.md, "rizqim.uz landing"
# Sahifa formasi faqat POST /api/v1/store-applications va GET /api/v1/locations ni backend'ga uzatadi (nginx.conf).
# Root bo'lmagan nginx (uid 101), 8080 port. Teg 2026-11 da Docker Hub API orqali tekshirilgan (1.30 stable, alpine 3.24).
FROM nginxinc/nginx-unprivileged:1.30.6-alpine3.24
# conf.d yozish mumkin (uid 101): staging'dagi 10-noindex.sh shu faylni sed -i bilan o'zgartiradi
COPY --chown=101:0 security-headers.conf /etc/nginx/security-headers.inc
COPY --chown=101:0 nginx.conf /etc/nginx/conf.d/default.conf
COPY --chown=101:0 site/ /usr/share/nginx/html/

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD wget -q -O /dev/null http://127.0.0.1:8080/healthz || exit 1
CMD ["nginx", "-g", "daemon off;"]
