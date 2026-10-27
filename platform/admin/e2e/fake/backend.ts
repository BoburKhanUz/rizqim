// An in-memory stand-in for the admin API (/api/v1/admin/*, the P4-11 contract), shaped like the real answers. The Next
// server's proxy talks to it, so the app's own route handler runs for real. Enough of the rules are here to drive the screens:
// email + password, TOTP (any member's code is VALID_CODE), recovery codes, lockout, challenge expiry and reuse, step-up
// (`admin.recent`), team, roles, audit log, search, sessions.
import { fail, ok, validation, type FakeRequest, type RecordedCall, type Reply } from './types'

export const VALID_CODE = '123456'
export const PASSWORD = 'correct horse battery'
const STEP_UP_MS = 10 * 60_000
const INVITE_MS = 72 * 3_600_000

export type PlatformRole = 'superadmin' | 'operator' | 'support' | 'finance' | 'sales' | 'viewer'

export const ROLE_LABELS: Record<PlatformRole, string> = { superadmin: 'Superadmin', operator: 'Operator', support: 'Yordam', finance: 'Moliya', sales: 'Savdo / ulash', viewer: "Faqat ko'rish" }
const ROLES = Object.keys(ROLE_LABELS) as PlatformRole[]

export const PERMISSIONS: Array<{ key: string; label: string; group: string }> = [
  { key: 'admin.today.view', label: "Bugun sahifasini ko'rish", group: 'Kundalik ish' },
  { key: 'admin.orders.view', label: "Buyurtmalarni ko'rish", group: 'Kundalik ish' },
  { key: 'admin.orders.intervene', label: 'Buyurtmaga aralashish', group: 'Kundalik ish' },
  { key: 'admin.stores.view', label: "Ovqatxonalarni ko'rish", group: 'Ovqatxonalar' },
  { key: 'admin.stores.manage', label: 'Ovqatxonalarni boshqarish', group: 'Ovqatxonalar' },
  { key: 'admin.finance.view', label: "Moliyani ko'rish", group: 'Moliya' },
  { key: 'admin.payouts.approve', label: 'Payoutni tasdiqlash', group: 'Moliya' },
  { key: 'admin.devices.view', label: "Qurilmalarni ko'rish", group: 'POS va obunalar' },
  { key: 'admin.monitoring.view', label: "Monitoringni ko'rish", group: 'Tizim' },
  { key: 'admin.users.view', label: "Foydalanuvchilarni ko'rish", group: 'Mijozlar' },
  { key: 'admin.support.view', label: "Shikoyatlarni ko'rish", group: 'Mijozlar' },
  { key: 'admin.subscriptions.manage', label: 'Obunalarni boshqarish', group: 'POS va obunalar' },
  { key: 'admin.marketing.manage', label: 'Marketingni boshqarish', group: 'Marketing' },
  { key: 'admin.integrations.view', label: "Integratsiyalarni ko'rish", group: 'Integratsiyalar' },
  { key: 'admin.audit.view', label: "Jurnalni ko'rish", group: 'Tizim' },
  { key: 'admin.team.manage', label: 'Jamoani boshqarish', group: 'Tizim' },
]

const ALL = PERMISSIONS.map((p) => p.key)
const VIEWS = ALL.filter((key) => key.endsWith('.view'))
const DEFAULT_GRANTS: Record<PlatformRole, string[]> = {
  superadmin: ALL,
  operator: ['admin.today.view', 'admin.orders.view', 'admin.orders.intervene', 'admin.stores.view', 'admin.monitoring.view', 'admin.devices.view'],
  support: ['admin.orders.view', 'admin.users.view', 'admin.stores.view'],
  finance: ['admin.finance.view', 'admin.payouts.approve', 'admin.stores.view'],
  sales: ['admin.stores.view', 'admin.stores.manage'],
  viewer: VIEWS,
}

