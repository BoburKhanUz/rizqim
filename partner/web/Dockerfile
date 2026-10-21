# Hamkor veb-panel (Next.js, standalone output). The build context is the REPOSITORY ROOT, because the app links two
# packages from packages/ts/ (package.json: file: dependencies). Which files enter the context: Dockerfile.dockerignore.
#   docker build -f partner/web/Dockerfile -t rizqim-partner-web .
# Served by docker-compose.yml as the `partner-web` service (P1-36; host, CORS origin and env: docs/DEPLOY.md, section 4).
# Runtime configuration (no rebuild per environment): API_BASE_URL, PUBLIC_API_BASE_URL, SESSION_COOKIE_SECURE (.env.example).

# Node 22 LTS, as CI
FROM node:22-alpine AS build
WORKDIR /app

# The shared UI package ships TypeScript source: nothing to build
COPY packages/ts/ui /app/packages/ts/ui

# The API client is compiled to dist/ (its package.json "main"), then the app is built against it
COPY packages/ts/api-client/package.json packages/ts/api-client/package-lock.json /app/packages/ts/api-client/
WORKDIR /app/packages/ts/api-client
RUN npm ci
COPY packages/ts/api-client /app/packages/ts/api-client
RUN npm run build

WORKDIR /app/partner/web
COPY partner/web/package.json partner/web/package-lock.json partner/web/.npmrc ./
RUN npm ci
COPY partner/web ./
ENV NEXT_TELEMETRY_DISABLED=1
RUN npm run build

FROM node:22-alpine AS runtime
WORKDIR /app
ENV NODE_ENV=production \
    NEXT_TELEMETRY_DISABLED=1 \
    PORT=3000 \
    HOSTNAME=0.0.0.0

# .next/standalone mirrors the repository layout below the tracing root (next.config.ts outputFileTracingRoot)
COPY --from=build --chown=node:node /app/partner/web/.next/standalone ./
COPY --from=build --chown=node:node /app/partner/web/.next/static ./partner/web/.next/static
COPY --from=build --chown=node:node /app/partner/web/public ./partner/web/public

USER node
EXPOSE 3000
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD wget -q -O /dev/null http://127.0.0.1:3000/login || exit 1
CMD ["node", "partner/web/server.js"]
