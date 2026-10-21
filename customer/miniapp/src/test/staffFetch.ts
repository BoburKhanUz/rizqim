import { vi } from 'vitest';

// A fake backend for the screens that use the staff API layer (@rizqim/staff), which talks over fetch. The tests give
// it one handler for every request; the handler sees the call the way the old axios mocks did (the path without the
// /api/v1 prefix, the query as an object) and answers a JSON body (200), a `reply(status, body)`, or throws to fail the
// way a dropped connection does.

export interface FakeCall {
  method: string;
  path: string; // "/orders", without the /api/v1 prefix and the query string
  query: Record<string, string>;
  body: unknown; // the parsed JSON body, a FormData for a multipart one, undefined without a body
  authorization: string | null;
}

export class FakeReply {
  status: number;
  body: unknown;
  constructor(status: number, body: unknown) {
    this.status = status;
    this.body = body;
  }
}

// An answer with a status: reply(422, { message: 'Xato', errors: {...} })
export const reply = (status: number, body: unknown = {}) => new FakeReply(status, body);

// The usual envelope of a successful answer
export const success = (data: unknown, extra: Record<string, unknown> = {}) => ({ success: true, message: '', data, ...extra });

type Handler = (call: FakeCall) => unknown | Promise<unknown>;

// Replaces the global fetch. Returns the calls made so far (a live array).
export function stubStaffApi(handler: Handler) {
  const calls: FakeCall[] = [];
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const request = input instanceof Request ? input : new Request(input, init);
    const url = new URL(request.url);
    const contentType = request.headers.get('Content-Type') ?? '';
    let body: unknown;
    if (request.method !== 'GET' && request.method !== 'DELETE') {
      if (contentType.startsWith('multipart/form-data')) body = await request.clone().formData();
      else {
        const text = await request.clone().text();
        body = text ? JSON.parse(text) : undefined;
      }
    }
    const call: FakeCall = {
      method: request.method,
      path: url.pathname.replace(/^\/api\/v1/, ''),
      query: Object.fromEntries(url.searchParams),
      body,
      authorization: request.headers.get('Authorization'),
    };
    calls.push(call);
    const answer = await handler(call);
    const { status, payload } = answer instanceof FakeReply ? { status: answer.status, payload: answer.body } : { status: 200, payload: answer };
    return new Response(JSON.stringify(payload), { status, headers: { 'Content-Type': 'application/json' } });
  });
  vi.stubGlobal('fetch', fetchMock);
  return { calls, fetchMock };
}

// The calls to one path (and method)
export const callsTo = (calls: readonly FakeCall[], path: string, method?: string) =>
  calls.filter((call) => call.path === path && (method === undefined || call.method === method));
