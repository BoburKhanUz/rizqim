import { describe, expect, it } from 'vitest';
import { createCheckoutAttempt, newIdempotencyKey } from './idempotency';

describe('newIdempotencyKey', () => {
  it('is a UUID the server accepts (1-255 visible characters)', () => {
    const key = newIdempotencyKey();
    expect(key).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    expect(newIdempotencyKey()).not.toBe(key);
  });
});

describe('createCheckoutAttempt', () => {
  const counter = () => {
    let n = 0;
    return () => `key-${++n}`;
  };

  it('reuses the key for a retry of the same order', () => {
    const attempt = createCheckoutAttempt(counter());
    const body = { items: [{ variant_id: 'v', quantity: 1 }], type: 'pickup' };
    expect(attempt.keyFor(body)).toBe('key-1');
    expect(attempt.keyFor({ ...body })).toBe('key-1');
  });

  it('takes a new key when the order changed (the server answers 409 for another body under the same key)', () => {
    const attempt = createCheckoutAttempt(counter());
    expect(attempt.keyFor({ quantity: 1 })).toBe('key-1');
    expect(attempt.keyFor({ quantity: 2 })).toBe('key-2');
  });

  it('starts a new attempt after the order was placed', () => {
    const attempt = createCheckoutAttempt(counter());
    attempt.keyFor({ a: 1 });
    attempt.finish();
    expect(attempt.keyFor({ a: 1 })).toBe('key-2');
  });
});
