'use client';

import { useState } from 'react';
import { KeyRound, MonitorSmartphone, ShieldCheck } from 'lucide-react';
import { Button, Card, CardSkeletons, ConfirmDialog, Dialog, EmptyState, InlineError, Notice, PageHeader, Pill } from '@rizqim/ui';
import { regenerateRecoveryCodes } from '@/lib/api/endpoints';
import { useRevokeSession, useSessions } from '@/lib/api/hooks';
import type { AdminSessionRow } from '@/lib/api/types';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { useAuth, useMe } from '../AuthProvider';
import { RecoveryCodesPanel } from '../auth/RecoveryCodesPanel';
import { useT } from '../I18nProvider';

// A readable device from a User-Agent line ("Chrome, Linux"); the raw line when it is not one we know
export function deviceName(userAgent: string | null, fallback: string): string {
  if (!userAgent) return fallback;
  const browser = /Edg\//.test(userAgent) ? 'Edge' : /OPR\/|Opera/.test(userAgent) ? 'Opera' : /Firefox\//.test(userAgent) ? 'Firefox' : /Chrome\//.test(userAgent) ? 'Chrome' : /Safari\//.test(userAgent) ? 'Safari' : null;
  const system = /Android/.test(userAgent) ? 'Android' : /iPhone|iPad/.test(userAgent) ? 'iOS' : /Windows/.test(userAgent) ? 'Windows' : /Mac OS X|Macintosh/.test(userAgent) ? 'macOS' : /Linux/.test(userAgent) ? 'Linux' : null;
  const name = [browser, system].filter(Boolean).join(', ');
  return name || userAgent.slice(0, 60);
}

// Sessiyalarim: where this account is signed in (each login is one row the member can end), and the 2FA recovery codes
export function SessionsView() {
  const t = useT();
  const me = useMe();
  const { refresh } = useAuth();
  const sessions = useSessions();
  const revoke = useRevokeSession();
  const [asking, setAsking] = useState<AdminSessionRow | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [codes, setCodes] = useState<{ list: string[] } | null>(null);
  const [codesBusy, setCodesBusy] = useState(false);
  const [codesError, setCodesError] = useState<string | null>(null);

  async function newCodes() {
    setCodesBusy(true);
    setCodesError(null);
    try {
      setCodes({ list: await regenerateRecoveryCodes() });
      void refresh();
    } catch (failure) {
      setCodesError(errorText(failure, t));
    } finally {
      setCodesBusy(false);
    }
  }

  return (
    <>
      <PageHeader title={t('sessions_title')} subtitle={t('sessions_subtitle')} />
      <Notice message={notice} />
      <div className="flex max-w-4xl flex-col gap-4">
        {me && (
          <Card className="flex flex-col gap-3">
            <h2 className="flex items-center gap-2 text-headline font-bold text-fg">
              <ShieldCheck aria-hidden="true" className="size-5" />
              {t('sessions_security')}
            </h2>
            <dl className="grid grid-cols-1 gap-x-4 gap-y-2 text-footnote sm:grid-cols-3">
              <div>
                <dt className="text-fg-muted">{t('sessions_twoFactor')}</dt>
                <dd>
                  <Pill tone={me.two_factor_enabled ? 'success' : 'warning'}>{me.two_factor_enabled ? t('sessions_twoFactorOn') : t('sessions_twoFactorOff')}</Pill>
                </dd>
              </div>
              <div>
                <dt className="text-fg-muted">{t('sessions_codesLeft')}</dt>
                <dd className="text-fg">{me.recovery_codes_left}</dd>
              </div>
              <div>
                <dt className="text-fg-muted">{t('sessions_lastLogin')}</dt>
                <dd className="text-fg">{when(me.last_login_at)}</dd>
              </div>
            </dl>
            {codesError && (
              <p role="alert" className="rounded-control bg-danger-soft px-3 py-2 text-footnote font-bold text-danger-soft-fg">
                {codesError}
              </p>
            )}
            <div>
              <Button variant="secondary" icon={KeyRound} loading={codesBusy} onClick={() => void newCodes()}>
                {t('sessions_newCodes')}
              </Button>
            </div>
          </Card>
        )}

        {sessions.isError ? (
          <InlineError title={t('sessions_loadError')} message={errorText(sessions.error, t)} retryLabel={t('retry')} retrying={sessions.isFetching} onRetry={() => void sessions.refetch()} />
        ) : !sessions.isSuccess ? (
          <CardSkeletons count={2} lines={2} label={t('loading')} />
        ) : sessions.data.length === 0 ? (
          <EmptyState icon={MonitorSmartphone} title={t('sessions_empty')} />
        ) : (
          <ul aria-label={t('sessions_title')} className="flex flex-col gap-3">
            {sessions.data.map((session) => (
              <li key={session.id}>
                <Card className="flex flex-col gap-3">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="flex min-w-0 items-center gap-2 text-headline font-bold text-fg">
                      <MonitorSmartphone aria-hidden="true" className="size-5 shrink-0" />
                      <span className="min-w-0 break-words">{deviceName(session.user_agent, t('sessions_unknownDevice'))}</span>
                    </h2>
                    {session.current && <Pill tone="success">{t('sessions_current')}</Pill>}
                  </div>
                  <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-footnote sm:grid-cols-4">
                    <div>
                      <dt className="text-fg-muted">IP</dt>
                      <dd className="break-all text-fg">{session.ip ?? '—'}</dd>
                    </div>
                    <div>
                      <dt className="text-fg-muted">{t('sessions_started')}</dt>
                      <dd className="text-fg">{when(session.created_at)}</dd>
                    </div>
                    <div>
                      <dt className="text-fg-muted">{t('sessions_lastUsed')}</dt>
                      <dd className="text-fg">{when(session.last_used_at)}</dd>
                    </div>
                    <div>
                      <dt className="text-fg-muted">{t('sessions_expires')}</dt>
                      <dd className="text-fg">{when(session.expires_at)}</dd>
                    </div>
                  </dl>
                  {!session.current && (
                    <div>
                      <Button variant="secondary" aria-label={`${t('sessions_revoke')}: ${deviceName(session.user_agent, t('sessions_unknownDevice'))}, ${session.ip ?? ''}`} onClick={() => { setNotice(null); revoke.reset(); setAsking(session); }}>
                        {t('sessions_revoke')}
                      </Button>
                    </div>
                  )}
                </Card>
              </li>
            ))}
          </ul>
        )}
      </div>

      {asking && (
        <ConfirmDialog
          title={t('sessions_revokeTitle')}
          text={t('sessions_revokeText')}
          confirmLabel={t('sessions_revokeConfirm')}
          cancelLabel={t('cancel')}
          closeLabel={t('close')}
          busy={revoke.isPending}
          error={revoke.error ? errorText(revoke.error, t) : null}
          onClose={() => setAsking(null)}
          onConfirm={() =>
            revoke.mutate(asking.id, {
              onSuccess: () => {
                setAsking(null);
                setNotice(t('sessions_revoked'));
              },
            })
          }
        />
      )}

      {codes && (
        <Dialog open title={t('sessions_newCodesTitle')} description={t('sessions_newCodesText')} closeLabel={t('close')} onClose={() => setCodes(null)} footer={<Button onClick={() => setCodes(null)}>{t('auth_recoverySaved')}</Button>}>
          <RecoveryCodesPanel codes={codes.list} />
        </Dialog>
      )}
    </>
  );
}
