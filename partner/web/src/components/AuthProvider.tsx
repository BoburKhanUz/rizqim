'use client';

import { useQueryClient } from '@tanstack/react-query';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { getApi, getSession, logout as endSession, refreshSession, subscribeSession, type Session } from '@/lib/session/client';

export interface Permissions {
  role: string;
  permissions: string[];
  branchScoped: boolean;
}

export type AuthState =
  | { status: 'loading' }
  | { status: 'anonymous' }
  | { status: 'error' } // the server could not be reached while restoring the session
  | { status: 'ready'; userName: string; access: Permissions };

interface AuthValue {
  state: AuthState;
  // Loads the permissions after a sign-in (the session itself is already set by lib/session/client.ts `login`)
  start: () => void;
  retry: () => void;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthValue | null>(null);

// Restores the session on load (the refresh cookie → a new access token, single-flight), loads the person's
// permissions, and goes back to "anonymous" whenever the session ends (sign-out, a refused refresh).
export function AuthProvider({ apiBaseUrl, children }: { apiBaseUrl: string; children: ReactNode }) {
  const queryClient = useQueryClient();
  const [state, setState] = useState<AuthState>({ status: 'loading' });
  // A newer load wins over an older one still running
  const generation = useRef(0);

  const loadAccess = useCallback(
    async (session: Session) => {
      const id = ++generation.current;
      const { data, error } = await getApi(apiBaseUrl).GET('/v1/me/permissions');
      if (id !== generation.current) return;
      if (!data) {
        // A 401 here already ended the session (subscribeSession below); anything else is a failure to retry
        setState((previous) => (previous.status === 'anonymous' ? previous : { status: error ? 'error' : 'anonymous' }));
        return;
      }
      setState({
        status: 'ready',
        userName: session.user?.name ?? '',
        access: { role: data.data.role, permissions: data.data.permissions, branchScoped: data.data.branch_scoped },
      });
    },
    [apiBaseUrl],
  );

  const restore = useCallback(async () => {
    setState({ status: 'loading' });
    const outcome = await refreshSession();
    if (outcome.status === 'ok') await loadAccess(outcome.session);
    else setState({ status: outcome.status === 'denied' ? 'anonymous' : 'error' });
  }, [loadAccess]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- restoring the session is the effect's job
    void restore();
  }, [restore]);

  useEffect(
    () =>
      subscribeSession((session) => {
        if (!session) {
          generation.current++;
          // The next person to sign in on this screen must not see the previous one's orders
          queryClient.clear();
          setState({ status: 'anonymous' });
        }
      }),
    [queryClient],
  );

  const value = useMemo<AuthValue>(
    () => ({
      state,
      start: () => {
        const session = getSession();
        if (!session) return;
        setState({ status: 'loading' });
        void loadAccess(session);
      },
      retry: () => void restore(),
      logout: async () => {
        generation.current++;
        await endSession();
        setState({ status: 'anonymous' });
      },
    }),
    [state, loadAccess, restore],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValue {
  const value = useContext(AuthContext);
  if (!value) throw new Error('useAuth must be used inside <AuthProvider>');
  return value;
}
