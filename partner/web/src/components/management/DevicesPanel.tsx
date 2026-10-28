'use client';

import { useState, type FormEvent } from 'react';
import { Link2, MonitorSmartphone, Pencil, Ban } from 'lucide-react';
import { Button, Card, CardSkeletons, Dialog, EmptyState, Field, IconButton, InlineError, fieldClass, formatDateTime, FormError, Notice, Pill } from '@rizqim/ui';
import { useMenuBranches, useRenameStoreDevice, useRevokeStoreDevice, useStoreDevices, type StoreDevice } from '@rizqim/staff';
import { isMessageKey } from '@/i18n';
import { roleLabel } from '@/lib/labels';
import { useI18n } from '../I18nProvider';
import { useCan } from './access';
import { ConfirmDialog } from './ConfirmDialog';
import { errorText } from './errors';
import { Check } from './fields';
import { PairingCodeDialog } from './PairingCodeDialog';

type Dialog = { kind: 'pair' } | { kind: 'rename'; device: StoreDevice } | { kind: 'revoke'; device: StoreDevice };

// GET /owner/devices: the terminals and apps signed in to the store. devices.view lists them; devices.manage renames,
// revokes (the device loses its token at once) and creates the one-time pairing code of a new POS or kitchen display.
export function DevicesPanel() {
  const { t, language } = useI18n();
  const access = useCan();
  const [includeRevoked, setIncludeRevoked] = useState(false);
  const devices = useStoreDevices(includeRevoked);
  const branches = useMenuBranches();
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const revoke = useRevokeStoreDevice();
  const [revokeError, setRevokeError] = useState<string | null>(null);
  const canManage = access.can('devices.manage');
  const branchName = (id: number | null) => (branches.data ?? []).find((branch) => branch.id === id)?.name ?? '—';
  const typeLabel = (type: string) => (isMessageKey(`devices_type_${type}`) ? t(`devices_type_${type}` as 'devices_type_pos') : type);
  const close = () => {
    setDialog(null);
    setRevokeError(null);
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <Check checked={includeRevoked} onChange={setIncludeRevoked} label={t('devices_showRevoked')} />
        {canManage && (
          <Button icon={Link2} onClick={() => setDialog({ kind: 'pair' })}>
            {t('devices_pair')}
          </Button>
        )}
      </div>
      <Notice message={notice} />

      {devices.isPending ? (
        <CardSkeletons count={3} lines={2} label={t('loading')} />
      ) : devices.isError ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(devices.error, t)} retryLabel={t('retry')} retrying={devices.isFetching} onRetry={() => void devices.refetch()} />
      ) : devices.data.length === 0 ? (
        <EmptyState icon={MonitorSmartphone} title={t('devices_empty')} text={t('devices_emptyText')} />
      ) : (
        <ul className="grid gap-3 lg:grid-cols-2">
          {devices.data.map((device) => (
            <li key={device.uuid}>
              <Card className="flex h-full flex-col gap-2">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <h3 className="font-bold text-fg">{device.name}</h3>
                    <p className="text-footnote text-fg-muted">
                      {typeLabel(device.type)}
                      {device.platform ? ` · ${device.platform}` : ''}
                      {device.app_version ? ` · v${device.app_version}` : ''}
                    </p>
                  </div>
                  <Pill tone={device.revoked_at ? 'danger' : 'success'}>{device.revoked_at ? t('devices_revoked') : t('devices_active')}</Pill>
                </div>
                <p className="text-footnote text-fg-muted">
                  {device.user ? `${device.user.name} (${roleLabel(t, device.user.role)})` : '—'} · {branchName(device.branch_id)}
                </p>
                <p className="text-caption text-fg-muted">{device.last_seen_at ? t('devices_lastSeen', { time: formatDateTime(device.last_seen_at, language) }) : t('devices_neverSeen')}</p>
                {canManage && !device.revoked_at && (
                  <div className="mt-auto flex justify-end gap-1">
                    <IconButton icon={Pencil} label={t('devices_renameAria', { name: device.name })} onClick={() => setDialog({ kind: 'rename', device })} />
                    <IconButton icon={Ban} variant="danger" label={t('devices_revokeAria', { name: device.name })} onClick={() => setDialog({ kind: 'revoke', device })} />
                  </div>
                )}
              </Card>
            </li>
          ))}
        </ul>
      )}

      {dialog?.kind === 'pair' && <PairingCodeDialog onClose={close} />}
      {dialog?.kind === 'rename' && (
        <RenameDialog
          device={dialog.device}
          onClose={close}
          onSaved={() => {
            setNotice(t('devices_renamed'));
            close();
          }}
        />
      )}
      {dialog?.kind === 'revoke' && (
        <ConfirmDialog
          title={t('devices_revokeTitle', { name: dialog.device.name })}
          text={t('devices_revokeText')}
          confirmLabel={t('devices_revoke')}
          busy={revoke.isPending}
          error={revokeError}
          onClose={close}
          onConfirm={() => {
            setRevokeError(null);
            revoke.mutate(dialog.device.uuid, {
              onSuccess: () => {
                setNotice(t('devices_revokedNotice', { name: dialog.device.name }));
                close();
              },
              onError: (err) => setRevokeError(errorText(err, t)),
            });
          }}
        />
      )}
    </div>
  );
}

function RenameDialog({ device, onClose, onSaved }: { device: StoreDevice; onClose: () => void; onSaved: () => void }) {
  const { t } = useI18n();
  const [name, setName] = useState(device.name);
  const [error, setError] = useState<string | null>(null);
  const rename = useRenameStoreDevice();

  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (name.trim() === '') {
      setError(t('devices_nameRequired'));
      return;
    }
    setError(null);
    rename.mutate({ uuid: device.uuid, name: name.trim() }, { onSuccess: onSaved, onError: (err) => setError(errorText(err, t)) });
  };

  return (
    <Dialog open title={t('devices_renameTitle')} busy={rename.isPending} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <Field label={t('devices_name')}>
          {(props) => <input {...props} data-autofocus type="text" maxLength={100} autoComplete="off" value={name} onChange={(event) => setName(event.target.value)} className={fieldClass} />}
        </Field>
        <FormError message={error} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={rename.isPending}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={rename.isPending}>
            {t('mgmt_save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
