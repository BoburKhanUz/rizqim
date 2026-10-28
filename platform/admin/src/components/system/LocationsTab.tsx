'use client';

import { useState } from 'react';
import { ArrowDown, ArrowUp, ChevronDown, ChevronRight, MapPin, Plus } from 'lucide-react';
import { Button, Card, Field, fieldClass, IconButton, Notice, Pill, Switch } from '@rizqim/ui';
import {
  createDistrict,
  createRegion,
  deleteDistrict,
  deleteRegion,
  orderDistricts,
  orderRegions,
  updateDistrict,
  updateRegion,
  useLocationAction,
  useRegions,
  type DistrictRow,
  type RegionRow,
} from '@/lib/api/system';
import { errorText, fieldError } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { ListState, ReasonDialog } from './parts';

// Viloyatlar va tumanlar: what the Mini App's address form, the branches and the store applications pick from. Each can be added,
// renamed, switched on or off, put in order (up/down, then saved as the full list) and deleted when nothing uses it (branches,
// customer addresses, applications); something in use is switched off instead. Every change asks for a reason.

type Action =
  | { kind: 'createRegion' }
  | { kind: 'editRegion'; region: RegionRow }
  | { kind: 'toggleRegion'; region: RegionRow }
  | { kind: 'deleteRegion'; region: RegionRow }
  | { kind: 'createDistrict'; region: RegionRow }
  | { kind: 'editDistrict'; district: DistrictRow }
  | { kind: 'toggleDistrict'; district: DistrictRow }
  | { kind: 'deleteDistrict'; district: DistrictRow }
  | { kind: 'orderRegions'; ids: number[] }
  | { kind: 'orderDistricts'; region: RegionRow; ids: number[] };

interface ActionInput {
  action: Action;
  name: string;
  active: boolean;
  reason: string;
}

async function run({ action, name, active, reason }: ActionInput): Promise<unknown> {
  switch (action.kind) {
    case 'createRegion':
      return createRegion(name, active, reason);
    case 'editRegion':
      return updateRegion(action.region.id, { name }, reason);
    case 'toggleRegion':
      return updateRegion(action.region.id, { is_active: !action.region.is_active }, reason);
    case 'deleteRegion':
      return deleteRegion(action.region.id, reason);
    case 'createDistrict':
      return createDistrict(action.region.id, name, active, reason);
    case 'editDistrict':
      return updateDistrict(action.district.id, { name }, reason);
    case 'toggleDistrict':
      return updateDistrict(action.district.id, { is_active: !action.district.is_active }, reason);
    case 'deleteDistrict':
      return deleteDistrict(action.district.id, reason);
    case 'orderRegions':
      return orderRegions(action.ids, reason);
    case 'orderDistricts':
      return orderDistricts(action.region.id, action.ids, reason);
  }
}

const move = (ids: number[], index: number, by: -1 | 1): number[] => {
  const next = [...ids];
  const target = index + by;
  if (target < 0 || target >= next.length) return next;
  [next[index], next[target]] = [next[target]!, next[index]!];
  return next;
};

