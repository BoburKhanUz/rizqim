'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Card, Notice, Pill, Switch } from '@rizqim/ui';
import { unwrap, useStaffApi, type StaffApi } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { ConfirmDialog } from '../management/ConfirmDialog';
import { errorText } from '../management/errors';
import { Gate } from '../pos/Gate';

// Wave M5 S3-d (docs/API.md "Imzolangan navbat"): the store's mode for the signatures of the tills' offline queue.
// "Kuzatish" (the default): what each signature was worth is only recorded. "Qat'iy": an unsigned or changed operation
// is rejected, a payment is still recorded and marked for the manager. Store-wide (not the chosen branch's). Turning it
// on asks first, and names the tills that do not sign yet. The tills' keys never reach the panel, and there is no
// "renew the key" action: a till renews its own.
//
// The two calls are not in the generated client yet (the spec is regenerated with the stage): typed here.

interface UnsignedTill {
  uuid: string;
  name: string;
  branch_id: number | null;
  // false: no key on the server yet
  has_key: boolean;
  last_sig_status: string | null;
}

interface SyncSigning {
  enforce: boolean;
  signing_tills: number;
  unsigned_tills: UnsignedTill[];
}

interface Result {
  data?: unknown;
  error?: unknown;
  response: Response;
}
interface Door {
  GET(path: string): Promise<Result>;
  PUT(path: string, init: { body: unknown }): Promise<Result>;
}

const PATH = '/v1/owner/pos/sync-signing';
const door = (api: StaffApi): Door => api.client as unknown as Door;
const read = async (promise: Promise<Result>) => (unwrap(await promise) as { data: SyncSigning }).data;

// Under ['owner', ...] like the other owner screens', so a sign-out clears it
const KEY = ['owner', 'pos', 'sync-signing'] as const;

export function SyncSigningPanel() {
  const api = useStaffApi();
  const signing = useQuery({ queryKey: KEY, queryFn: () => read(door(api).GET(PATH)), retry: false });
  return <Gate query={signing} count={1}>{(data) => <SyncSigningCard data={data} />}</Gate>;
}

function SyncSigningCard({ data }: { data: SyncSigning }) {
  const { t } = useI18n();
  const api = useStaffApi();
  const queryClient = useQueryClient();
  const [confirming, setConfirming] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const unsigned = data.unsigned_tills;

  const save = useMutation({
    mutationFn: (enforce: boolean) => read(door(api).PUT(PATH, { body: enforce && unsigned.length > 0 ? { enforce, confirm_unsigned: true } : { enforce } })),
    onSuccess: (answer) => {
      queryClient.setQueryData(KEY, answer);
      setConfirming(false);
      setNotice(answer.enforce ? t('possign_enforceOn') : t('possign_enforceOff'));
    },
    onError: (failure) => {
      setConfirming(false);
      setError(errorText(failure, t));
      // The list of tills may have changed meanwhile
      void queryClient.invalidateQueries({ queryKey: KEY });
    },
  });

  const toggle = (enforce: boolean) => {
    setNotice(null);
    setError(null);
    // Turning it off never asks; turning it on does
    if (enforce) setConfirming(true);
    else save.mutate(false);
  };

  const names = unsigned.map((till) => till.name).join(', ');

  return (
    <div className="flex max-w-4xl flex-col gap-5">
      <p className="text-footnote text-fg-muted">{t('possign_intro')}</p>
      <Notice message={notice} />
      {error && (
        <p role="alert" className="rounded-control bg-danger-soft px-3 py-2 text-footnote font-bold text-danger-soft-fg">
          {error}
        </p>
      )}

      <Card className="flex flex-col gap-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex min-w-0 flex-col gap-1">
            <div className="flex flex-wrap items-center gap-2" data-testid="signing-mode">
              <h2 className="text-headline font-bold text-fg">{t('possign_title')}</h2>
              <Pill tone={data.enforce ? 'success' : 'neutral'}>{data.enforce ? t('possign_modeEnforce') : t('possign_modeObserve')}</Pill>
            </div>
            <p className="text-footnote text-fg-muted">{data.enforce ? t('possign_enforceHint') : t('possign_observeHint')}</p>
          </div>
          <Switch checked={data.enforce} onChange={toggle} label={t('possign_switch')} disabled={save.isPending} />
        </div>
        <p className="text-body text-fg" data-testid="signing-tills">
          {t('possign_signingTills', { count: data.signing_tills })}
        </p>
        {unsigned.length > 0 && (
          <div role="note" className="flex flex-col gap-1 rounded-control bg-warning-soft px-3 py-2 text-footnote text-warning-soft-fg">
            <span className="font-bold">{t('possign_unsignedTitle')}</span>
            <ul className="list-disc pl-5">
              {unsigned.map((till) => (
                <li key={till.uuid}>
                  {till.name} · {till.has_key ? t('possign_unsignedOldApp') : t('possign_unsignedNoKey')}
                </li>
              ))}
            </ul>
            <span>{t('possign_unsignedHint')}</span>
          </div>
        )}
        <p className="text-footnote text-fg-muted">{t('possign_storeWide')}</p>
      </Card>

      {confirming && (
        <ConfirmDialog
          title={t('possign_confirmTitle')}
          text={unsigned.length > 0 ? t('possign_confirmUnsignedText', { names }) : t('possign_confirmText')}
          confirmLabel={unsigned.length > 0 ? t('possign_confirmAnyway') : t('possign_confirm')}
          tone={unsigned.length > 0 ? 'danger' : 'primary'}
          busy={save.isPending}
          onClose={() => setConfirming(false)}
          onConfirm={() => save.mutate(true)}
        />
      )}
    </div>
  );
}
