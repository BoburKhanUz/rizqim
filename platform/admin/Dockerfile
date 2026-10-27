# RIZQIM boshqaruv markazi (platform/admin, Next.js, standalone output). The build context is the REPOSITORY ROOT, because the app
# links two packages from packages/ts/ (package.json: file: dependencies). Which files enter the context: Dockerfile.dockerignore.
#   docker build -f platform/admin/Dockerfile -t rizqim-platform-admin .
# Served by docker-compose.yml as the `platform-admin` service (P4-12; host, env and the reverse proxy: docs/DEPLOY.md, section 4a).
# Runtime configuration (no rebuild per environment): API_BASE_URL, SESSION_COOKIE_SECURE (.env.example).

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

WORKDIR /app/platform/admin
COPY platform/admin/package.json platform/admin/package-lock.json platform/admin/.npmrc ./
RUN npm ci
COPY platform/admin ./
ENV NEXT_TELEMETRY_DISABLED=1
RUN npm run build

FROM node:22-alpine AS runtime
WORKDIR /app
ENV NODE_ENV=production \
    NEXT_TELEMETRY_DISABLED=1 \
    PORT=3000 \
    HOSTNAME=0.0.0.0

# .next/standalone mirrors the repository layout below the tracing root (next.config.ts outputFileTracingRoot)
COPY --from=build --chown=node:node /app/platform/admin/.next/standalone ./
COPY --from=build --chown=node:node /app/platform/admin/.next/static ./platform/admin/.next/static
COPY --from=build --chown=node:node /app/platform/admin/public ./platform/admin/public

USER node
EXPOSE 3000
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD wget -q -O /dev/null http://127.0.0.1:3000/login || exit 1
CMD ["node", "platform/admin/server.js"]