export function LocationsTab() {
  const t = useT();
  const regions = useRegions();
  const [notice, setNotice] = useState<string | null>(null);
  const [open, setOpen] = useState<number | null>(null);
  const [action, setAction] = useState<Action | null>(null);
  // While ordering: the ids in the new order (regions, or one region's districts)
  const [regionOrder, setRegionOrder] = useState<number[] | null>(null);
  const [districtOrder, setDistrictOrder] = useState<{ region: number; ids: number[] } | null>(null);
  const mutation = useLocationAction(run);

  const list = regions.data ?? [];
  const byId = new Map(list.map((region) => [region.id, region]));
  const shown = regionOrder ? regionOrder.map((id) => byId.get(id)).filter((region): region is RegionRow => region !== undefined) : list;

  const done: Record<Action['kind'], string> = {
    createRegion: t('system_loc_regionCreated'),
    editRegion: t('system_loc_saved'),
    toggleRegion: t('system_loc_saved'),
    deleteRegion: t('system_loc_regionDeleted'),
    createDistrict: t('system_loc_districtCreated'),
    editDistrict: t('system_loc_saved'),
    toggleDistrict: t('system_loc_saved'),
    deleteDistrict: t('system_loc_districtDeleted'),
    orderRegions: t('system_loc_orderSaved'),
    orderDistricts: t('system_loc_orderSaved'),
  };

  return (
    <div className="flex max-w-4xl flex-col gap-4">
      <Notice message={notice} />
      <p className="text-footnote text-fg-muted">{t('system_loc_intro')}</p>
      <div className="flex flex-wrap gap-2">
        {regionOrder ? (
          <>
            <Button onClick={() => setAction({ kind: 'orderRegions', ids: regionOrder })} disabled={regionOrder.join() === list.map((region) => region.id).join()}>
              {t('system_loc_orderSave')}
            </Button>
            <Button variant="ghost" onClick={() => setRegionOrder(null)}>
              {t('cancel')}
            </Button>
          </>
        ) : (
          <>
            <Button icon={Plus} onClick={() => setAction({ kind: 'createRegion' })}>
              {t('system_loc_addRegion')}
            </Button>
            {list.length > 1 && (
              <Button variant="secondary" onClick={() => setRegionOrder(list.map((region) => region.id))}>
                {t('system_loc_order')}
              </Button>
            )}
          </>
        )}
      </div>

      <ListState
        loading={!regions.data}
        error={regions.isError ? regions.error : null}
        fetching={regions.isFetching}
        onRetry={() => void regions.refetch()}
        empty={list.length === 0}
        emptyTitle={t('system_loc_empty')}
        emptyText={t('system_loc_emptyText')}
        icon={MapPin}
        errorTitle={t('system_loadError')}
      >
        <ul aria-label={t('system_loc_listLabel')} className="flex flex-col gap-2">
          {shown.map((region, index) => (
            <li key={region.id}>
              <Card padded={false}>
                <div className="flex flex-wrap items-center gap-2 p-3">
                  {regionOrder ? (
                    <span className="flex gap-1">
                      <IconButton icon={ArrowUp} label={t('system_loc_up', { name: region.name })} disabled={index === 0} onClick={() => setRegionOrder(move(regionOrder, index, -1))} />
                      <IconButton icon={ArrowDown} label={t('system_loc_down', { name: region.name })} disabled={index === shown.length - 1} onClick={() => setRegionOrder(move(regionOrder, index, 1))} />
                    </span>
                  ) : (
                    <IconButton
                      icon={open === region.id ? ChevronDown : ChevronRight}
                      label={open === region.id ? t('system_loc_collapse', { name: region.name }) : t('system_loc_expand', { name: region.name })}
                      aria-expanded={open === region.id}
                      onClick={() => setOpen(open === region.id ? null : region.id)}
                    />
                  )}
                  <span className="flex min-w-0 flex-1 flex-col">
                    <span className="break-words text-body font-bold text-fg">{region.name}</span>
                    <span className="text-footnote text-fg-muted">
                      {t('system_loc_regionFacts', { districts: region.districts_count, active: region.active_districts, branches: region.usage.branches })}
                    </span>
                  </span>
                  <Pill tone={region.is_active ? 'success' : 'neutral'}>{region.is_active ? t('system_loc_active') : t('system_loc_inactive')}</Pill>
                  {!regionOrder && <Switch checked={region.is_active} label={t('system_loc_switch', { name: region.name })} onChange={() => setAction({ kind: 'toggleRegion', region })} />}
                </div>
                {open === region.id && !regionOrder && (
                  <RegionBody
                    region={region}
                    order={districtOrder?.region === region.id ? districtOrder.ids : null}
                    onOrder={(ids) => setDistrictOrder(ids ? { region: region.id, ids } : null)}
                    onAction={setAction}
                  />
                )}
              </Card>
            </li>
          ))}
        </ul>
      </ListState>

      {action && (
        <ActionDialog
          action={action}
          busy={mutation.isPending}
          error={mutation.error}
          onClose={() => {
            setAction(null);
            mutation.reset();
          }}
          onSubmit={(name, active, reason) =>
            mutation.mutate(
              { action, name, active, reason },
              {
                onSuccess: () => {
                  setNotice(done[action.kind]);
                  if (action.kind === 'orderRegions') setRegionOrder(null);
                  if (action.kind === 'orderDistricts') setDistrictOrder(null);
                  if (action.kind === 'deleteRegion') setOpen(null);
                  setAction(null);
                },
              },
            )
          }
        />
      )}
    </div>
  );
}

