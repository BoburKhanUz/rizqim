// The fake of Monitoring (P4-15): /admin/monitoring/health, /failed-jobs, /alerts and /rules, shaped like the controllers' answers
// (App\Http\Controllers\Api\V1\Admin\AdminMonitoring*). A test changes `health`, `alerts`, `jobs` or `rules` before (or while)
// the page is open; the page asks again every 30 seconds. The rules of the real backend that matter to the screens are here:
// viewing needs monitoring.view, acting monitoring.manage, deleting a job and changing a rule also a step-up and a reason.
import { fail, ok, type FakeRequest, type Reply } from './types'

const minutesAgo = (minutes: number) => new Date(Date.now() - minutes * 60_000).toISOString()

export interface FakeAlert {
  id: number
  kind: string
  kind_label: string
  severity: 'critical' | 'warning' | 'info'
  state: 'open' | 'acknowledged' | 'resolved'
  title: string
  details: Record<string, unknown> | null
  store: { id: number; name: string } | null
  first_seen_at: string
  last_seen_at: string
  count: number
  acknowledged: { by: { id: number; name: string } | null; at: string; note: string | null } | null
  resolved: { by: { id: number; name: string } | null; at: string; note: string | null; resolution: 'manual' | 'auto' | 'rule_disabled' | null } | null
}

export interface FakeJob {
  id: number
  uuid: string
  job: string
  job_class: string
  queue: string
  connection: string
  exception_line: string
  failed_at: string
  exception: string
  payload: Record<string, unknown>
}

interface RuleField {
  label: string
  unit: string
  min: number
  max: number
}

export interface FakeRule {
  kind: string
  label: string
  description: string
  severity: 'critical' | 'warning' | 'info'
  enabled: boolean
  telegram: boolean
  threshold: number
  threshold_secondary: number | null
  window_minutes: number | null
  fields: { threshold: RuleField; threshold_secondary: RuleField | null; window: RuleField | null }
  defaults: { threshold: number; threshold_secondary: number | null; window_minutes: number | null }
  unresolved_alerts: number
  updated_at: string | null
  updated_by: { id: number; name: string } | null
}

const rule = (kind: string, label: string, description: string, severity: FakeRule['severity'], defaults: FakeRule['defaults'], fields: FakeRule['fields']): FakeRule => ({
  kind,
  label,
  description,
  severity,
  enabled: true,
  telegram: true,
  threshold: defaults.threshold,
  threshold_secondary: defaults.threshold_secondary,
  window_minutes: defaults.window_minutes,
  fields,
  defaults,
  unresolved_alerts: 0,
  updated_at: null,
  updated_by: null,
})

