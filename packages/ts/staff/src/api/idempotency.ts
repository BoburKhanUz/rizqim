// The POS endpoints (bookings, deposits, customer accounts, shifts) take an `Idempotency-Key` header: the same key on a
// retry gives the first answer back and nothing is written twice. Deposits and repayments REQUIRE it (400
// `idempotency_key_required` without). A screen makes one key per user action and reuses it when it retries that action.
export const IDEMPOTENCY_HEADER = 'Idempotency-Key';

export function newIdempotencyKey(): string {
  const random = globalThis.crypto?.randomUUID?.();
  return random ?? `k-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
}

// The request options that carry a key: `{ headers: { 'Idempotency-Key': key } }`
export const withIdempotency = (key: string = newIdempotencyKey()) => ({ headers: { [IDEMPOTENCY_HEADER]: key } });
