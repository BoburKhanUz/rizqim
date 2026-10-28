// P4-14: the admin app's real-time client (platform.ops over Laravel Reverb). Pages use usePlatformOps.
export { AUTH_ENDPOINT, realtimeConfigFromEnv, type RealtimeConfig } from './config';
export { createPlatformOps, getPlatformOps, OPS_EVENTS, PLATFORM_OPS_CHANNEL, setPlatformOps, type EchoLike, type OpsStatus, type PlatformOps } from './platformOps';
export { OPS_POLL_MS, usePlatformOps, type PlatformOpsOptions } from './usePlatformOps';
