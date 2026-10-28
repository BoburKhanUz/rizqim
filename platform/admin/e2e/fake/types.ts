// What the fake admin backend gets and answers (the HTTP face is in server.ts)
export interface FakeRequest {
  method: string
  /** The path below /api (e.g. /v1/admin/team/2) */
  path: string
  query: Record<string, string>
  body: Record<string, unknown> | null
  authorization: string | null
  /** P4-23: the request headers the fakes read (lower-case names: idempotency-key) */
  headers?: Record<string, string>
}

export interface Reply {
  status: number
  body?: unknown
  headers?: Record<string, string>
  /** P4-23: a file answer (the Excel export): these bytes as they are, with `headers` (Content-Type, Content-Disposition) */
  raw?: Uint8Array
}

export interface RecordedCall {
  method: string
  /** The path below /v1/admin (e.g. /team/2) */
  path: string
  body: Record<string, unknown> | null
  query: Record<string, string>
  status: number
  authorization: string | null
}

export const ok = (data: unknown, extra: Record<string, unknown> = {}): Reply => ({ status: 200, body: { success: true, message: '', data, ...extra } })
// The admin envelope of a refusal: {success: false, message, code, data: {reason}} (App\Http\Controllers\Api\V1\Admin\AdminController::refuse)
export const fail = (status: number, message: string, data: Record<string, unknown> = {}, extra: Record<string, unknown> = {}): Reply => ({
  status,
  body: { success: false, message, ...(typeof data.reason === 'string' ? { code: data.reason } : {}), data, ...extra },
})
export const validation = (field: string, message: string): Reply => fail(422, message, { reason: 'validation' }, { errors: { [field]: [message] } })