export interface Member {
  id: number
  name: string
  email: string
  password: string | null
  platform_role: PlatformRole
  disabled: boolean
  inviteToken: string | null
  inviteExpiresAt: number | null
  twoFactor: boolean
  recoveryCodes: string[]
  lastLoginAt: string | null
}

interface Challenge {
  memberId: number
  kind: 'otp' | 'setup'
  expiresAt: number
  setupStarted: boolean
}

interface Session {
  id: string
  token: string
  memberId: number
  createdAt: number
  lastUsedAt: number
  confirmedUntil: number
  ip: string
  userAgent: string
}

interface AuditRow {
  id: number
  action: string
  actor_id: number | null
  actor_name: string | null
  subject_type: string | null
  subject_id: string | null
  store_id: number | null
  reason: string | null
  before: Record<string, unknown> | null
  after: Record<string, unknown> | null
  ip: string
  user_agent: string
  created_at: string
}

const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 29 29" width="29" height="29"><rect width="29" height="29" fill="#fff"/><rect x="2" y="2" width="7" height="7"/><rect x="20" y="2" width="7" height="7"/><rect x="2" y="20" width="7" height="7"/></svg>'

const STORES = [
  { id: 12, name: 'Lazzat Oshxona', inn: '123456789', phone: '+998901234567' },
  { id: 13, name: 'Lazzat Express', inn: '987654321', phone: '+998935550011' },
  { id: 14, name: 'Shirin Qandolat', inn: '555000111', phone: '+998977001122' },
]
const ORDERS = [
  { uuid: 'ab12cd34-1111-4222-8333-444455556666', code: 'AB12CD', store: 'Lazzat Oshxona' },
  { uuid: 'ab99ef00-1111-4222-8333-444455556667', code: 'AB99EF', store: 'Shirin Qandolat' },
]
const USERS = [{ id: 7, name: 'Olim Egamov', phone: '+998901234567' }]

export const maskPhone = (phone: string) => `${phone.slice(0, 4)} ${phone.slice(4, 6)} *** ** ${phone.slice(-2)}`

export class FakeBackend {
  members: Member[] = []
  grants: Record<string, string[]> = JSON.parse(JSON.stringify(DEFAULT_GRANTS))
  audit: AuditRow[] = []
  calls: RecordedCall[] = []
  unhandled: string[] = []
  /** The code a challenge accepts as the TOTP (any 6 digits equal to this) */
  readonly code = VALID_CODE
  /** Milliseconds a challenge lives; tests shorten it to see it expire */
  challengeTtlMs = 5 * 60_000
  /** Shown to the next sign-in attempt: set to make it fail with the lockout */
  private failedPasswords = 0
  private challenges = new Map<string, Challenge>()
  private sessions = new Map<string, Session>()
  private counter = 0
  private nextMemberId = 1
  private readonly tag = Math.random().toString(36).slice(2, 8)

  constructor() {
    this.addMember({ name: 'Aziza Karimova', email: 'aziza@rizqim.uz', platform_role: 'superadmin', twoFactor: true })
    this.addMember({ name: 'Sardor Operator', email: 'sardor@rizqim.uz', platform_role: 'operator', twoFactor: true })
    this.addMember({ name: 'Madina Yangi', email: 'madina@rizqim.uz', platform_role: 'support', twoFactor: false })
  }

  // --- Fixtures ----------------------------------------------------------------------------------------------------------

  addMember(input: { name: string; email: string; platform_role: PlatformRole; twoFactor?: boolean; password?: string | null; disabled?: boolean; invite?: boolean }): Member {
    const member: Member = {
      id: this.nextMemberId++,
      name: input.name,
      email: input.email,
      password: input.invite ? null : (input.password ?? PASSWORD),
      platform_role: input.platform_role,
      disabled: input.disabled ?? false,
      inviteToken: null,
      inviteExpiresAt: null,
      twoFactor: input.twoFactor ?? false,
      recoveryCodes: input.twoFactor ? ['seed-0001', 'seed-0002', 'seed-0003', 'seed-0004', 'seed-0005', 'seed-0006', 'seed-0007', 'seed-0008'] : [],
      lastLoginAt: input.twoFactor ? '2026-10-26T09:00:00+05:00' : null,
    }
    this.members.push(member)
    return member
  }

