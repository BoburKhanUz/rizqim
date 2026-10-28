'use client';

import { useQueryClient } from '@tanstack/react-query';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { ApiError, markActive, subscribeSessionEnded } from '@/lib/api/client';
import { getMe, logout as endSession } from '@/lib/api/endpoints';
import type { AdminMe } from '@/lib/api/types';
import { hasPermission } from '@/lib/nav';

export type AnonymousReason = 'expired' | 'idle' | null;

export type AuthState =
  | { status: 'loading' }
  | { status: 'anonymous'; reason: AnonymousReason }
  | { status: 'error' } // the server could not be reached while restoring the session
  | { status: 'ready'; me: AdminMe };

interface AuthValue {
  state: AuthState;
  // Loads the member after a sign-in (the session cookie is already set by the proxy)
  start: () => void;
  retry: () => void;
  // Reloads GET /auth/me (after the member's own 2FA or role changed)
  refresh: () => Promise<void>;
  logout: (reason?: AnonymousReason) => Promise<void>;
}

const AuthContext = createContext<AuthValue | null>(null);

// Restores the session on load (the httpOnly cookie → GET /auth/me), and goes back to "anonymous" whenever the session ends
// (sign-out, a 401 on any call: the backend ended it after 60 idle minutes or 12 hours, or another device revoked it).
export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [state, setState] = useState<AuthState>({ status: 'loading' });
  const generation = useRef(0);
  // The latest status, for the "session ended" listener below (it must not touch a sign-in that is still being checked)
  const status = useRef<AuthState['status']>('loading');
  useEffect(() => {
    status.current = state.status;
  }, [state.status]);

  const load = useCallback(async () => {
    const id = ++generation.current;
    try {
      const me = await getMe();
      if (id === generation.current) setState({ status: 'ready', me });
    } catch (failure) {
      if (id !== generation.current) return;
      setState(failure instanceof ApiError && failure.status === 401 ? { status: 'anonymous', reason: null } : { status: 'error' });
    }
  }, []);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- restoring the session is the effect's job
    void load();
  }, [load]);

  useEffect(
    () =>
      subscribeSessionEnded(() => {
        if (status.current !== 'ready') return;
        generation.current++;
        // The next person to sign in on this screen must not see the previous one's data
        queryClient.clear();
        setState({ status: 'anonymous', reason: 'expired' });
      }),
    [queryClient],
  );

  const value = useMemo<AuthValue>(
    () => ({
      state,
      start: () => {
        markActive();
        setState({ status: 'loading' });
        void load();
      },
      retry: () => {
        setState({ status: 'loading' });
        void load();
      },
      refresh: load,
      logout: async (reason = null) => {
        generation.current++;
        try {
          await endSession();
        } catch {
          // Signed out in this browser either way; the cookie expires with the session
        }
        queryClient.clear();
        setState({ status: 'anonymous', reason });
      },
    }),
    [state, load, queryClient],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValue {
  const value = useContext(AuthContext);
  if (!value) throw new Error('useAuth must be used inside <AuthProvider>');
  return value;
}

// The signed-in member (null until loaded, and outside the provider: a test of one page)
export function useMe(): AdminMe | null {
  const value = useContext(AuthContext);
  return value?.state.status === 'ready' ? value.state.me : null;
}

// Whether the member has ANY of the permissions (the backend still checks every request). `ready` is false until the member loads.
export function useCan(): { ready: boolean; can: (...permissions: string[]) => boolean } {
  const me = useMe();
  return useMemo(
    () => ({ ready: me !== null, can: (...permissions: string[]) => me !== null && permissions.some((permission) => hasPermission(me.permissions, permission)) }),
    [me],
  );
}
