'use client';

import { useState, type FormEvent } from 'react';
import { KeyRound, Save } from 'lucide-react';
import { Button, Card, formatDateTime, FormError, Notice, Pill } from '@rizqim/ui';
import { LAN_DEFAULT_PORT, useBranchLan, useRotateBranchLanKey, useSaveBranchLan, type BranchLan, type BranchLanCandidate, type BranchLanPatch } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { ConfirmDialog } from '../management/ConfirmDialog';
import { errorText } from '../management/errors';
import { Gate } from '../pos/Gate';
import { SelectField, TextField } from '../pos/fields';
import { changedKeys, useServerDraft } from '../pos/serverDraft';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';

// The branch's local bridge (P6 stage B; docs/API.md "Lokal ko'prik (P6)"): which till is the hub the waiters' phones, the
// kitchen and customer screens reach over Wi-Fi when the internet is down, its port, and "Kalitni almashtirish" (a new
// branch key: every device takes it at its next sign-in). Candidates are the branch's active tills; a waiter's phone never
// is one (the server leaves it out).

interface Draft {
  // '' : automatic (the printing till, else the first cashier till)
  hub: string;
  port: string;
}

const draftOf = (lan: BranchLan): Draft => ({ hub: lan.hub?.chosen ? lan.hub.device : '', port: String(lan.port) });

const SERVER_FIELDS = ['hub_device_id', 'port'];

export function LanPanel({ branchId }: { branchId: number }) {
  const lan = useBranchLan(branchId);
  return <Gate query={lan} count={2}>{(data) => <LanForm key={branchId} branchId={branchId} data={data} />}</Gate>;
}

function LanForm({ branchId, data }: { branchId: number; data: BranchLan }) {
  const { t, language } = useI18n();
  const save = useSaveBranchLan();
  const rotate = useRotateBranchLanKey();
  const { draft, setDraft, base, dirty } = useServerDraft(data, draftOf);
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [portError, setPortError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [confirming, setConfirming] = useState(false);
  const [rotateError, setRotateError] = useState<string | null>(null);

  const patch = (changes: Partial<Draft>) => {
    setDraft((current) => ({ ...current, ...changes }));
    setNotice(null);
  };

  const appLabel = (candidate: BranchLanCandidate) => (candidate.app ? t(`poslan_app_${candidate.app}`) : t('poslan_appUnknown'));
  const candidateLabel = (candidate: BranchLanCandidate) =>
    [candidate.name ?? candidate.uuid.slice(0, 8), appLabel(candidate), candidate.platform, candidate.last_seen_at ? formatDateTime(candidate.last_seen_at, language) : t('poslan_neverSeen')]
      .filter(Boolean)
      .join(' · ');

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setNotice(null);
    setErrors(NO_ERRORS);
    const port = Number(draft.port.trim());
    const portOk = /^\d+$/.test(draft.port.trim()) && port >= 1024 && port <= 65535;
    setPortError(portOk ? null : t('poslan_portRange'));
    if (!portOk) return;

    // Only what changed: a key left out keeps what the server has
    const changed = new Set(changedKeys(draft, base));
    const body: BranchLanPatch = {};
    if (changed.has('hub')) body.hub_device_id = draft.hub === '' ? null : draft.hub;
    if (changed.has('port')) body.port = port;
    save.mutate(
      { branchId, patch: body },
      {
        onSuccess: (answer) => {
          setDraft(draftOf(answer));
          setNotice(t('poslan_saved'));
        },
        onError: (error) => setErrors(sortServerErrors(error, SERVER_FIELDS, t)),
      },
    );
  };

  const hubName = data.hub ? (data.hub.name ?? data.candidates.find((candidate) => candidate.uuid === data.hub?.device)?.name ?? data.hub.device.slice(0, 8)) : null;

  return (
    <div className="flex max-w-4xl flex-col gap-5">
      <p className="text-footnote text-fg-muted">{t('poslan_intro')}</p>
      <Notice message={notice} />

      <form onSubmit={submit} noValidate className="flex flex-col gap-5">
        <Card className="flex flex-col gap-4">
          <div>
            <h2 className="text-headline font-bold text-fg">{t('poslan_hubTitle')}</h2>
            <p className="text-footnote text-fg-muted">{t('poslan_hubHint')}</p>
          </div>
          {hubName === null ? (
            <p role="note" className="rounded-control bg-warning-soft px-3 py-2 text-footnote font-bold text-warning-soft-fg">
              {t('poslan_noHub')}
            </p>
          ) : (
            <div className="flex flex-wrap items-center gap-2" data-testid="lan-hub">
              <span className="text-body font-bold text-fg">{t('poslan_hubNow', { name: hubName })}</span>
              <Pill tone={data.hub?.chosen ? 'success' : 'neutral'}>{data.hub?.chosen ? t('poslan_hubChosen') : t('poslan_hubDefault')}</Pill>
            </div>
          )}
          {data.candidates.length > 0 && (
            <SelectField
              label={t('poslan_hub')}
              value={draft.hub}
              onChange={(hub) => patch({ hub })}
              options={[{ value: '', label: t('poslan_hubAuto') }, ...data.candidates.map((candidate) => ({ value: candidate.uuid, label: candidateLabel(candidate) }))]}
              error={errors.fields.hub_device_id}
              className="sm:max-w-xl"
            />
          )}
          <TextField
            label={t('poslan_port')}
            value={draft.port}
            onChange={(port) => patch({ port })}
            inputMode="numeric"
            maxLength={5}
            error={portError ?? errors.fields.port}
            hint={t('poslan_portHint', { port: LAN_DEFAULT_PORT })}
            className="sm:max-w-48"
          />
        </Card>
        <FormError message={errors.form} />
        <div>
          <Button type="submit" size="lg" icon={Save} loading={save.isPending} disabled={!dirty}>
            {t('poslan_save')}
          </Button>
        </div>
      </form>

      <Card className="flex flex-col gap-3">
        <div>
          <h2 className="text-headline font-bold text-fg">{t('poslan_keyTitle')}</h2>
          <p className="text-footnote text-fg-muted">{t('poslan_keyHint')}</p>
        </div>
        <p className="text-body text-fg" data-testid="lan-key">
          {t('poslan_keyVersion', { version: data.key_id })} · {data.rotated_at ? t('poslan_keyRotatedAt', { time: formatDateTime(data.rotated_at, language) }) : t('poslan_keyNeverRotated')}
        </p>
        <div>
          <Button
            variant="secondary"
            icon={KeyRound}
            onClick={() => {
              setRotateError(null);
              setConfirming(true);
            }}
          >
            {t('poslan_rotate')}
          </Button>
        </div>
      </Card>

      {confirming && (
        <ConfirmDialog
          title={t('poslan_rotateTitle')}
          text={t('poslan_rotateText')}
          confirmLabel={t('poslan_rotateConfirm')}
          busy={rotate.isPending}
          error={rotateError}
          onClose={() => setConfirming(false)}
          onConfirm={() =>
            rotate.mutate(
              { branchId },
              {
                onSuccess: () => {
                  setConfirming(false);
                  setNotice(t('poslan_rotated'));
                },
                onError: (error) => setRotateError(errorText(error, t)),
              },
            )
          }
        />
      )}
    </div>
  );
}
