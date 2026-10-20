import type { ApiUser, User } from '../types';
import { toAmount } from './money';

// The API sends the cashback balance as a DECIMAL string ("125000.00"); the app compares and formats it as a number.
export function normalizeUser(raw: ApiUser): User {
  const { balance, ...rest } = raw;
  return {
    ...rest,
    balance: balance === undefined || balance === null ? undefined : toAmount(balance),
  };
}