  member(email: string): Member {
    const found = this.members.find((m) => m.email === email)
    if (!found) throw new Error(`No member ${email}`)
    return found
  }

  /** A signed-in session of a member, as an earlier visit would have left (the value of the `rz_admin` cookie). */
  seedSession(email = 'aziza@rizqim.uz', options: { confirmed?: boolean; userAgent?: string } = {}): string {
    return this.openSession(this.member(email), '127.0.0.1', options.userAgent ?? 'Seeded Browser', options.confirmed ?? false).token
  }

  liveSessions(): number {
    return this.sessions.size
  }

  callsTo(method: string, path: string): RecordedCall[] {
    return this.calls.filter((call) => call.method === method && call.path === path)
  }

  /** Makes the sessions of a member look idle past the limit (the next call answers 401) */
  endSessions(email: string): void {
    const id = this.member(email).id
    for (const [token, session] of this.sessions) if (session.memberId === id) this.sessions.delete(token)
  }

  /** Marks every session of a member as confirmed (the step-up passed) */
  confirmAll(email: string): void {
    const id = this.member(email).id
    for (const session of this.sessions.values()) if (session.memberId === id) session.confirmedUntil = Date.now() + STEP_UP_MS
  }

  /** Adds `count` journal entries older than everything else (for the cursor list) */
  seedAudit(count: number): void {
    for (let i = 0; i < count; i++) {
      const row = this.entry(null, `admin.seed.event_${String(i).padStart(2, '0')}`, 'seed', String(i), null, null, null, new Date(Date.UTC(2026, 9, 1, 8, 0, 0) + i * 60_000))
      // entry() appends; these are older than everything already logged
      this.audit.pop()
      this.audit.unshift(row)
    }
    this.audit.forEach((row, index) => (row.id = this.audit.length - index))
  }

  // --- Plumbing ------------------------------------------------------------------------------------------------------------

  private permissionsOf(member: Member): string[] {
    return member.platform_role === 'superadmin' ? ALL : (this.grants[member.platform_role] ?? [])
  }

  private openSession(member: Member, ip: string, userAgent: string, confirmed = true): Session {
    const n = ++this.counter
    const token = `admin-${n}-${this.tag}`
    const now = Date.now()
    const session: Session = { id: `s${n}`, token, memberId: member.id, createdAt: now, lastUsedAt: now, confirmedUntil: confirmed ? now + STEP_UP_MS : 0, ip, userAgent }
    this.sessions.set(token, session)
    return session
  }

  private entry(actor: Member | null, action: string, subjectType: string | null, subjectId: string | null, before: Record<string, unknown> | null, after: Record<string, unknown> | null, reason: string | null, at = new Date()): AuditRow {
    const row: AuditRow = {
      id: this.audit.length + 1,
      action,
      actor_id: actor?.id ?? null,
      actor_name: actor?.name ?? null,
      subject_type: subjectType,
      subject_id: subjectId,
      store_id: null,
      reason,
      before,
      after,
      ip: '127.0.0.1',
      user_agent: 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120.0 Safari/537.36',
      created_at: at.toISOString(),
    }
    this.audit.push(row)
    return row
  }

  private teamRow(member: Member) {
    return {
      id: member.id,
      name: member.name,
      email: member.email,
      platform_role: member.platform_role,
      disabled: member.disabled,
      status: member.inviteToken !== null ? 'invited' : member.disabled ? 'disabled' : 'active',
      two_factor_enabled: member.twoFactor,
      last_login_at: member.lastLoginAt,
      active_sessions: [...this.sessions.values()].filter((s) => s.memberId === member.id).length,
      invite_expires_at: member.inviteExpiresAt ? new Date(member.inviteExpiresAt).toISOString() : null,
    }
  }