export function defaultRules(): FakeRule[] {
  return [
    rule('failed_jobs_spike', "Yiqilgan vazifalar ko'payishi", "Oxirgi daqiqalarda yiqilgan fon vazifalari soni chegaradan oshdi.", 'warning', { threshold: 10, threshold_secondary: null, window_minutes: 15 }, {
      threshold: { label: 'Yiqilgan vazifalar soni', unit: 'ta', min: 1, max: 10000 },
      threshold_secondary: null,
      window: { label: 'Oxirgi qancha vaqt ichida', unit: 'daqiqa', min: 1, max: 1440 },
    }),
    rule('queue_backlog', "Navbat to'planishi", "Navbatda vazifalar to'planib qoldi yoki eng eskisi juda uzoq kutmoqda.", 'warning', { threshold: 500, threshold_secondary: 10, window_minutes: null }, {
      threshold: { label: 'Navbatdagi vazifalar soni', unit: 'ta', min: 1, max: 1000000 },
      threshold_secondary: { label: 'Eng eski vazifa kutish vaqti', unit: 'daqiqa', min: 1, max: 1440 },
      window: null,
    }),
    rule('pos_device_offline', 'Ish vaqtida oflayn kassa', "Ovqatxona ish vaqtida kassa yoki oshxona ekrani aloqada emas.", 'warning', { threshold: 15, threshold_secondary: null, window_minutes: null }, {
      threshold: { label: 'Aloqasiz turgan vaqt (ish vaqti ichida)', unit: 'daqiqa', min: 5, max: 720 },
      threshold_secondary: null,
      window: null,
    }),
    rule('fiscal_failures', 'Fiskal cheklar yiqilishi', "Fiskal chek soliq tizimida ro'yxatdan o'tmadi (yiqilgan cheklar).", 'critical', { threshold: 1, threshold_secondary: null, window_minutes: 60 }, {
      threshold: { label: 'Yiqilgan cheklar soni', unit: 'ta', min: 1, max: 10000 },
      threshold_secondary: null,
      window: { label: 'Oxirgi qancha vaqt ichida', unit: 'daqiqa', min: 5, max: 10080 },
    }),
    rule('settlement_failures', 'Hisob-kitob xatolari', 'Yetkazilgan buyurtma puli hisobga yozilmadi.', 'critical', { threshold: 1, threshold_secondary: null, window_minutes: null }, {
      threshold: { label: 'Hisobga yozilmagan buyurtmalar soni', unit: 'ta', min: 1, max: 10000 },
      threshold_secondary: null,
      window: null,
    }),
    rule('backup_stale', 'Zaxira nusxa eskirgan', "Ma'lumotlar bazasining oxirgi zaxira nusxasi belgilangan muddatdan eski.", 'critical', { threshold: 26, threshold_secondary: null, window_minutes: null }, {
      threshold: { label: 'Oxirgi zaxiradan beri', unit: 'soat', min: 1, max: 720 },
      threshold_secondary: null,
      window: null,
    }),
    rule('scheduler_stale', "Scheduler to'xtagan", "Scheduler yurak urishi yo'q yoki eskirgan: rejalashtirilgan ishlar ishlamayapti.", 'critical', { threshold: 180, threshold_secondary: null, window_minutes: null }, {
      threshold: { label: 'Yurak urishi eskirishi', unit: 'soniya', min: 60, max: 3600 },
      threshold_secondary: null,
      window: null,
    }),
  ]
}

export function defaultHealth() {
  return {
    status: 'degraded',
    checked_at: new Date().toISOString(),
    database: { status: 'ok', latency_ms: 1.4 },
    redis: { status: 'ok', latency_ms: 0.6 },
    queue: {
      status: 'backlog',
      queues: [
        { name: 'default', size: 640, oldest_age_seconds: 780 },
        { name: 'broadcasts', size: 0, oldest_age_seconds: null },
        { name: 'reports', size: 2, oldest_age_seconds: 45 },
      ],
      failed_jobs: { total: 3, last_24h: 2, latest_failed_at: minutesAgo(12) },
    },
    scheduler: { status: 'ok', last_heartbeat_age_seconds: 20, max_age_seconds: 180 },
    reverb: { status: 'ok', latency_ms: 4.2 },
    backup: { status: 'stale', last_backup_at: minutesAgo(30 * 60), age_hours: 30, max_age_hours: 26 },
    app: { version: '1.4.0', commit: 'a1b2c3d', environment: 'production' },
    sentry: { configured: true, environment: 'production', project_url: 'https://sentry.io/organizations/rizqim/projects/rizqim-backend/' },
    alerts: { open: 2, acknowledged: 1, critical_unresolved: 1 },
  }
}

export type FakeHealth = ReturnType<typeof defaultHealth>

