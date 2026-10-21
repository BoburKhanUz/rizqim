// A thin client of the real backend API for what the tests do "from outside" the two apps: a customer placing an order,
// the kitchen display's PIN session and bumps. Everything else is done through the apps' own screens.
import { createHmac, randomUUID } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { stack, type Seed } from './env'

interface Envelope<T> {
  success: boolean
  message: string
  data: T
}

export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly path: string,
    readonly body: unknown,
  ) {
    super(`${path}: HTTP ${status} ${JSON.stringify(body)}`)
  }
}

async function call<T>(method: string, path: string, token: string | null, body?: unknown, headers: Record<string, string> = {}): Promise<T> {
  const response = await fetch(`${stack.backend}/api/v1${path}`, {
    method,
    headers: {
      Accept: 'application/json',
      ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  const parsed: unknown = await response.json().catch(() => null)
  if (!response.ok) throw new ApiError(response.status, `${method} ${path}`, parsed)
  return (parsed as Envelope<T>).data
}

// What Telegram hands a Mini App on launch: the user and a hash signed with the bot token (Telegram's documented scheme,
// the same the backend checks in ValidateTelegramInitDataAction). Valid for ten minutes.
export function signedInitData(user: { id: number | string; first_name: string }): string {
  const fields: Record<string, string> = {
    auth_date: String(Math.floor(Date.now() / 1000)),
    query_id: `E2E${randomUUID().slice(0, 8)}`,
    user: JSON.stringify({ id: Number(user.id), first_name: user.first_name, language_code: 'uz' }),
  }
  const check = Object.keys(fields)
    .sort()
    .map((key) => `${key}=${fields[key]}`)
    .join('\n')
  const secret = createHmac('sha256', 'WebAppData').update(stack.botToken).digest()
  const hash = createHmac('sha256', secret).update(check).digest('hex')
  return new URLSearchParams({ ...fields, hash }).toString()
}

export interface PlacedOrder {
  uuid: string
  // The number the apps show ("#A1B2"), without the hash
  number: string
  status: string
}

// A customer signs in the way the Mini App does and places a pickup order (cash) at the seeded branch.
export async function placeCustomerOrder(seed: Seed, lines: Array<{ product: string; quantity: number }>): Promise<PlacedOrder> {
  const auth = await call<{ token: string }>('POST', '/auth/telegram', null, {
    initData: signedInitData({ id: seed.customer.telegram_id, first_name: 'Aziz' }),
    bot_username: stack.botUsername,
  })
  const items = lines.map((line) => {
    const product = seed.products.find((candidate) => candidate.name === line.product)
    if (!product) throw new Error(`The seed has no product ${line.product}`)
    return { variant_id: product.variant_uuid, quantity: line.quantity }
  })
  const created = await call<{ order_uuid: string; order_number: string; status: string }>(
    'POST',
    '/orders',
    auth.token,
    { store_id: seed.store.id, type: 'pickup', branch_id: seed.branch.id, payment_method: 'cash', items },
    { 'Idempotency-Key': randomUUID() },
  )
  return { uuid: created.order_uuid, number: created.order_number, status: created.status }
}

export interface KitchenTicket {
  uuid: string
  status: string
  items: Array<{ id: number; product_name: string; kitchen_status: string }>
  items_total: number
  items_ready: number
}

// The kitchen display's side: the cook signs in on the paired terminal with a PIN, reads the tickets and bumps lines.
export class Kitchen {
  private constructor(private readonly token: string) {}

  static async signIn(seed: Seed): Promise<Kitchen> {
    const session = await call<{ token: string }>('POST', `/devices/${seed.terminal.uuid}/staff-session`, seed.terminal.token, {
      user_id: seed.cook.id,
      pin: seed.cook.pin,
    })
    return new Kitchen(session.token)
  }

  async tickets(): Promise<KitchenTicket[]> {
    const data = await call<{ tickets: KitchenTicket[] } | KitchenTicket[]>('GET', '/staff/kitchen/tickets', this.token)
    return Array.isArray(data) ? data : data.tickets
  }

  async ticket(orderUuid: string): Promise<KitchenTicket> {
    const ticket = (await this.tickets()).find((candidate) => candidate.uuid === orderUuid)
    if (!ticket) throw new Error(`The kitchen has no ticket for order ${orderUuid}`)
    return ticket
  }

  async bump(orderUuid: string, itemId: number): Promise<void> {
    await call('POST', `/staff/orders/${orderUuid}/items/${itemId}/ready`, this.token)
  }
}

// The code the backend "texted" to a phone, read from the log of the log SMS driver (SMS_DRIVER=log): the newest line
// for the phone. Polls briefly, since the line is written while the request that asked for the code answers.
export async function smsCodeFor(phone: string, afterOffset = 0): Promise<string> {
  const deadline = Date.now() + 10_000
  while (Date.now() < deadline) {
    const log = readFileSync(stack.logFile, 'utf8').slice(afterOffset)
    const matches = [...log.matchAll(new RegExp(`SMS Mocked to ${phone.replace('+', '\\+')}: [^\\d]*(\\d{6})`, 'g'))]
    const last = matches.at(-1)
    if (last) return last[1]
    await new Promise((resolve) => setTimeout(resolve, 200))
  }
  throw new Error(`No sign-in code for ${phone} in ${stack.logFile}`)
}

export function logSize(): number {
  try {
    return readFileSync(stack.logFile, 'utf8').length
  } catch {
    return 0
  }
}
