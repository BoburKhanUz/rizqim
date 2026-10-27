// The booking book of the fake backend (docs/API.md P3-17): the calendar of the floor's rooms, creating (with the collision
// check and its 409), confirming and canceling. Times are UTC instants; a time without a zone is read in Tashkent (UTC+5).
import type { FakeBackend } from './backend'
import { created, fail, ok, type FakeRequest, type Reply } from './types'

const TZ_OFFSET_MS = 5 * 3_600_000

export interface FakeBooking {
  uuid: string
  branch_id: number
  dining_table_id: number | null
  table_ids: number[]
  starts_at: string
  ends_at: string
  guests_planned: number
  event_type: string
  event_type_label: string
  contact_name: string
  contact_phone: string | null
  source: string
  source_label: string
  status: string
  status_label: string
  min_spend: string | null
  note: string | null
  order_uuid: string | null
  deposit_total: string
  deposit_available: string
  deposit_unsettled: boolean
  items_count: number
  cancel_reason: string | null
  version: number
  created_by: number | null
  confirmed_at: string | null
  arrived_at: string | null
  completed_at: string | null
  canceled_at: string | null
  created_at: string | null
  updated_at: string | null
  // P3-38: when and how the guest was last sent the confirmation
  confirmation_sent_at?: string | null
  confirmation_channel?: string | null
}

const LABELS: Record<string, string> = { requested: "So'rov", confirmed: 'Tasdiqlangan', canceled: 'Bekor qilindi' }

const instant = (value: unknown): string => {
  const text = String(value)
  // Without an offset the store's time zone (UTC+5)
  const hasZone = /(Z|[+-]\d{2}:\d{2})$/.test(text)
  const ms = hasZone ? Date.parse(text) : Date.parse(`${text}Z`) - TZ_OFFSET_MS
  return new Date(ms).toISOString()
}

export class FakeBookings {
  bookings: FakeBooking[] = []
  // P3-38: what a confirmation send can use now (a test switches them to reach `no_channel`)
  channels = { telegram: true, sms: false }
  // The messages queued by a send, newest last
  sent: { uuid: string; channel: string }[] = []
  private counter = 0

  constructor(private readonly backend: FakeBackend) {}

  private inStoreDay(iso: string): string {
    return new Date(Date.parse(iso) + TZ_OFFSET_MS).toISOString().slice(0, 10)
  }

  private conflicts(tableIds: number[], startsAt: string, endsAt: string, except?: string): FakeBooking[] {
    return this.bookings.filter(
      (booking) =>
        booking.uuid !== except &&
        ['requested', 'confirmed', 'arrived'].includes(booking.status) &&
        booking.table_ids.some((id) => tableIds.includes(id)) &&
        Date.parse(booking.starts_at) < Date.parse(endsAt) &&
        Date.parse(booking.ends_at) > Date.parse(startsAt),
    )
  }

  // A booking made before the page opens (what a test starts from); `input` is the body of POST /v1/pos/bookings
  seed(input: Record<string, unknown>): FakeBooking {
    const reply = this.create(input)
    if (reply.status !== 201) throw new Error(`Could not seed the booking: ${JSON.stringify(reply.body)}`)
    return this.bookings[this.bookings.length - 1]!
  }

