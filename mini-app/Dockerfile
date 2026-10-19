# Node 22 LTS, as CI: the locked vitest 5 needs Node ^22.12 (npm ci warns on Node 20)
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
# Sentry: bo'sh DSN = o'chiq (SDK yuklanmaydi ham). docs/OPERATIONS.md
ARG VITE_SENTRY_DSN=
ARG VITE_SENTRY_ENVIRONMENT=production
ARG VITE_SENTRY_RELEASE=
ARG VITE_SENTRY_TRACES_SAMPLE_RATE=0
# Reverb (real-time buyurtma holatlari): REVERB_APP_KEY bilan bir xil kalit; bo'sh kalit = real-time o'chiq (ilova
# so'rov bilan yangilanadi). Bo'sh host = Mini App ochilgan domen; WebSocket shu origin'dagi /app/ orqali (docker/nginx.conf)
ARG VITE_REVERB_APP_KEY=
ARG VITE_REVERB_HOST=
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_BOT_USERNAME=$VITE_BOT_USERNAME
ENV VITE_API_URL=$VITE_API_URL
ENV VITE_MAP_TILE_URL="$VITE_MAP_TILE_URL"
ENV VITE_MAP_ATTRIBUTION="$VITE_MAP_ATTRIBUTION"
ENV VITE_SENTRY_DSN="$VITE_SENTRY_DSN"
ENV VITE_SENTRY_ENVIRONMENT="$VITE_SENTRY_ENVIRONMENT"
ENV VITE_SENTRY_RELEASE="$VITE_SENTRY_RELEASE"
ENV VITE_SENTRY_TRACES_SAMPLE_RATE="$VITE_SENTRY_TRACES_SAMPLE_RATE"
ENV VITE_REVERB_APP_KEY="$VITE_REVERB_APP_KEY"
ENV VITE_REVERB_HOST="$VITE_REVERB_HOST"
ENV VITE_REVERB_PORT="$VITE_REVERB_PORT"
ENV VITE_REVERB_SCHEME="$VITE_REVERB_SCHEME"

RUN npm run build

FROM nginx:1.27-alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
