# rizqim.uz: landing va "Hamkor bo'lish" sahifasi (statik, build qadamisiz). docs/OPERATIONS.md, "rizqim.uz landing"
# Sahifa formasi faqat POST /api/v1/store-applications va GET /api/v1/locations ni backend'ga uzatadi (nginx.conf).
FROM nginx:1.27-alpine
COPY security-headers.conf /etc/nginx/security-headers.inc
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY site/ /usr/share/nginx/html/

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
