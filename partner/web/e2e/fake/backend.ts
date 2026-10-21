// An in-memory stand-in for the backend API, shaped like the real answers (docs/API.md, the OpenAPI spec). It serves both
// the Next server's session route handlers (sign-in, refresh, sign-out) and the browser's own calls (with the access
// token). The feature areas live in their own modules; this one has the accounts, tokens and the routing.
import { FakeMenu } from './menu'
import { FakeOrders } from './orders'
import { FakeReports } from './reports'
import { FakeTeam } from './team'
import { FakeZones } from './zones'
import { fail, ok, unauthenticated, type FakeRequest, type RecordedCall, type Reply } from './types'

export const ROLE_LABELS: Record<string, string> = { owner: 'Ega', manager: 'Menejer', cashier: 'Kassir', waiter: 'Ofitsiant', cook: 'Oshpaz', courier: 'Kuryer' }

// What the owner's role holds by default (App\Services\Auth\RolePermissions), the part the panel looks at
export const OWNER_PERMISSIONS = [
  'orders.view', 'orders.manage', 'orders.cancel', 'orders.assign_courier', 'kitchen.manage',
  'menu.view', 'menu.manage', 'stoplist.manage', 'batches.manage',
  'staff.view', 'staff.manage', 'staff.pin', 'branches.manage', 'settings.manage', 'zones.manage', 'devices.view', 'devices.manage',
  'reports.view', 'finance.view',
]

export interface Account {
  id: number
  name: string
  role: string
  phone: string
  storeId: number
  storeName: string
  branchId: number | null
  branchName: string | null
  permissions: string[]
}

export const OWNER: Account = {
  id: 7,
  name: 'Olim Egamov',
  role: 'owner',
  phone: '+998901234567',
  storeId: 1,
  storeName: 'Lazzat Oshxona',
  branchId: null,
  branchName: null,
  permissions: OWNER_PERMISSIONS,
}

// The code the fake accepts for every phone ("sent" by SMS)
export const VALID_CODE = '123456'

const DAY_MS = 86_400_000

function formFields(form: FormData): Record<string, unknown> {
  const fields: Record<string, unknown> = {}
  for (const [name, value] of form.entries()) {
    if (typeof value !== 'string') fields[name] = `(file ${value.name})`
    else fields[name] = name === 'variants' ? (JSON.parse(value) as unknown) : value
  }
  return fields
}

export class FakeBackend {
  /** Who can sign in, by phone. More than one account on a phone is the "two stores" case (account selection). */
  accounts = new Map<string, Account[]>()
  calls: RecordedCall[] = []
  /** Requests no endpoint of the fake knows: a test fails when the page starts using one. */
  unhandled: string[] = []

  private accessTokens = new Map<string, { account: Account; expiresAt: number }>()
  private refreshTokens = new Map<string, { account: Account; deviceUuid: string }>()
  private selectionTickets = new Map<string, Account[]>()
  private counter = 0
  /** The SMS codes the backend was asked to send, by phone (most recent last). */
  readonly smsRequests: string[] = []
  /** Set to make the next sign-in attempt fail with this message (a server refusal in Uzbek). */
  signInRefusal: { status: number; message: string; reason?: string } | null = null

  orders: FakeOrders
  menu: FakeMenu
  team: FakeTeam
  zones: FakeZones
  reports: FakeReports

  constructor() {
    this.orders = new FakeOrders(this)
    this.menu = new FakeMenu(this)
    this.team = new FakeTeam(this)
    this.zones = new FakeZones(this)
    this.reports = new FakeReports(this)
    this.accounts.set(OWNER.phone, [OWNER])
  }

  /** The account whose token signed the request (set while a request is routed). */
  account!: Account

  // --- Tokens ------------------------------------------------------------------------------------------------------

  private issue(account: Account, deviceUuid: string) {
    const n = ++this.counter
    const token = `access-${n}`
    const refresh = `refresh-${n}`
    const expiresAt = Date.now() + 15 * 60_000
    this.accessTokens.set(token, { account, expiresAt })
    this.refreshTokens.set(refresh, { account, deviceUuid })
    return {
      token,
      token_expires_at: new Date(expiresAt).toISOString(),
      refresh_token: refresh,
      refresh_token_expires_at: new Date(Date.now() + 30 * DAY_MS).toISOString(),
      device: { uuid: deviceUuid, type: 'web', name: 'Hamkor veb-panel', platform: 'web', user: { id: account.id, name: account.name, role: account.role } },
    }
  }

  /** A refresh token a browser can start with, as if it had signed in earlier (the `rz_refresh` cookie value). */
  seedSession(account: Account = OWNER): { refreshToken: string; deviceUuid: string } {
    const deviceUuid = `00000000-0000-4000-8000-${String(++this.counter).padStart(12, '0')}`
    const { refresh_token } = this.issue(account, deviceUuid)
    return { refreshToken: refresh_token, deviceUuid }
  }

  /** Makes every access token invalid (the page has to refresh). */
  expireAccessTokens(): void {
    this.accessTokens.clear()
  }

  private userPayload(account: Account) {
    return { id: account.id, name: account.name, role: account.role, phone: account.phone, store_id: account.storeId, branch_id: account.branchId }
  }

