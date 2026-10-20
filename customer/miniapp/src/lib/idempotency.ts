// One Idempotency-Key per checkout attempt (docs/MENU.md, "Idempotency kaliti"): POST /orders sent again with the same
// key and the same body returns the first order instead of placing another, so a retry after a dropped connection can
// never double-order. The server answers 409 for the same key with a different body, so a changed order (another
// cart, address or payment) is a new attempt with a new key. A failed request frees the key on the server, so the
// retry of an attempt that was refused (a 422) can reuse it too.

export function newIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
  // Not a secure context: 16 random bytes in the shape of a UUID v4
  const bytes = new Uint8Array(16);
  if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') crypto.getRandomValues(bytes);
  else bytes.forEach((_, index) => (bytes[index] = Math.floor(Math.random() * 256)));
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export interface CheckoutAttempt {
  // The key of the attempt that sends `body`: the same one while the body is the same, a fresh one when it changed
  keyFor: (body: unknown) => string;
  // The attempt is over (the order was placed): the next order starts a new one
  finish: () => void;
}

export function createCheckoutAttempt(makeKey: () => string = newIdempotencyKey): CheckoutAttempt {
  let current: { key: string; fingerprint: string } | null = null;

  return {
    keyFor: (body) => {
      const fingerprint = JSON.stringify(body);
      if (current === null || current.fingerprint !== fingerprint) current = { key: makeKey(), fingerprint };
      return current.key;
    },
    finish: () => {
      current = null;
    },
  };
}
