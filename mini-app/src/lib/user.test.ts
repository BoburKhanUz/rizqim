import { describe, expect, it } from 'vitest';
import { makeUser } from '../test/factories';
import { normalizeUser } from './user';

describe('normalizeUser', () => {
  it('turns the DECIMAL balance string of the API into a number', () => {
    expect(normalizeUser(makeUser({ balance: '125000.00' })).balance).toBe(125000);
    expect(normalizeUser(makeUser({ balance: '0.00' })).balance).toBe(0);
    expect(normalizeUser(makeUser({ balance: 4500 })).balance).toBe(4500);
  });

  it('keeps every other field as it came', () => {
    const raw = makeUser({ name: 'Vali', role: 'owner', phone: '+998901234567' });
    const user = normalizeUser(raw);

    expect(user).toMatchObject({ id: raw.id, name: 'Vali', role: 'owner', phone: '+998901234567', telegram_id: raw.telegram_id });
  });
});