  private newCodes(member: Member): string[] {
    member.recoveryCodes = Array.from({ length: 10 }, (_, i) => `rc${String(i).padStart(2, '0')}-${Math.random().toString(36).slice(2, 8)}`)
    return [...member.recoveryCodes]
  }

  private challenge(member: Member): Reply {
    const token = `chal-${++this.counter}-${this.tag}`
    const kind = member.twoFactor ? 'otp' : 'setup'
    const expiresAt = Date.now() + this.challengeTtlMs
    this.challenges.set(token, { memberId: member.id, kind, expiresAt, setupStarted: false })
    return ok({ status: kind === 'otp' ? 'otp_required' : 'setup_required', challenge_token: token, expires_at: new Date(expiresAt).toISOString() })
  }

  private useChallenge(token: unknown, kinds: Array<'otp' | 'setup'>): Challenge | Reply {
    const challenge = typeof token === 'string' ? this.challenges.get(token) : undefined
    if (!challenge || !kinds.includes(challenge.kind)) return fail(422, 'Kirish muddati tugadi. Qaytadan boshlang.', { reason: 'challenge_invalid' })
    if (challenge.expiresAt < Date.now()) {
      this.challenges.delete(String(token))
      return fail(422, 'Kirish muddati tugadi. Qaytadan boshlang.', { reason: 'challenge_invalid' })
    }
    return challenge
  }

  private signIn(member: Member, challengeToken: string, req: FakeRequest, extra: Record<string, unknown> = {}): Reply {
    this.challenges.delete(challengeToken)
    const session = this.openSession(member, '127.0.0.1', 'E2E Browser')
    member.lastLoginAt = new Date().toISOString()
    this.entry(member, 'admin.auth.login', 'platform_member', String(member.id), null, null, null)
    void req
    return ok({
      token: session.token,
      expires_at: new Date(session.createdAt + 12 * 3_600_000).toISOString(),
      member: { id: member.id, name: member.name, email: member.email, platform_role: member.platform_role },
      ...extra,
    })
  }

  // --- Routing -------------------------------------------------------------------------------------------------------------

  handle(req: FakeRequest): Reply {
    const reply = this.route(req)
    this.calls.push({ method: req.method, path: req.path.replace(/^\/v1\/admin/, ''), body: req.body, query: req.query, status: reply.status, authorization: req.authorization })
    return reply
  }

