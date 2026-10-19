# Node 22 LTS, as CI and scripts/setup-server.sh: the locked vitest 5 needs Node ^22.12 (npm ci warns on Node 20)
FROM node:22-alpine AS build
WORKDIR /app

COPY package.json package-lock.json ./
# puppeteer (devDependency of the manual test-browser.js script only) would download Chrome during install
RUN PUPPETEER_SKIP_DOWNLOAD=1 npm ci

COPY . .

ARG VITE_BOT_USERNAME=rizqimbot
ARG VITE_API_URL=/api/v1
# Bo'sh bo'lsa ilova OpenStreetMap'ning ochiq tile serverini ishlatadi (faqat ishlab chiqish va kam trafik uchun)
ARG VITE_MAP_TILE_URL=
ARG VITE_MAP_ATTRIBUTION=
ENV VITE_BOT_USERNAME=$VITE_BOT_USERNAME
ENV VITE_API_URL=$VITE_API_URL
ENV VITE_MAP_TILE_URL="$VITE_MAP_TILE_URL"
ENV VITE_MAP_ATTRIBUTION="$VITE_MAP_ATTRIBUTION"

RUN npm run build

FROM nginx:1.27-alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
