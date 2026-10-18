import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface User {
  id: number;
  name: string;
  telegram_id: string;
  role: string;
  telegram_username?: string;
  telegram_photo_url?: string;
  phone?: string;
  address?: string;
  balance?: number;
}

interface Store {
  id: number;
  uuid?: string;
  name: string;
  telegram_bot_username: string;
  payment_settings?: any;
  branches?: any[];
}

interface AuthState {
  token: string | null;
  user: User | null;
  store: Store | null;
  isAuthenticated: boolean;
  smsVerificationRequired: boolean;
  setAuth: (token: string, user: User, store: Store, smsVerificationRequired?: boolean) => void;
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
        set({ token, user, store, isAuthenticated: true, smsVerificationRequired }),
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
