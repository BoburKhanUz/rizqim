// The request the fake backend answers and its reply. The HTTP layer (server.ts) turns a real request into a
// FakeRequest and the reply back into a response; the backend itself (backend.ts) is plain, synchronous TypeScript.
export interface FakeRequest {
  method: string
  // /v1/... (the /api prefix is already cut off), without the query string
  path: string
  query: Record<string, string>
  // JSON bodies are objects; a multipart body (the product form) is a FormData; no body is null
  body: Record<string, unknown> | FormData | null
  authorization: string | null
}

export interface Reply {
  status: number
  body: unknown
  headers?: Record<string, string>
  // A reply that is not JSON (the CSV export)
  raw?: string
}

export interface RecordedCall {
  method: string
  path: string
  query: Record<string, string>
  body: Record<string, unknown> | null
  status: number
}

// The {success, message, data} envelope of App\Traits\ApiResponse
export const ok = (data: unknown, extra: Record<string, unknown> = {}): Reply => ({ status: 200, body: { success: true, message: 'ok', data, ...extra } })
export const created = (data: unknown): Reply => ({ status: 201, body: { success: true, message: 'ok', data } })
export const fail = (status: number, message: string, errors?: Record<string, string[]>, data?: unknown): Reply => ({
  status,
  body: { success: false, message, ...(errors ? { errors } : {}), ...(data === undefined ? {} : { data }) },
})
export const unauthenticated = (): Reply => fail(401, 'Unauthenticated.')