  private route(req: FakeRequest): Reply {
    const match = /^\/v1\/admin(\/.*)$/.exec(req.path)
    if (!match) {
      this.unhandled.push(`${req.method} ${req.path}`)
      return fail(404, 'Topilmadi.')
    }
    const path = match[1]!
    const body = req.body ?? {}

    // --- Public sign-in steps
    if (req.method === 'POST' && path === '/auth/login') return this.login(body)
    if (req.method === 'POST' && path === '/auth/invite/accept') return this.acceptInvite(body)
    if (req.method === 'POST' && path === '/auth/2fa/setup') return this.setup(body)
    if (req.method === 'POST' && path === '/auth/2fa/confirm') return this.confirmSetup(body, req)
    if (req.method === 'POST' && path === '/auth/2fa/verify') return this.verify(body, req)

    // --- Everything below needs a session
    const token = /^Bearer (.+)$/.exec(req.authorization ?? '')?.[1]
    const session = token ? this.sessions.get(token) : undefined
    if (!session) return { status: 401, body: { message: 'Unauthenticated.' } }
    const member = this.members.find((m) => m.id === session.memberId)!
    session.lastUsedAt = Date.now()
    const can = (permission: string) => this.permissionsOf(member).includes(permission)
    const needsRecent = (): Reply | null => (session.confirmedUntil < Date.now() ? { status: 403, body: { success: false, message: 'Amalni qayta tasdiqlang.', code: 'reauth_required' } } : null)

    if (req.method === 'GET' && path === '/auth/me') {
      return ok({
        member: { id: member.id, name: member.name, email: member.email, platform_role: member.platform_role },
        platform_role: member.platform_role,
        permissions: this.permissionsOf(member),
        two_factor_enabled: member.twoFactor,
        recovery_codes_left: member.recoveryCodes.length,
        last_login_at: member.lastLoginAt,
      })
    }
    if (req.method === 'POST' && path === '/auth/logout') {
      this.sessions.delete(session.token)
      this.entry(member, 'admin.auth.logout', 'platform_member', String(member.id), null, null, null)
      return ok([])
    }
    if (req.method === 'GET' && path === '/auth/sessions') {
      return ok(
        [...this.sessions.values()]
          .filter((s) => s.memberId === member.id)
          .map((s) => ({ id: s.id, current: s.token === session.token, ip: s.ip, user_agent: s.userAgent, created_at: new Date(s.createdAt).toISOString(), last_used_at: new Date(s.lastUsedAt).toISOString(), expires_at: new Date(s.createdAt + 12 * 3_600_000).toISOString() })),
      )
    }
    const revoke = /^\/auth\/sessions\/([^/]+)$/.exec(path)
    if (req.method === 'DELETE' && revoke) {
      const recent = needsRecent()
      if (recent) return recent
      const target = [...this.sessions.values()].find((s) => s.id === revoke[1] && s.memberId === member.id)
      if (!target) return fail(404, 'Sessiya topilmadi.')
      this.sessions.delete(target.token)
      this.entry(member, 'admin.auth.session_revoked', 'admin_session', target.id, null, null, null)
      return ok([])
    }
    if (req.method === 'POST' && path === '/auth/confirm') {
      if (body.code !== VALID_CODE) return fail(422, 'Kod xato.', { reason: 'invalid_code' })
      session.confirmedUntil = Date.now() + STEP_UP_MS
      return ok({ confirmed_until: new Date(session.confirmedUntil).toISOString() })
    }
    if (req.method === 'POST' && path === '/auth/2fa/recovery-codes') {
      const recent = needsRecent()
      if (recent) return recent
      this.entry(member, 'admin.auth.recovery_codes_regenerated', 'platform_member', String(member.id), null, null, null)
      return ok({ recovery_codes: this.newCodes(member) })
    }

    if (req.method === 'GET' && path === '/search') return this.search(String(req.query.q ?? ''), can)
    if (req.method === 'GET' && path === '/audit-logs') return can('admin.audit.view') ? this.auditList(req.query) : fail(403, 'Ruxsat yo\'q.')

    // --- Team and roles (team.manage)
    if (path === '/team' || path.startsWith('/team/') || path === '/roles') {
      if (!can('admin.team.manage')) return fail(403, "Ruxsat yo'q.")
      return this.team(req, path, body, member, session, needsRecent)
    }

    this.unhandled.push(`${req.method} ${req.path}`)
    return fail(404, 'Topilmadi.')
  }

  private login(body: Record<string, unknown>): Reply {
    if (this.failedPasswords >= 5) return { status: 429, headers: { 'Retry-After': '900' }, body: { success: false, message: "Juda ko'p urinish. 15 daqiqadan so'ng qayta urinib ko'ring.", code: 'locked', data: { reason: 'locked', retry_after: 900 } } }
    const member = this.members.find((m) => m.email === body.email)
    if (!member || member.password === null || member.password !== body.password) {
      this.failedPasswords++
      this.entry(null, 'admin.auth.login_failed', 'platform_member', null, null, null, null)
      return fail(422, "Email yoki parol noto'g'ri.", { reason: 'invalid_credentials' })
    }
    if (member.disabled) return fail(403, "Hisobingiz o'chirilgan. Superadministratorga murojaat qiling.", { reason: 'disabled' })
    this.failedPasswords = 0
    return this.challenge(member)
  }