export function defaultAlerts(): FakeAlert[] {
  return [
    {
      id: 1,
      kind: 'backup_stale',
      kind_label: 'Zaxira nusxa eskirgan',
      severity: 'critical',
      state: 'open',
      title: 'Oxirgi zaxira nusxa 30 soat oldin olingan',
      details: { last_backup_at: minutesAgo(30 * 60), age_hours: 30, threshold_hours: 26 },
      store: null,
      first_seen_at: minutesAgo(240),
      last_seen_at: minutesAgo(1),
      count: 240,
      acknowledged: null,
      resolved: null,
    },
    {
      id: 2,
      kind: 'queue_backlog',
      kind_label: "Navbat to'planishi",
      severity: 'warning',
      state: 'open',
      title: '"default" navbatida 640 ta vazifa to\'planib qoldi',
      details: { queue: 'default', size: 640, oldest_age_seconds: 780, threshold: 500 },
      store: null,
      first_seen_at: minutesAgo(20),
      last_seen_at: minutesAgo(1),
      count: 20,
      acknowledged: null,
      resolved: null,
    },
    {
      id: 3,
      kind: 'pos_device_offline',
      kind_label: 'Ish vaqtida oflayn kassa',
      severity: 'warning',
      state: 'acknowledged',
      title: 'Lazzat Oshxona: Kassa 1 45 daqiqadan beri aloqada emas',
      details: { store: 'Lazzat Oshxona', device_name: 'Kassa 1', offline_minutes: 45, last_seen_at: minutesAgo(45) },
      store: { id: 12, name: 'Lazzat Oshxona' },
      first_seen_at: minutesAgo(40),
      last_seen_at: minutesAgo(1),
      count: 40,
      acknowledged: { by: { id: 2, name: 'Sardor Operator' }, at: minutesAgo(30), note: "Egasiga qo'ng'iroq qildim" },
      resolved: null,
    },
    {
      id: 4,
      kind: 'scheduler_stale',
      kind_label: "Scheduler to'xtagan",
      severity: 'critical',
      state: 'resolved',
      title: 'Scheduler 6 daqiqadan beri yurak urishini yozmayapti',
      details: { last_heartbeat_age_seconds: 400, threshold_seconds: 180 },
      store: null,
      first_seen_at: minutesAgo(600),
      last_seen_at: minutesAgo(590),
      count: 8,
      acknowledged: null,
      resolved: { by: null, at: minutesAgo(590), note: null, resolution: 'auto' },
    },
  ]
}

const SECRET_NOTE = '[redacted]'

export function jobOf(id: number, minutes: number, overrides: Partial<FakeJob> = {}): FakeJob {
  const uuid = `00000000-0000-4000-8000-${String(id).padStart(12, '0')}`
  return {
    id,
    uuid,
    job: 'SendTelegramRequest',
    job_class: 'App\\Jobs\\SendTelegramRequest',
    queue: 'default',
    connection: 'redis',
    exception_line: 'Illuminate\\Http\\Client\\ConnectionException: cURL error 28: Operation timed out for https://api.telegram.org/bot[Filtered]/sendMessage',
    failed_at: minutesAgo(minutes),
    exception: 'Illuminate\\Http\\Client\\ConnectionException: cURL error 28: Operation timed out for https://api.telegram.org/bot[Filtered]/sendMessage\n#0 /var/www/html/app/Jobs/SendTelegramRequest.php(60): Illuminate\\Http\\Client\\PendingRequest->send()\n#1 {main}',
    payload: {
      displayName: 'App\\Jobs\\SendTelegramRequest',
      maxTries: 5,
      attempts: 5,
      command: { method: 'sendMessage', params: { chat_id: '-100123', api_key: SECRET_NOTE, text: 'Buyurtma tayyor' } },
    },
    ...overrides,
  }
}

export function defaultJobs(): FakeJob[] {
  return [
    jobOf(3, 12),
    jobOf(2, 90, { job: 'SubmitClickFiscalData', job_class: 'App\\Jobs\\SubmitClickFiscalData', queue: 'reports', exception_line: 'RuntimeException: Click fiscal API returned 502', exception: 'RuntimeException: Click fiscal API returned 502\n#0 {main}' }),
    jobOf(1, 1500, { job: 'GenerateMenuImageThumbnails', job_class: 'App\\Jobs\\GenerateMenuImageThumbnails', exception_line: 'Intervention\\Image\\Exceptions\\DecoderException: Unsupported image', exception: 'Intervention\\Image\\Exceptions\\DecoderException: Unsupported image\n#0 {main}' }),
  ]
}

const PAGE = 25

export class FakeMonitoring {
  health: FakeHealth = defaultHealth()
  alerts: FakeAlert[] = defaultAlerts()
  /** Newest first */
  jobs: FakeJob[] = defaultJobs()
  rules: FakeRule[] = defaultRules()
  telegramConfigured = true
  /** Set to make the next health call fail with this status (a down server), once */
  failHealthOnce: number | null = null

  /** Adds `count` older failed jobs (for the cursor list) */
  seedJobs(count: number): void {
    const start = this.jobs.length
    for (let i = 0; i < count; i++) this.jobs.push(jobOf(start + i + 1000, 2000 + i))
    this.jobs.sort((a, b) => b.id - a.id)
  }

