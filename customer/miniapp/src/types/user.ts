import type { Decimal } from './api';
import type { Branch, Store } from './store';

// Mirrors App\Enums\UserRole.
export type UserRole = 'owner' | 'manager' | 'courier' | 'customer';

// A user exactly as the API serializes it (App\Models\User; `password` and `remember_token` are hidden).
export interface ApiUser {
  id: number;
  uuid: string;
  name: string;
  email: string | null;
  telegram_id: string | null; // `temp_...` for an employee that has not joined through the invite link yet
  telegram_username: string | null;
  telegram_photo_url: string | null;
  phone: string | null;
  phone_verified_at: string | null;
  address: string | null;
  role: UserRole;
  store_id: number | null;
  branch_id: number | null;
  balance: Decimal; // cashback points (a DECIMAL column)
  referred_by: number | null;
  telegram_invite_token: string | null; // set while an invited employee/owner has not joined yet
  created_at: string;
  updated_at: string;
  branch?: Branch | null; // only when the endpoint eager-loads it
}

// The signed-in user kept in the auth store: the API user with `balance` normalised to a number.
export type User = Omit<ApiUser, 'balance'> & { balance?: number };

// GET /user
export interface AuthPayload {
  user: ApiUser;
  store: Store | null; // null for accounts without a store (super admins)
  sms_verification_required: boolean;
}

// POST /auth/telegram
export interface LoginPayload extends AuthPayload {
  token: string;
}

// POST /phone/send-code. `code` is only filled on a local backend that is set up to expose it.
export interface SendCodeResponse {
  success: boolean;
  message?: string;
  code?: string | null;
}

// POST /phone/verify-code. The number is already saved on the account when `success` is true.
export interface VerifyCodeResponse {
  success: boolean;
  message?: string;
  user?: ApiUser | null;
}
