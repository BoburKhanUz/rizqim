// Where the stack started by scripts/e2e-stack.sh listens. The script exports these; the defaults are its defaults.
import { readFileSync } from 'node:fs'

const read = (name: string, fallback: string): string => process.env[name] || fallback

export const stack = {
  backend: read('E2E_BACKEND_URL', 'http://127.0.0.1:18000'),
  partnerWeb: read('E2E_PARTNER_URL', 'http://127.0.0.1:13000'),
  miniApp: read('E2E_MINIAPP_URL', 'http://127.0.0.1:14173'),
  // The control center (platform/admin) and the file with the invite link `php artisan rizqim:admin:create` printed
  admin: read('E2E_ADMIN_URL', 'http://127.0.0.1:13300'),
  adminInviteFile: read('E2E_ADMIN_INVITE_FILE', '.run/admin-invite.url'),
  // The backend's laravel.log: the SMS "driver" of the stack is `log`, so the sign-in codes are read from it
  logFile: read('E2E_LOG_FILE', '../../backend/storage/logs/laravel.log'),
  seedFile: read('E2E_SEED_FILE', '.run/seed.json'),
  // The Telegram bot token the backend validates the Mini App's initData with (the stack's own, made up)
  botToken: read('E2E_BOT_TOKEN', '123456:E2E-TEST-TOKEN'),
  botUsername: read('E2E_BOT_USERNAME', 'e2e_bot'),
}

export interface Seed {
  store: { id: number; uuid: string; name: string }
  branch: { id: number; name: string }
  owner: { id: number; phone: string; telegram_id: string }
  manager: { id: number; name: string; phone: string; telegram_id: string }
  cook: { id: number; name: string; phone: string; pin: string }
  customer: { id: number; name: string; phone: string; telegram_id: string }
  products: Array<{ name: string; variant_uuid: string; price: string }>
  terminal: { uuid: string; token: string }
}

// What backend/database/seeders/E2EStackSeeder.php wrote
export function loadSeed(): Seed {
  return JSON.parse(readFileSync(stack.seedFile, 'utf8')) as Seed
}
