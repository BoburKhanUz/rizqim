'use client';

import { useState } from 'react';
import { Clock, Plus } from 'lucide-react';
import { Button, Card, CardSkeletons, Dialog, EmptyState, Field, fieldClass, formatDateTime, InlineError } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { ErrorBanner } from '../menu/ErrorBanner';
import { describeError } from '../menu/serverError';
import { DateField } from '../pos/fields';
import { formatDay } from '../reports/format';
import { DataTable, Pill, Td, Th } from '../reports/ui';
import type { StaffMember, WorkSession } from './api';
import { formatMinutes, fromLocalInput, todayIso, toLocalInput } from './describe';
import { useRemoveSession, useRules, useSaveSession, useSessions } from './hooks';

// "Ish vaqti": when each person came and went (their own clock-in/out on the till, or a manager's entry with a reason),
// by business day. A session left open on an earlier day is set aside ("Unutilgan") until a manager gives it an end.
export function WorkTimePanel({ branchId, canManage }: { branchId: number; canManage: boolean }) {
  const { t, language } = useI18n();
  const [from, setFrom] = useState(() => todayIso(-6));
  const [to, setTo] = useState(() => todayIso());
  const sessions = useSessions(from !== '' && to !== '' ? { branchId, from, to } : null);
  const rules = useRules(branchId);
  const [editing, setEditing] = useState<WorkSession | 'new' | null>(null);
  const [removing, setRemoving] = useState<WorkSession | null>(null);
  const staff = rules.data?.staff ?? [];

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end gap-4">
        <DateField label={t('dm_from')} value={from} max={to || undefined} onChange={setFrom} />
        <DateField label={t('dm_to')} value={to} min={from || undefined} onChange={setTo} />
        {canManage && (
          <Button icon={Plus} onClick={() => setEditing('new')} disabled={staff.length === 0}>
            {t('sp_sessionAdd')}
          </Button>
        )}
      </div>
      <p className="text-footnote text-fg-muted">{t('sp_workTimeHint')}</p>
      {sessions.isPending ? (
        <CardSkeletons count={2} lines={3} label={t('loading')} />
      ) : sessions.isError ? (
        <InlineError title={t('loadErrorTitle')} message={describeError(sessions.error, t).message} retryLabel={t('retry')} retrying={sessions.isFetching} onRetry={() => void sessions.refetch()} />
      ) : sessions.data.length === 0 ? (
        <EmptyState icon={Clock} title={t('sp_sessionsEmptyTitle')} text={t('sp_sessionsEmptyText')} />
      ) : (
        <Card>
          <DataTable caption={t('sp_tabWorkTime')}>
            <thead>
              <tr>
                <Th>{t('sp_colPerson')}</Th>
                <Th>{t('sp_colDay')}</Th>
                <Th>{t('sp_colIn')}</Th>
                <Th>{t('sp_colOut')}</Th>
                <Th numeric>{t('sp_colDuration')}</Th>
                <Th>{t('sp_colSource')}</Th>
                {canManage && <Th />}
              </tr>
            </thead>
            <tbody>
              {sessions.data.map((session) => (
                <tr key={session.uuid}>
                  <Td className="font-bold">{session.user_name}</Td>
                  <Td className="whitespace-nowrap">{formatDay(session.business_date, language)}</Td>
                  <Td muted className="whitespace-nowrap">{formatDateTime(session.clock_in_at, language)}</Td>
                  <Td muted className="whitespace-nowrap">
                    {session.clock_out_at ? formatDateTime(session.clock_out_at, language) : session.abandoned ? <Pill tone="warning">{t('sp_sessionAbandoned')}</Pill> : <Pill tone="info">{t('sp_sessionOpen')}</Pill>}
                  </Td>
                  <Td numeric>{session.clock_out_at ? formatMinutes(session.minutes, t) : '—'}</Td>
                  <Td muted>
                    {t(session.source === 'manual' ? 'sp_sourceManual' : 'sp_sourcePin')}
                    {session.note ? <span className="block text-caption">{session.note}</span> : null}
                  </Td>
                  {canManage && (
                    <Td className="whitespace-nowrap text-right">
                      <Button variant="secondary" onClick={() => setEditing(session)}>
                        {t('sp_sessionFix')}
                      </Button>{' '}
                      <Button variant="ghost" onClick={() => setRemoving(session)}>
                        {t('sp_delete')}
                      </Button>
                    </Td>
                  )}
                </tr>
              ))}
            </tbody>
          </DataTable>
        </Card>
      )}
      {editing !== null && <SessionDialog branchId={branchId} staff={staff} session={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
      {removing !== null && <RemoveDialog session={removing} onClose={() => setRemoving(null)} />}
    </div>
  );
}

function SessionDialog({ branchId, staff, session, onClose }: { branchId: number; staff: StaffMember[]; session: WorkSession | null; onClose: () => void }) {
  const { t } = useI18n();
  const save = useSaveSession({ onSuccess: onClose });
  const [userId, setUserId] = useState<number>(session?.user_id ?? staff[0]?.id ?? 0);
  const [clockIn, setClockIn] = useState(toLocalInput(session?.clock_in_at ?? null));
  const [clockOut, setClockOut] = useState(toLocalInput(session?.clock_out_at ?? null));
  const [reason, setReason] = useState('');
  const [problem, setProblem] = useState<Record<string, string>>({});
  const failure = save.error ? describeError(save.error, t) : null;
  const fieldError = (name: string) => problem[name] ?? failure?.fields[name];

  const submit = () => {
    const found: Record<string, string> = {};
    const inIso = fromLocalInput(clockIn);
    if (inIso === null) found.clock_in_at = t('menu_err_required');
    if (reason.trim() === '') found.reason = t('menu_err_required');
    setProblem(found);
    if (Object.keys(found).length > 0 || inIso === null) return;
    save.mutate({ uuid: session?.uuid ?? null, branchId, userId, clockInAt: inIso, clockOutAt: fromLocalInput(clockOut), reason: reason.trim() });
  };

  return (
    <Dialog
      open
      onClose={onClose}
      title={session === null ? t('sp_sessionAdd') : t('sp_sessionFixTitle', { name: session.user_name ?? '' })}
      description={t('sp_sessionDialogHint')}
      busy={save.isPending}
      closeLabel={t('menu_close')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            {t('menu_cancel')}
          </Button>
          <Button loading={save.isPending} onClick={submit}>
            {t('sp_save')}
          </Button>
        </>
      }
    >
      {session === null && (
        <Field label={t('sp_person')} error={fieldError('user_id')}>
          {(control) => (
            <select {...control} className={fieldClass} value={userId} onChange={(event) => setUserId(Number(event.target.value))}>
              {staff.map((member) => (
                <option key={member.id} value={member.id}>
                  {member.name} · {member.role_label}
                </option>
              ))}
            </select>
          )}
        </Field>
      )}
      <Field label={t('sp_clockIn')} error={fieldError('clock_in_at')}>
        {(control) => <input {...control} type="datetime-local" className={fieldClass} value={clockIn} onChange={(event) => setClockIn(event.target.value)} />}
      </Field>
      <Field label={t('sp_clockOut')} error={fieldError('clock_out_at')} hint={t('sp_clockOutHint')}>
        {(control) => <input {...control} type="datetime-local" className={fieldClass} value={clockOut} onChange={(event) => setClockOut(event.target.value)} />}
      </Field>
      <Field label={t('sp_reason')} error={fieldError('reason')}>
        {(control) => <input {...control} className={fieldClass} maxLength={500} value={reason} onChange={(event) => setReason(event.target.value)} />}
      </Field>
      {failure && <ErrorBanner message={failure.message} />}
    </Dialog>
  );
}

function RemoveDialog({ session, onClose }: { session: WorkSession; onClose: () => void }) {
  const { t } = useI18n();
  const remove = useRemoveSession({ onSuccess: onClose });
  const [reason, setReason] = useState('');
  const failure = remove.error ? describeError(remove.error, t) : null;

  return (
    <Dialog
      open
      onClose={onClose}
      title={t('sp_sessionRemoveTitle', { name: session.user_name ?? '' })}
      busy={remove.isPending}
      closeLabel={t('menu_close')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={remove.isPending}>
            {t('menu_cancel')}
          </Button>
          <Button variant="danger" loading={remove.isPending} disabled={reason.trim() === ''} onClick={() => remove.mutate({ uuid: session.uuid, reason: reason.trim() })}>
            {t('sp_delete')}
          </Button>
        </>
      }
    >
      <Field label={t('sp_reason')} error={failure?.fields.reason}>
        {(control) => <input {...control} data-autofocus className={fieldClass} maxLength={500} value={reason} onChange={(event) => setReason(event.target.value)} />}
      </Field>
      {failure && <ErrorBanner message={failure.message} />}
    </Dialog>
  );
}
