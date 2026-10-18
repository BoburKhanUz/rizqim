import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { normalizeUser } from '../lib/user';
import type { ApiUser, Store, User } from '../types';

interface AuthState {
  token: string | null;
  user: User | null;
  store: Store | null;
  isAuthenticated: boolean;
  smsVerificationRequired: boolean;
  setAuth: (token: string, user: ApiUser, store: Store | null, smsVerificationRequired?: boolean) => void;
  setSmsVerificationRequired: (required: boolean) => void;
  updateUser: (userData: Partial<User>) => void;
  logout: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      store: null,
      isAuthenticated: false,
      smsVerificationRequired: false,
      setAuth: (token, user, store, smsVerificationRequired = false) =>
        set({ token, user: normalizeUser(user), store, isAuthenticated: true, smsVerificationRequired }),
      setSmsVerificationRequired: (required) => set({ smsVerificationRequired: required }),
      updateUser: (userData) => set((state) => ({
        user: state.user ? { ...state.user, ...userData } : null
      })),
      logout: () => set({ token: null, user: null, store: null, isAuthenticated: false, smsVerificationRequired: false }),
    }),
    {
      name: 'auth-storage', // saves to localStorage
    }
  )
);