function RegionBody({ region, order, onOrder, onAction }: { region: RegionRow; order: number[] | null; onOrder: (ids: number[] | null) => void; onAction: (action: Action) => void }) {
  const t = useT();
  const byId = new Map(region.districts.map((district) => [district.id, district]));
  const shown = order ? order.map((id) => byId.get(id)).filter((district): district is DistrictRow => district !== undefined) : region.districts;
  const used = region.usage.branches + region.usage.addresses + region.usage.applications;

  return (
    <div className="flex flex-col gap-3 border-t border-line p-3">
      <div className="flex flex-wrap gap-2">
        {order ? (
          <>
            <Button onClick={() => onAction({ kind: 'orderDistricts', region, ids: order })} disabled={order.join() === region.districts.map((district) => district.id).join()}>
              {t('system_loc_orderSave')}
            </Button>
            <Button variant="ghost" onClick={() => onOrder(null)}>
              {t('cancel')}
            </Button>
          </>
        ) : (
          <>
            <Button variant="secondary" onClick={() => onAction({ kind: 'editRegion', region })}>
              {t('system_loc_rename')}
            </Button>
            <Button variant="secondary" icon={Plus} onClick={() => onAction({ kind: 'createDistrict', region })}>
              {t('system_loc_addDistrict')}
            </Button>
            {region.districts.length > 1 && (
              <Button variant="secondary" onClick={() => onOrder(region.districts.map((district) => district.id))}>
                {t('system_loc_orderDistricts')}
              </Button>
            )}
            {region.deletable && (
              <Button variant="ghost" onClick={() => onAction({ kind: 'deleteRegion', region })}>
                {t('system_loc_delete')}
              </Button>
            )}
          </>
        )}
      </div>
      {!region.deletable && <p className="text-caption text-fg-muted">{region.districts_count > 0 ? t('system_loc_regionHasDistricts') : t('system_loc_inUse', { count: used })}</p>}
      {shown.length === 0 ? (
        <p className="text-footnote text-fg-muted">{t('system_loc_noDistricts')}</p>
      ) : (
        <ul aria-label={t('system_loc_districtsLabel', { name: region.name })} className="flex flex-col divide-y divide-line">
          {shown.map((district, index) => (
            <li key={district.id} className="flex flex-wrap items-center gap-2 py-2">
              {order && (
                <span className="flex gap-1">
                  <IconButton icon={ArrowUp} label={t('system_loc_up', { name: district.name })} disabled={index === 0} onClick={() => onOrder(move(order, index, -1))} />
                  <IconButton icon={ArrowDown} label={t('system_loc_down', { name: district.name })} disabled={index === shown.length - 1} onClick={() => onOrder(move(order, index, 1))} />
                </span>
              )}
              <span className="flex min-w-0 flex-1 flex-col">
                <span className="break-words text-body text-fg">{district.name}</span>
                {district.usage.branches + district.usage.addresses > 0 && (
                  <span className="text-caption text-fg-muted">{t('system_loc_districtUsage', { branches: district.usage.branches, addresses: district.usage.addresses })}</span>
                )}
              </span>
              {!order && (
                <>
                  <Switch checked={district.is_active} label={t('system_loc_switch', { name: district.name })} onChange={() => onAction({ kind: 'toggleDistrict', district })} />
                  <Button variant="ghost" aria-label={t('system_loc_renameLabel', { name: district.name })} onClick={() => onAction({ kind: 'editDistrict', district })}>
                    {t('system_loc_rename')}
                  </Button>
                  {district.deletable && (
                    <Button variant="ghost" aria-label={t('system_loc_deleteLabel', { name: district.name })} onClick={() => onAction({ kind: 'deleteDistrict', district })}>
                      {t('system_loc_delete')}
                    </Button>
                  )}
                </>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function ActionDialog({ action, busy, error, onSubmit, onClose }: { action: Action; busy: boolean; error: unknown; onSubmit: (name: string, active: boolean, reason: string) => void; onClose: () => void }) {
  const t = useT();
  const initialName = action.kind === 'editRegion' ? action.region.name : action.kind === 'editDistrict' ? action.district.name : '';
  const [name, setName] = useState(initialName);
  const [active, setActive] = useState(false);
  const withName = ['createRegion', 'editRegion', 'createDistrict', 'editDistrict'].includes(action.kind);
  const creates = action.kind === 'createRegion' || action.kind === 'createDistrict';

  const words = (): { title: string; text?: string; submit: string; danger?: boolean } => {
    switch (action.kind) {
      case 'createRegion':
        return { title: t('system_loc_addRegion'), submit: t('system_loc_add') };
      case 'editRegion':
        return { title: t('system_loc_renameTitle', { name: action.region.name }), submit: t('save') };
      case 'toggleRegion':
        return { title: action.region.is_active ? t('system_loc_offTitle', { name: action.region.name }) : t('system_loc_onTitle', { name: action.region.name }), text: action.region.is_active ? t('system_loc_offText') : undefined, submit: action.region.is_active ? t('system_loc_off') : t('system_loc_on') };
      case 'deleteRegion':
        return { title: t('system_loc_deleteTitle', { name: action.region.name }), submit: t('system_loc_delete'), danger: true };
      case 'createDistrict':
        return { title: t('system_loc_addDistrictTo', { name: action.region.name }), submit: t('system_loc_add') };
      case 'editDistrict':
        return { title: t('system_loc_renameTitle', { name: action.district.name }), submit: t('save') };
      case 'toggleDistrict':
        return { title: action.district.is_active ? t('system_loc_offTitle', { name: action.district.name }) : t('system_loc_onTitle', { name: action.district.name }), text: action.district.is_active ? t('system_loc_offText') : undefined, submit: action.district.is_active ? t('system_loc_off') : t('system_loc_on') };
      case 'deleteDistrict':
        return { title: t('system_loc_deleteTitle', { name: action.district.name }), submit: t('system_loc_delete'), danger: true };
      case 'orderRegions':
      case 'orderDistricts':
        return { title: t('system_loc_orderTitle'), submit: t('system_loc_orderSave') };
    }
  };
  const look = words();
  const nameOk = !withName || name.trim().length >= 2;

  return (
    <ReasonDialog
      title={look.title}
      text={look.text}
      submitLabel={look.submit}
      tone={look.danger ? 'danger' : 'primary'}
      busy={busy}
      canSubmit={nameOk}
      error={error && !fieldError(error, 'name') ? errorText(error, t) : null}
      reasonError={fieldError(error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => onSubmit(name.trim(), active, reason)}
    >
      {withName && (
        <Field label={t('system_loc_name')} error={fieldError(error, 'name')}>
          {(control) => <input {...control} data-autofocus maxLength={120} className={fieldClass} value={name} onChange={(event) => setName(event.target.value)} />}
        </Field>
      )}
      {creates && (
        <div className="flex items-center justify-between gap-3 rounded-control bg-surface-2 px-3 py-1">
          <span className="text-footnote font-bold text-fg">{t('system_loc_activeNow')}</span>
          <Switch checked={active} label={t('system_loc_activeNow')} onChange={setActive} />
        </div>
      )}
    </ReasonDialog>
  );
}