  private acceptInvite(body: Record<string, unknown>): Reply {
    const member = this.members.find((m) => m.inviteToken !== null && m.inviteToken === body.token)
    if (!member || (member.inviteExpiresAt ?? 0) < Date.now()) return fail(422, 'Taklif havolasi eskirgan yoki ishlatilgan.', { reason: 'invite_invalid' })
    const password = String(body.password ?? '')
    if (password.length < 12) return validation('password', 'Parol kamida 12 ta belgidan iborat bo\'lsin.')
    if (password === member.email) return validation('password', 'Parol emailga o\'xshamasin.')
    member.password = password
    member.inviteToken = null
    member.inviteExpiresAt = null
    this.entry(member, 'admin.team.invite_accepted', 'platform_member', String(member.id), null, null, null)
    return this.challenge(member)
  }

  private setup(body: Record<string, unknown>): Reply {
    const challenge = this.useChallenge(body.challenge_token, ['setup'])
    if ('status' in challenge) return challenge
    // The same challenge gives the same secret again (the real backend), so a double call is harmless
    challenge.setupStarted = true
    const member = this.members.find((m) => m.id === challenge.memberId)!
    return ok({ secret: 'JBSWY3DPEHPK3PXP', otpauth_uri: `otpauth://totp/RIZQIM:${member.email}?secret=JBSWY3DPEHPK3PXP&issuer=RIZQIM`, qr_svg: SVG })
  }

  private confirmSetup(body: Record<string, unknown>, req: FakeRequest): Reply {
    const challenge = this.useChallenge(body.challenge_token, ['setup'])
    if ('status' in challenge) return challenge
    if (body.code !== VALID_CODE) return fail(422, 'Kod xato.', { reason: 'invalid_code' })
    const member = this.members.find((m) => m.id === challenge.memberId)!
    member.twoFactor = true
    this.entry(member, 'admin.auth.two_factor_enabled', 'platform_member', String(member.id), null, null, null)
    return this.signIn(member, String(body.challenge_token), req, { recovery_codes: this.newCodes(member) })
  }

  private verify(body: Record<string, unknown>, req: FakeRequest): Reply {
    const challenge = this.useChallenge(body.challenge_token, ['otp'])
    if ('status' in challenge) return challenge
    const member = this.members.find((m) => m.id === challenge.memberId)!
    if (typeof body.recovery_code === 'string') {
      const index = member.recoveryCodes.indexOf(body.recovery_code)
      if (index < 0) return fail(422, "Tiklash kodi noto'g'ri yoki ishlatilgan.", { reason: 'invalid_recovery_code' })
      member.recoveryCodes.splice(index, 1)
    } else if (body.code !== VALID_CODE) {
      return fail(422, 'Kod xato.', { reason: 'invalid_code' })
    }
    return this.signIn(member, String(body.challenge_token), req)
  }