  private create(input: Record<string, unknown>): Reply {
    const errors: Record<string, string[]> = {}
    if (String(input.contact_name ?? '').trim() === '') errors.contact_name = ['Ismini kiriting.']
    if (Object.keys(errors).length > 0) return fail(422, 'The given data was invalid.', errors)
    const startsAt = instant(input.starts_at)
    const endsAt = input.ends_at ? instant(input.ends_at) : new Date(Date.parse(startsAt) + 5 * 3_600_000).toISOString()
    const tableIds = [input.dining_table_id, ...((input.extra_table_ids as number[] | undefined) ?? [])].filter((id): id is number => typeof id === 'number')
    const clash = this.conflicts(tableIds, startsAt, endsAt)
    if (clash.length > 0) {
      return fail(409, 'Xona shu vaqtda band.', undefined, { reason: 'booking_conflict', conflicts: clash.map((booking) => ({ uuid: booking.uuid, status: booking.status, starts_at: booking.starts_at, ends_at: booking.ends_at, table_ids: booking.table_ids })) })
    }
    const status = input.confirm === true ? 'confirmed' : 'requested'
    const booking: FakeBooking = {
      uuid: `b0000000-0000-4000-8000-${String(++this.counter).padStart(12, '0')}`,
      branch_id: Number(input.branch_id ?? 1),
      dining_table_id: typeof input.dining_table_id === 'number' ? input.dining_table_id : null,
      table_ids: tableIds,
      starts_at: startsAt,
      ends_at: endsAt,
      guests_planned: typeof input.guests_planned === 'number' ? input.guests_planned : 1,
      event_type: String(input.event_type ?? 'other'),
      event_type_label: String(input.event_type ?? 'other'),
      contact_name: String(input.contact_name),
      contact_phone: typeof input.contact_phone === 'string' ? input.contact_phone : null,
      source: String(input.source ?? 'phone'),
      source_label: String(input.source ?? 'phone'),
      status,
      status_label: LABELS[status] ?? status,
      min_spend: input.min_spend === null || input.min_spend === undefined ? null : Number(input.min_spend).toFixed(2),
      note: typeof input.note === 'string' ? input.note : null,
      order_uuid: null,
      deposit_total: '0.00',
      deposit_available: '0.00',
      deposit_unsettled: false,
      items_count: 0,
      cancel_reason: null,
      version: 1,
      created_by: this.backend.account?.id ?? null, // (a seeded booking is made before anyone signs in)
      confirmed_at: status === 'confirmed' ? new Date().toISOString() : null,
      arrived_at: null,
      completed_at: null,
      canceled_at: null,
      created_at: new Date().toISOString(),
      updated_at: null,
    }
    this.bookings.push(booking)
    return created({ ...booking, items: [], payments: [] })
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    const input = body ?? {}
    const path = req.path
    let match: RegExpExecArray | null
    if (req.method === 'GET' && path === '/v1/pos/bookings/calendar') {
      const from = req.query.from ?? this.inStoreDay(new Date().toISOString())
      const to = req.query.to ?? from
      // Laravel's `boolean` rule: only 1 and 0 in a query string ("true"/"false" is a 422)
      if (req.query.include_canceled !== undefined && !['0', '1'].includes(req.query.include_canceled)) {
        return fail(422, 'The given data was invalid.', { include_canceled: ['include_canceled true yoki false bo\'lishi kerak.'] })
      }
      const shown = this.bookings.filter((booking) => {
        if (booking.status === 'canceled' && req.query.include_canceled !== '1') return false
        return this.inStoreDay(booking.starts_at) <= to && this.inStoreDay(booking.ends_at) >= from
      })
      const rooms = this.backend.floor.tables.map((table) => ({
        id: table.id,
        name: table.name,
        kind: table.kind,
        seats: table.seats,
        hall_id: table.hall_id,
        hall_name: this.backend.floor.halls.find((hall) => hall.id === table.hall_id)?.name ?? null,
        is_active: table.is_active,
        min_spend: table.min_spend,
        bookings: shown.filter((booking) => booking.table_ids.includes(table.id)),
      }))
      return ok({ branch_id: Number(req.query.branch_id ?? 1), from, to, timezone: 'Asia/Tashkent', rooms, unassigned: shown.filter((booking) => booking.table_ids.length === 0) })
    }
    if (req.method === 'POST' && path === '/v1/pos/bookings') return this.create(input)
    if ((match = /^\/v1\/pos\/bookings\/([\w-]+)\/confirmation(\/send)?$/.exec(path))) {
      const booking = this.bookings.find((candidate) => candidate.uuid === match![1])
      if (!booking) return fail(404, 'Bron topilmadi.')
      if (req.method === 'GET' && !match[2]) {
        const room = this.backend.floor.tables.find((table) => table.id === booking.dining_table_id)
        const text = ['Bron tasdiqlandi ✅', `Xona: "${room?.name ?? 'keyin belgilanadi'}" · ${booking.guests_planned} kishi`, "💵 Xona puli: 300 000 so'm (dam olish kuni narxi)"].join('\n')
        return ok({ text, lines: [], room_fee: room ? { amount: '300000.00', day_type: 'weekend', tariff_id: 1 } : null, channels: this.channels })
      }
      if (req.method === 'POST' && match[2]) {
        const wanted = String(input.channel ?? 'auto')
        const channel = wanted !== 'auto' ? wanted : this.channels.telegram ? 'telegram' : this.channels.sms ? 'sms' : null
        if (channel === null) return fail(422, "Mehmonga yuborish uchun kanal yo'q.", undefined, { reason: 'no_channel' })
        booking.confirmation_sent_at = new Date().toISOString()
        booking.confirmation_channel = channel
        this.sent.push({ uuid: booking.uuid, channel })
        return ok({ channel, queued: true })
      }
    }
    if ((match = /^\/v1\/pos\/bookings\/([\w-]+)(\/(confirm|cancel))?$/.exec(path))) {
      const booking = this.bookings.find((candidate) => candidate.uuid === match![1])
      if (!booking) return fail(404, 'Bron topilmadi.')
      if (req.method === 'GET' && !match[2]) return ok({ ...booking, items: [], payments: [] })
      if (req.method === 'POST' && match[3] === 'confirm') {
        booking.status = 'confirmed'
        booking.status_label = LABELS.confirmed!
        booking.version += 1
        return ok({ ...booking, items: [], payments: [] })
      }
      if (req.method === 'POST' && match[3] === 'cancel') {
        if (String(input.reason ?? '').trim() === '') return fail(422, 'The given data was invalid.', { reason: ['Bekor qilish sababini yozing.'] })
        booking.status = 'canceled'
        booking.status_label = LABELS.canceled!
        booking.cancel_reason = String(input.reason)
        booking.version += 1
        return ok({ ...booking, items: [], payments: [] })
      }
    }
    return null
  }
}