  private counts() {
    return {
      open: this.alerts.filter((a) => a.state === 'open').length,
      acknowledged: this.alerts.filter((a) => a.state === 'acknowledged').length,
      resolved_7d: this.alerts.filter((a) => a.state === 'resolved').length,
    }
  }

  private unresolvedPerRule(): void {
    for (const r of this.rules) r.unresolved_alerts = this.alerts.filter((a) => a.kind === r.kind && a.state !== 'resolved').length
  }

  /** The reply for a path below /v1/admin, or null when it is not a monitoring path */
  route(req: FakeRequest, path: string, body: Record<string, unknown>, can: (permission: string) => boolean, needsRecent: () => Reply | null, actor: { id: number; name: string }): Reply | null {
    if (!path.startsWith('/monitoring/')) return null
    const view = can('admin.monitoring.view')
    const manage = can('admin.monitoring.manage')
    const forbidden = fail(403, "Bu amal uchun ruxsatingiz yo'q.", { reason: 'forbidden' })
    const write = req.method !== 'GET'

    if (!view || (write && !manage)) return forbidden

    if (req.method === 'GET' && path === '/monitoring/health') {
      if (this.failHealthOnce !== null) {
        const status = this.failHealthOnce
        this.failHealthOnce = null
        return fail(status, 'Server xatosi.')
      }
      return ok({ ...this.health, checked_at: new Date().toISOString() })
    }

    if (req.method === 'GET' && path === '/monitoring/alerts') {
      const state = req.query.state || 'active'
      const kind = req.query.kind || ''
      let rows = this.alerts.filter((a) => (kind ? a.kind === kind : true))
      if (state === 'active') rows = rows.filter((a) => a.state !== 'resolved')
      else if (state !== 'all') rows = rows.filter((a) => a.state === state)
      const rank = { critical: 0, warning: 1, info: 2 }
      rows = [...rows].sort((a, b) => (state === 'all' || state === 'resolved' ? b.id - a.id : (a.state === b.state ? 0 : a.state === 'open' ? -1 : 1) || rank[a.severity] - rank[b.severity] || b.id - a.id))
      return ok({ data: rows, next_cursor: null, prev_cursor: null, per_page: 25, counts: this.counts() })
    }

    const alert = /^\/monitoring\/alerts\/(\d+)(?:\/(acknowledge|resolve))?$/.exec(path)
    if (alert) {
      const row = this.alerts.find((a) => a.id === Number(alert[1]))
      if (!row) return fail(404, 'Ogohlantirish topilmadi.', { reason: 'not_found' })
      if (req.method === 'GET' && !alert[2]) return ok(row)
      const note = typeof body.note === 'string' && body.note !== '' ? body.note : null
      if (req.method === 'POST' && alert[2] === 'acknowledge') {
        if (row.state !== 'open') return fail(409, 'Bu ogohlantirish allaqachon qabul qilingan yoki hal bo\'lgan.', { reason: 'alert_not_open' })
        row.state = 'acknowledged'
        row.acknowledged = { by: actor, at: new Date().toISOString(), note }
        this.health.alerts = { ...this.health.alerts, open: this.counts().open, acknowledged: this.counts().acknowledged }
        return ok({ id: row.id, kind: row.kind, state: row.state })
      }
      if (req.method === 'POST' && alert[2] === 'resolve') {
        if (row.state === 'resolved') return fail(409, 'Bu ogohlantirish allaqachon hal bo\'lgan.', { reason: 'alert_resolved' })
        row.state = 'resolved'
        row.resolved = { by: actor, at: new Date().toISOString(), note, resolution: 'manual' }
        this.health.alerts = { ...this.health.alerts, open: this.counts().open, acknowledged: this.counts().acknowledged }
        return ok({ id: row.id, kind: row.kind, state: row.state })
      }
    }

    if (req.method === 'GET' && path === '/monitoring/failed-jobs') {
      const queue = req.query.queue || ''
      const q = (req.query.q || '').toLowerCase()
      const rows = this.jobs.filter((j) => (queue ? j.queue === queue : true) && (q ? j.job_class.toLowerCase().includes(q) : true))
      const offset = req.query.cursor ? Number(req.query.cursor) : 0
      const perPage = Number(req.query.per_page) || PAGE
      const slice = rows.slice(offset, offset + perPage)
      const next = offset + perPage < rows.length ? String(offset + perPage) : null
      return ok({
        data: slice.map((j) => ({ id: j.id, uuid: j.uuid, job: j.job, job_class: j.job_class, queue: j.queue, connection: j.connection, exception_line: j.exception_line, failed_at: j.failed_at })),
        next_cursor: next,
        prev_cursor: null,
        per_page: perPage,
        total: this.jobs.length,
      })
    }

    const job = /^\/monitoring\/failed-jobs\/([0-9a-f-]{36})(?:\/(retry))?$/.exec(path)
    if (job) {
      const row = this.jobs.find((j) => j.uuid === job[1])
      const gone = fail(404, 'Yiqilgan vazifa topilmadi.', { reason: 'not_found' })
      if (req.method === 'GET' && !job[2]) return row ? ok({ ...row, attempts: 5, max_tries: 5 }) : gone
      if (req.method === 'POST' && job[2] === 'retry') {
        if (!row) return gone
        this.removeJob(row)
        return ok({ uuid: row.uuid, job: row.job, queue: row.queue }, { message: 'Vazifa navbatga qaytarildi.' })
      }
      if (req.method === 'DELETE' && !job[2]) {
        const recent = needsRecent()
        if (recent) return recent
        if (typeof body.reason !== 'string' || body.reason.trim().length < 3) return fail(422, 'Sabab kamida 3 belgidan iborat bo\'lishi kerak.', { reason: 'validation' }, { errors: { reason: ['Sabab kamida 3 belgidan iborat bo\'lishi kerak.'] } })
        if (!row) return gone
        this.removeJob(row)
        return ok({ uuid: row.uuid, job: row.job, queue: row.queue }, { message: "Vazifa o'chirildi." })
      }
    }

    if (req.method === 'GET' && path === '/monitoring/rules') {
      this.unresolvedPerRule()
      return ok({ rules: this.rules, telegram_configured: this.telegramConfigured })
    }

    const update = /^\/monitoring\/rules\/([a-z_]+)$/.exec(path)
    if (req.method === 'PUT' && update) {
      const recent = needsRecent()
      if (recent) return recent
      const target = this.rules.find((r) => r.kind === update[1])
      if (!target) return fail(404, 'Bunday qoida yo\'q.', { reason: 'not_found' })
      if (typeof body.reason !== 'string' || body.reason.trim().length < 3) return fail(422, 'Sabab kamida 3 belgidan iborat bo\'lishi kerak.', { reason: 'validation' }, { errors: { reason: ['Sabab kamida 3 belgidan iborat bo\'lishi kerak.'] } })
      for (const [name, field] of [['threshold', target.fields.threshold], ['threshold_secondary', target.fields.threshold_secondary], ['window_minutes', target.fields.window]] as const) {
        if (body[name] === undefined) continue
        const value = Number(body[name])
        if (!field || !Number.isInteger(value) || value < field.min || value > field.max) {
          return fail(422, `${name} noto'g'ri.`, { reason: 'validation' }, { errors: { [name]: [`${name} ${field ? `${field.min} dan ${field.max} gacha bo'lishi kerak` : 'bu qoidada yo\'q'}.`] } })
        }
        ;(target as unknown as Record<string, unknown>)[name] = value
      }
      if (typeof body.enabled === 'boolean') {
        target.enabled = body.enabled
        // The evaluator resolves the alerts of a rule that is switched off
        if (!body.enabled) for (const a of this.alerts) if (a.kind === target.kind && a.state !== 'resolved') Object.assign(a, { state: 'resolved', resolved: { by: null, at: new Date().toISOString(), note: "Qoida o'chirildi", resolution: 'rule_disabled' } })
      }
      if (typeof body.telegram === 'boolean') target.telegram = body.telegram
      target.updated_at = new Date().toISOString()
      target.updated_by = actor
      this.unresolvedPerRule()
      return ok({ rules: this.rules, telegram_configured: this.telegramConfigured }, { message: 'Qoida saqlandi.' })
    }

    return null
  }

  private removeJob(row: FakeJob): void {
    this.jobs = this.jobs.filter((j) => j !== row)
    this.health.queue.failed_jobs = { ...this.health.queue.failed_jobs, total: this.jobs.length }
  }
}
