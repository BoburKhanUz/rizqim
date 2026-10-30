'use client';

import { useState, type FormEvent } from 'react';
import { ChefHat, Pencil, Plus, Trash2 } from 'lucide-react';
import { Button, Card, Dialog, EmptyState, IconButton, FormError, Notice, Switch } from '@rizqim/ui';
import { useCreateKitchenStation, useDeleteKitchenStation, useKitchenStations, usePosFormat, useUpdateKitchenStation, type KitchenStation, type StationKind } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { formatDuration, parseTarget, TARGET_LIMITS } from '../kds/format';
import { useRefreshKdsTargets } from '../kds/hooks';
import { ProductTargetsPanel } from '../kds/ProductTargetsPanel';
import { ConfirmDialog } from '../management/ConfirmDialog';
import { errorText } from '../management/errors';
import { Check } from '../management/fields';
import { Gate } from '../pos/Gate';
import { SelectField, TextField } from '../pos/fields';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';
import { labelFor } from '../reports/format';
import { DataTable, Td, Th } from '../reports/ui';

// Kitchen stations of a branch (osh, mangal, salad bar, tea ...): where the kitchen ticket of a dish is printed or shown. A
// station that took an order cannot be deleted, only switched off. P6-33 (KDS v2): the universal kinds (grill, fryer, drinks,
// barista, expo) and, with the `kds_expo` module, each station's time target and the dishes' own targets.
export function StationsPanel({ branchId }: { branchId: number }) {
  const { t } = useI18n();
  const stations = useKitchenStations(branchId);
  const format = usePosFormat(branchId);
  const targetsOn = (format.data?.modules ?? []).includes('kds_expo');
  const update = useUpdateKitchenStation();
  const remove = useDeleteKitchenStation();
  const [dialog, setDialog] = useState<{ station: KitchenStation | null } | null>(null);
  const [deleting, setDeleting] = useState<KitchenStation | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [failure, setFailure] = useState<string | null>(null);

  return (
    <div className="flex flex-col gap-4">
      <Gate query={stations} count={2}>
        {(data) => (
          <Card className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 className="text-headline font-bold text-fg">{t('pos_stations_title')}</h2>
                <p className="text-footnote text-fg-muted">{t('pos_stations_intro')}</p>
              </div>
              <Button icon={Plus} onClick={() => setDialog({ station: null })}>
                {t('pos_stations_add')}
              </Button>
            </div>
            <Notice message={notice} />
            <FormError message={failure} />
            {data.stations.length === 0 ? (
              <EmptyState icon={ChefHat} title={t('pos_stations_empty')} text={t('pos_stations_emptyText')} />
            ) : (
              <DataTable caption={t('pos_stations_title')}>
                <thead>
                  <tr>
                    <Th>{t('pos_station_name')}</Th>
                    <Th>{t('pos_station_kind')}</Th>
                    <Th numeric>{t('pos_station_order')}</Th>
                    <Th>{t('pos_station_active')}</Th>
                    <Th>{t('pos_station_kds')}</Th>
                    <Th numeric>{t('kds_station_target')}</Th>
                    <Th>{t('mgmt_actions')}</Th>
                  </tr>
                </thead>
                <tbody>
                  {data.stations.map((station) => (
                    <tr key={station.id}>
                      <Td className="font-bold">{station.name}</Td>
                      <Td>{labelFor(t, 'pos_kind_', station.kind)}</Td>
                      <Td numeric>{station.sort_order}</Td>
                      <Td>
                        <Switch
                          checked={station.is_active}
                          label={t('pos_station_activeAria', { name: station.name })}
                          disabled={update.isPending}
                          onChange={(checked) => {
                            setFailure(null);
                            update.mutate({ id: station.id, patch: { is_active: checked } }, { onError: (error) => setFailure(errorText(error, t)) });
                          }}
                        />
                      </Td>
                      <Td>
                        <Switch
                          checked={station.show_on_kds}
                          label={t('pos_station_kdsAria', { name: station.name })}
                          disabled={update.isPending}
                          onChange={(checked) => {
                            setFailure(null);
                            update.mutate({ id: station.id, patch: { show_on_kds: checked } }, { onError: (error) => setFailure(errorText(error, t)) });
                          }}
                        />
                      </Td>
                      <Td numeric>{formatDuration(station.target_seconds, t)}</Td>
                      <Td>
                        <div className="flex gap-1">
                          <IconButton icon={Pencil} label={t('pos_station_editAria', { name: station.name })} onClick={() => setDialog({ station })} />
                          <IconButton icon={Trash2} label={t('pos_station_deleteAria', { name: station.name })} onClick={() => setDeleting(station)} />
                        </div>
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </DataTable>
            )}

            {dialog && (
              <StationDialog
                branchId={branchId}
                station={dialog.station}
                kinds={data.kinds.map((kind) => kind.value)}
                targetsOn={targetsOn}
                onClose={() => setDialog(null)}
                onSaved={(saved) => {
                  setNotice(dialog.station ? t('pos_station_saved', { name: saved.name }) : t('pos_station_created', { name: saved.name }));
                  setDialog(null);
                }}
              />
            )}
            {deleting && (
              <ConfirmDialog
                title={t('pos_station_deleteTitle', { name: deleting.name })}
                text={t('pos_station_deleteText')}
                confirmLabel={t('mgmt_delete')}
                busy={remove.isPending}
                error={deleteError}
                onClose={() => {
                  setDeleting(null);
                  setDeleteError(null);
                }}
                onConfirm={() =>
                  remove.mutate(deleting.id, {
                    onSuccess: () => {
                      setNotice(t('pos_station_deleted', { name: deleting.name }));
                      setDeleting(null);
                    },
                    onError: (error) => setDeleteError(errorText(error, t)),
                  })
                }
              />
            )}
          </Card>
        )}
      </Gate>
      {targetsOn && <ProductTargetsPanel branchId={branchId} />}
    </div>
  );
}

const SUGGESTED: Record<string, number> = { grill: 300, fryer: 240, drinks: 90, barista: 180, expo: 120 };

function StationDialog({
  branchId,
  station,
  kinds,
  targetsOn,
  onClose,
  onSaved,
}: {
  branchId: number;
  station: KitchenStation | null;
  kinds: string[];
  targetsOn: boolean;
  onClose: () => void;
  onSaved: (station: KitchenStation) => void;
}) {
  const { t } = useI18n();
  const [name, setName] = useState(station?.name ?? '');
  const [kind, setKind] = useState<string>(station?.kind ?? kinds[0] ?? 'kitchen');
  const [order, setOrder] = useState(station ? String(station.sort_order) : '');
  const [active, setActive] = useState(station?.is_active ?? true);
  const [kds, setKds] = useState(station?.show_on_kds ?? true);
  const original = station?.target_seconds ?? null;
  const [target, setTarget] = useState(original === null ? '' : String(original));
  // A target is the kds_expo setup; one already set can still be cleared after the module went off
  const showTarget = targetsOn || original !== null;
  const refreshTargets = useRefreshKdsTargets();
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<Record<string, string>>({});
  const create = useCreateKitchenStation();
  const update = useUpdateKitchenStation();
  const busy = create.isPending || update.isPending;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const problems: Record<string, string> = {};
    if (name.trim() === '') problems.name = t('pos_err_name');
    const sort = order.trim() === '' ? undefined : Number(order);
    if (sort !== undefined && (!Number.isInteger(sort) || sort < 0)) problems.sort_order = t('mgmt_integerInvalid');
    const seconds = showTarget ? parseTarget(target, TARGET_LIMITS) : original;
    if (seconds === undefined) problems.target_seconds = t('kds_err_target', { min: TARGET_LIMITS.min, max: TARGET_LIMITS.max });
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;
    // Only a changed target is sent (an unchanged one needs no module check)
    const fields = {
      name: name.trim(),
      kind: kind as StationKind,
      ...(sort !== undefined ? { sort_order: sort } : {}),
      is_active: active,
      show_on_kds: kds,
      ...(seconds !== undefined && seconds !== original ? { target_seconds: seconds } : {}),
    };
    const options = {
      onSuccess: (saved: KitchenStation) => {
        refreshTargets();
        onSaved(saved);
      },
      onError: (error: unknown) => setErrors(sortServerErrors(error, ['name', 'kind', 'sort_order', 'is_active', 'show_on_kds', 'branch_id', 'target_seconds'], t)),
    };
    if (station) update.mutate({ id: station.id, patch: fields }, options);
    else create.mutate({ branch_id: branchId, ...fields }, options);
  };

  return (
    <Dialog open title={station ? t('pos_station_editTitle') : t('pos_station_newTitle')} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <TextField label={t('pos_station_name')} value={name} onChange={setName} maxLength={100} autoFocus error={local.name ?? errors.fields.name} />
        <SelectField label={t('pos_station_kind')} value={kind} onChange={setKind} options={kinds.map((value) => ({ value, label: labelFor(t, 'pos_kind_', value) }))} error={errors.fields.kind} />
        {kind === 'expo' && <p className="text-footnote text-fg-muted">{t('kds_station_expoNote')}</p>}
        <TextField label={t('pos_station_order')} hint={t('pos_station_orderHint')} value={order} inputMode="numeric" onChange={setOrder} error={local.sort_order ?? errors.fields.sort_order} />
        {showTarget ? (
          <TextField
            label={t('kds_station_target')}
            hint={[kind === 'expo' ? t('kds_station_targetExpoHint') : t('kds_station_targetHint'), SUGGESTED[kind] ? t('kds_station_targetSuggested', { value: formatDuration(SUGGESTED[kind], t) }) : null].filter(Boolean).join(' ')}
            value={target}
            inputMode="numeric"
            onChange={setTarget}
            error={local.target_seconds ?? errors.fields.target_seconds}
          />
        ) : (
          <p className="text-footnote text-fg-muted">{t('kds_station_targetOff')}</p>
        )}
        <Check checked={active} onChange={setActive} label={t('pos_station_activeLabel')} hint={t('pos_station_activeHint')} />
        <Check checked={kds} onChange={setKds} label={t('pos_station_kdsLabel')} />
        <FormError message={errors.form} />
        <div className="flex flex-wrap justify-end gap-3">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={busy}>
            {t('mgmt_save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