  private session(account: Account, device: Record<string, unknown>): Reply {
    const uuid = typeof device.uuid === 'string' ? device.uuid : `device-${this.counter}`
    return ok({ ...this.issue(account, uuid), user: this.userPayload(account), store: { id: account.storeId, name: account.storeName }, sms_verification_required: false, is_new_user: false })
  }

  // --- Auth endpoints (called by the Next route handlers) --------------------------------------------------------------

  private auth(req: FakeRequest, body: Record<string, unknown>): Reply | null {
    if (req.method !== 'POST') return null
    switch (req.path) {
      case '/v1/auth/phone/send-code':
        this.smsRequests.push(String(body.phone))
        return ok({ code: null }, {})
      case '/v1/auth/phone/verify': {
        if (this.signInRefusal) {
          const refusal = this.signInRefusal
          return fail(refusal.status, refusal.message, undefined, refusal.reason ? { reason: refusal.reason } : undefined)
        }
        if (body.code !== VALID_CODE) return fail(422, "Tasdiqlash kodi noto'g'ri.")
        const accounts = this.accounts.get(String(body.phone)) ?? []
        if (accounts.length === 0) {
          return fail(403, "Bu raqam hech qaysi do'kon xodimiga biriktirilmagan. Do'kon egasidan raqamingizni xodimlar ro'yxatiga kiritishni so'rang.", undefined, { reason: 'staff_account_not_found' })
        }
        if (accounts.length > 1) {
          const ticket = `sel_${++this.counter}`
          this.selectionTickets.set(ticket, accounts)
          return fail(409, 'Qaysi hisobga kirasiz?', undefined, {
            reason: 'account_selection_required',
            selection_token: ticket,
            selection_expires_in: 300,
            accounts: accounts.map((account) => ({
              user_id: account.id,
              name: account.name,
              role: account.role,
              role_label: ROLE_LABELS[account.role] ?? account.role,
              store: { id: account.storeId, name: account.storeName },
              branch: account.branchId === null ? null : { id: account.branchId, name: account.branchName },
            })),
          })
        }
        return this.session(accounts[0], (body.device as Record<string, unknown>) ?? {})
      }
      case '/v1/auth/phone/select-account': {
        const offered = this.selectionTickets.get(String(body.selection_token))
        const account = offered?.find((candidate) => candidate.id === body.user_id)
        if (!offered || !account) return fail(422, "Tanlov muddati tugagan yoki noto'g'ri. Iltimos, qaytadan kiring.", undefined, { reason: 'selection_invalid' })
        this.selectionTickets.delete(String(body.selection_token))
        return this.session(account, (body.device as Record<string, unknown>) ?? {})
      }
      case '/v1/auth/refresh': {
        const record = this.refreshTokens.get(String(body.refresh_token))
        // Single use: a spent token is refused (and signs the browser out)
        if (!record) return fail(401, 'Seans tugagan.', undefined, { reason: 'refresh_invalid' })
        this.refreshTokens.delete(String(body.refresh_token))
        return ok(this.issue(record.account, record.deviceUuid))
      }
      case '/v1/auth/logout':
        return ok(null)
    }
    return null
  }

  // --- Routing -------------------------------------------------------------------------------------------------------

  handle(req: FakeRequest): Reply {
    const json = req.body instanceof FormData || req.body === null ? null : req.body
    const reply = this.route(req, json)
    // A multipart form is recorded as its fields (the product form's `variants` as the JSON it carries)
    const recorded = req.body instanceof FormData ? formFields(req.body) : json
    this.calls.push({ method: req.method, path: req.path, query: req.query, body: recorded, status: reply.status })
    return reply
  }

  private route(req: FakeRequest, json: Record<string, unknown> | null): Reply {
    const open = this.auth(req, json ?? {})
    if (open) return open

    const token = req.authorization?.replace(/^Bearer /, '') ?? ''
    const record = this.accessTokens.get(token)
    if (!record || record.expiresAt < Date.now()) return unauthenticated()
    this.account = record.account

    if (req.method === 'GET' && req.path === '/v1/me/permissions') {
      const account = record.account
      return ok({
        role: account.role,
        store_id: account.storeId,
        branch_id: account.branchId,
        branch_scoped: account.branchId !== null,
        permissions: account.permissions,
        device_uuid: null,
      })
    }

    // The signature of a private channel subscription (Laravel's /broadcasting/auth): the channels this person may join
    if (req.method === 'POST' && req.path === '/broadcasting/auth') {
      const channel = String(json?.channel_name ?? '')
      const account = record.account
      const allowed = account.branchId === null ? [`private-store.${account.storeId}`] : [`private-pos-branch.${account.branchId}`]
      if (!allowed.includes(channel) && !/^private-pos-branch\.\d+$/.test(channel)) return fail(403, 'Forbidden')
      return { status: 200, body: { auth: 'e2e-key:fake-signature' } }
    }

    for (const area of [this.orders, this.menu, this.team, this.zones, this.reports]) {
      const reply = area.handle(req, json)
      if (reply) return reply
    }

    this.unhandled.push(`${req.method} ${req.path}`)
    return fail(404, `E2E fake backend: no endpoint for ${req.method} ${req.path}`)
  }

  callsTo(method: string, path: string | RegExp): RecordedCall[] {
    return this.calls.filter((call) => call.method === method && (typeof path === 'string' ? call.path === path : path.test(call.path)))
  }
}