  private team(req: FakeRequest, path: string, body: Record<string, unknown>, me: Member, session: Session, needsRecent: () => Reply | null): Reply {
    if (req.method === 'GET' && path === '/team') return ok(this.members.map((m) => this.teamRow(m)))
    if (req.method === 'GET' && path === '/roles') return ok(this.matrix())

    // Everything else changes something: a fresh 2FA confirmation first
    const recent = needsRecent()
    if (recent) return recent
    void session

    if (req.method === 'PUT' && path === '/roles') {
      const role = String(body.role)
      const permission = String(body.permission)
      if (role === 'superadmin' || !ROLES.includes(role as PlatformRole)) return fail(422, "Superadmin ruxsatlari o'zgarmaydi.")
      const before = [...(this.grants[role] ?? [])]
      const next = new Set(before)
      // allowed: true grants, false takes away, null goes back to the default
      const wanted = body.allowed === null ? DEFAULT_GRANTS[role as PlatformRole].includes(permission) : Boolean(body.allowed)
      if (wanted) next.add(permission)
      else next.delete(permission)
      this.grants[role] = [...next]
      this.entry(me, 'admin.roles.updated', 'platform_role', role, { allowed: before.includes(permission) }, { allowed: wanted }, null)
      return ok(this.matrix())
    }

    if (req.method === 'POST' && path === '/team') {
      const email = String(body.email ?? '')
      if (this.members.some((m) => m.email === email)) return validation('email', 'Bu email allaqachon jamoada.')
      if (!ROLES.includes(body.platform_role as PlatformRole)) return validation('platform_role', 'Rol noto\'g\'ri.')
      const member = this.addMember({ name: String(body.name ?? ''), email, platform_role: body.platform_role as PlatformRole, invite: true, disabled: true })
      const invite = this.issueInvite(member)
      this.entry(me, 'admin.team.invited', 'platform_member', String(member.id), null, { email, platform_role: member.platform_role }, null)
      return ok({ member: this.teamRow(member), ...invite })
    }

    const one = /^\/team\/(\d+)(\/.*)?$/.exec(path)
    const target = one ? this.members.find((m) => m.id === Number(one[1])) : undefined
    if (!one || !target) return fail(404, 'Xodim topilmadi.')
    const superadmins = this.members.filter((m) => m.platform_role === 'superadmin' && !m.disabled)

    if (req.method === 'POST' && one[2] === '/invite') {
      if (target.inviteToken === null) return fail(422, 'Taklif allaqachon qabul qilingan.')
      this.entry(me, 'admin.team.invite_reissued', 'platform_member', String(target.id), null, null, null)
      return ok(this.issueInvite(target))
    }
    if (req.method === 'POST' && one[2] === '/reset-2fa') {
      target.twoFactor = false
      target.recoveryCodes = []
      for (const [token, s] of this.sessions) if (s.memberId === target.id) this.sessions.delete(token)
      this.entry(me, 'admin.team.two_factor_reset', 'platform_member', String(target.id), { two_factor: true }, { two_factor: false }, null)
      return ok([])
    }
    if (req.method === 'PATCH' && !one[2]) {
      if (target.id === me.id) return fail(422, "O'zingizni o'zgartira olmaysiz.")
      const lastSuper = target.platform_role === 'superadmin' && superadmins.length === 1 && superadmins[0]!.id === target.id
      const before: Record<string, unknown> = {}
      const after: Record<string, unknown> = {}
      if (typeof body.platform_role === 'string') {
        if (!ROLES.includes(body.platform_role as PlatformRole)) return validation('platform_role', "Rol noto'g'ri.")
        if (lastSuper && body.platform_role !== 'superadmin') return fail(422, "Oxirgi superadministratorni o'zgartirib bo'lmaydi.", { reason: 'last_superadmin' })
        before.platform_role = target.platform_role
        after.platform_role = body.platform_role
        target.platform_role = body.platform_role as PlatformRole
      }
      if (typeof body.disabled === 'boolean') {
        if (body.disabled && lastSuper) return fail(422, "Oxirgi superadministratorni o'chirib bo'lmaydi.", { reason: 'last_superadmin' })
        before.disabled = target.disabled
        after.disabled = body.disabled
        target.disabled = body.disabled
        if (body.disabled) for (const [token, s] of this.sessions) if (s.memberId === target.id) this.sessions.delete(token)
      }
      this.entry(me, 'admin.team.updated', 'platform_member', String(target.id), before, after, null)
      return ok(this.teamRow(target))
    }

    this.unhandled.push(`${req.method} ${req.path}`)
    return fail(404, 'Topilmadi.')
  }

  private matrix() {
    return {
      roles: ROLES.map((key) => ({ key, label: ROLE_LABELS[key], editable: key !== 'superadmin' })),
      permissions: PERMISSIONS,
      grants: Object.fromEntries(ROLES.map((key) => [key, key === 'superadmin' ? ALL : (this.grants[key] ?? [])])),
    }
  }

  private issueInvite(member: Member) {
    member.inviteToken = `invite-${++this.counter}-${this.tag}`
    member.inviteExpiresAt = Date.now() + INVITE_MS
    return { invite_url: `http://admin.example.test/invite/${member.inviteToken}`, invite_token: member.inviteToken, invite_expires_at: new Date(member.inviteExpiresAt).toISOString() }
  }

