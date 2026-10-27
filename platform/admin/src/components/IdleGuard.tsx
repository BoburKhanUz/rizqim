'use client';

import { useEffect, useRef, useState } from 'react';
import { Clock } from 'lucide-react';
import { Button, Dialog } from '@rizqim/ui';
import { getLastCallAt } from '@/lib/api/client';
import { getMe } from '@/lib/api/endpoints';
import { clock } from '@/lib/format';
import { useAuth } from './AuthProvider';
import { useT } from './I18nProvider';

// The backend ends a session 60 minutes after its last call (and after 12 hours in all). At 55 minutes without a call the member
// is warned and can stay (one cheap call renews the idle timer); at 60 the screen signs out on its own. Thresholds are props
// so the tests do not wait an hour.
export const IDLE_WARN_MS = 55 * 60_000;
export const IDLE_LIMIT_MS = 60 * 60_000;

export function IdleGuard({ warnAfterMs = IDLE_WARN_MS, limitMs = IDLE_LIMIT_MS }: { warnAfterMs?: number; limitMs?: number }) {
  const t = useT();
  const { logout } = useAuth();
  // Seconds left before sign-out while the warning shows, else null
  const [remaining, setRemaining] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  // The sign-out is asked for once, however many ticks pass before the screen reacts
  const leaving = useRef(false);

  useEffect(() => {
    const tick = () => {
      const idle = Date.now() - getLastCallAt();
      if (idle >= limitMs) {
        setRemaining(null);
        if (!leaving.current) {
          leaving.current = true;
          void logout('idle');
        }
      } else if (idle >= warnAfterMs) {
        setRemaining(Math.ceil((limitMs - idle) / 1000));
      } else {
        setRemaining((previous) => (previous === null ? previous : null));
      }
    };
    const timer = window.setInterval(tick, 1000);
    return () => window.clearInterval(timer);
  }, [warnAfterMs, limitMs, logout]);

  async function stay() {
    setBusy(true);
    try {
      await getMe();
      setRemaining(null);
    } catch {
      // A 401 already ended the session (AuthProvider); anything else: the warning stays and can be tried again
    } finally {
      setBusy(false);
    }
  }

  if (remaining === null) return null;
  return (
    <Dialog
      open
      variant="center"
      role="alertdialog"
      title={t('auth_idleTitle')}
      description={t('auth_idleText', { time: clock(remaining) })}
      busy={busy}
      hideClose
      onClose={() => undefined}
      footer={
        <>
          <Button variant="secondary" onClick={() => void logout()} disabled={busy}>
            {t('auth_idleLogout')}
          </Button>
          <Button icon={Clock} loading={busy} onClick={() => void stay()} data-autofocus>
            {t('auth_idleStay')}
          </Button>
        </>
      }
    />
  );
}
