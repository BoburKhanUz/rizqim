# pos.rizqim.uz: POS ilovalarini yuklab olish sahifasi (statik, build qadamisiz) + /files/ (ilova fayllari, volume).
# Sahifa GET /api/v1/pos/releases ni o'qiydi (nginx uni backend'ga uzatadi). docs/OPERATIONS.md, "POS ilovalarini chiqarish"
FROM nginx:1.27-alpine
COPY site/ /usr/share/nginx/html/
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