  private search(q: string, can: (permission: string) => boolean): Reply {
    if (q.trim().length < 2) return fail(422, 'Kamida 2 ta belgi yozing.')
    const needle = q.trim().toLowerCase()
    const results: Array<Record<string, unknown>> = []
    if (can('admin.stores.view')) {
      for (const s of STORES.filter((s) => `${s.name} ${s.inn} ${s.phone}`.toLowerCase().includes(needle)).slice(0, 5)) {
        results.push({ type: 'store', id: String(s.id), title: s.name, subtitle: `STIR ${s.inn} · ${maskPhone(s.phone)}`, href: `/stores/${s.id}` })
      }
    }
    if (can('admin.orders.view')) {
      for (const o of ORDERS.filter((o) => o.code.toLowerCase().startsWith(needle) || o.uuid.startsWith(needle)).slice(0, 5)) {
        results.push({ type: 'order', id: o.uuid, title: `Buyurtma #${o.code}`, subtitle: o.store, href: `/orders/${o.uuid}` })
      }
    }
    if (can('admin.users.view')) {
      for (const u of USERS.filter((u) => `${u.name} ${u.phone}`.toLowerCase().includes(needle)).slice(0, 5)) {
        results.push({ type: 'user', id: String(u.id), title: u.name, subtitle: maskPhone(u.phone), href: `/users/${u.id}` })
      }
    }
    if (can('admin.team.manage')) {
      for (const m of this.members.filter((m) => `${m.name} ${m.email}`.toLowerCase().includes(needle)).slice(0, 5)) {
        results.push({ type: 'member', id: String(m.id), title: m.name, subtitle: ROLE_LABELS[m.platform_role], href: `/team/${m.id}` })
      }
    }
    return ok(results)
  }

  private auditList(query: Record<string, string>): Reply {
    const perPage = Math.min(Number(query.per_page) || 30, 100)
    let rows = [...this.audit].reverse()
    if (query.action) rows = rows.filter((r) => r.action.startsWith(query.action!))
    if (query.q) rows = rows.filter((r) => `${r.action} ${r.subject_type ?? ''} ${r.subject_id ?? ''} ${r.reason ?? ''} ${r.actor_name ?? ''}`.toLowerCase().includes(query.q!.toLowerCase()))
    if (query.actor_id) rows = rows.filter((r) => String(r.actor_id) === query.actor_id)
    if (query.store_id) rows = rows.filter((r) => String(r.store_id) === query.store_id)
    if (query.subject_type) rows = rows.filter((r) => r.subject_type === query.subject_type)
    if (query.subject_id) rows = rows.filter((r) => r.subject_id === query.subject_id)
    if (query.from) rows = rows.filter((r) => r.created_at.slice(0, 10) >= query.from!)
    if (query.to) rows = rows.filter((r) => r.created_at.slice(0, 10) <= query.to!)
    const start = query.cursor ? rows.findIndex((r) => String(r.id) === query.cursor) : 0
    const page = rows.slice(Math.max(0, start), Math.max(0, start) + perPage)
    const next = rows[Math.max(0, start) + perPage]
    // The backend's cursor pages: the list and the cursor inside `data`
    return ok({
      data: page.map((r) => ({
        id: r.id,
        action: r.action,
        actor: r.actor_id ? { id: r.actor_id, name: r.actor_name, role: 'platform', platform_role: 'superadmin' } : null,
        store: r.store_id ? { id: r.store_id, name: 'Lazzat Oshxona' } : null,
        subject_type: r.subject_type,
        subject_id: r.subject_id,
        before: r.before,
        after: r.after,
        meta: null,
        reason: r.reason,
        ip: r.ip,
        user_agent: r.user_agent,
        created_at: r.created_at,
      })),
      next_cursor: next ? String(next.id) : null,
      prev_cursor: null,
      per_page: perPage,
    })
  }
}
