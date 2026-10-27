'use client';

import { useMemo, useReducer, useState } from 'react';
import { Eraser, Flag, Map as MapIcon, Plus, Shapes, Undo2, X } from 'lucide-react';
import { Button, Card, CardSkeletons, EmptyState, InlineError, cx, formatMoney, PageHeader, FormError, Notice, Pill } from '@rizqim/ui';
import {
  getApiFieldError,
  useCreateDeliveryZone,
  useDeleteDeliveryZone,
  useDeliveryZones,
  useMenuBranches,
  useUpdateDeliveryZone,
  type DeliveryZone,
  type ZoneInput,
} from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { useCan } from './access';
import { ConfirmDialog } from './ConfirmDialog';
import { errorText } from './errors';
import { countPoints, editorReducer, initialEditor, parseGeometry, shapesToGeometry } from './polygon';
import { ZoneForm, type ZoneFormErrors } from './ZoneForm';
import { ZoneMap } from './ZoneMap';

type Mode = { kind: 'idle' } | { kind: 'create' } | { kind: 'edit'; id: number };

const FIELDS = ['name', 'polygon', 'branch_id', 'priority', 'active', 'delivery_price', 'min_order_amount', 'free_delivery_from', 'eta_minutes'];
const NO_ERRORS: ZoneFormErrors = { fields: {}, form: null };

// Delivery zones (zones.manage, the owner): the zones and the branches of the store on a map, a form per zone and a small
// polygon editor on the map. The server decides what a polygon may be; its reason for a refusal (errors.polygon, Uzbek) is shown
// beside the toolbar and in the form.
export function ZonesView() {
  const { t, language } = useI18n();
  const zones = useDeliveryZones();
  const branches = useMenuBranches();
  const access = useCan();
  const [mode, setMode] = useState<Mode>({ kind: 'idle' });
  const [editor, dispatch] = useReducer(editorReducer, undefined, () => initialEditor());
  const [notice, setNotice] = useState<string | null>(null);
  const [errors, setErrors] = useState<ZoneFormErrors>(NO_ERRORS);
  const [confirmDelete, setConfirmDelete] = useState<DeliveryZone | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const create = useCreateDeliveryZone();
  const update = useUpdateDeliveryZone();
  const remove = useDeleteDeliveryZone();

  const editing = mode.kind !== 'idle';
  const selected = mode.kind === 'edit' ? (zones.data ?? []).find((zone) => zone.id === mode.id) ?? null : null;
  const geometry = useMemo(() => shapesToGeometry(editor.shapes), [editor.shapes]);

  const mapZones = useMemo(
    () => (zones.data ?? []).map((zone) => ({ id: zone.id, name: zone.name, active: zone.active, shapes: parseGeometry(zone.polygon) ?? [] })),
    [zones.data],
  );
  const mapBranches = useMemo(
    () =>
      (branches.data ?? [])
        .filter((branch) => branch.latitude !== null && branch.longitude !== null)
        .map((branch) => ({ id: branch.id, name: branch.name, position: [branch.latitude as number, branch.longitude as number] as [number, number] })),
    [branches.data],
  );

  const startCreate = () => {
    setNotice(null);
    setErrors(NO_ERRORS);
    setMode({ kind: 'create' });
    dispatch({ type: 'load', shapes: [], drawing: true });
  };
  const startEdit = (zone: DeliveryZone) => {
    setNotice(null);
    setErrors(NO_ERRORS);
    setMode({ kind: 'edit', id: zone.id });
    dispatch({ type: 'load', shapes: parseGeometry(zone.polygon) ?? [] });
  };
  const stop = () => {
    setMode({ kind: 'idle' });
    setErrors(NO_ERRORS);
    dispatch({ type: 'load', shapes: [] });
  };

  const fail = (err: unknown) => {
    const fields: Record<string, string> = {};
    for (const field of FIELDS) {
      const message = getApiFieldError(err, field);
      if (message) fields[field] = message;
    }
    setErrors({ fields, form: Object.keys(fields).length === 0 ? errorText(err, t) : null });
  };

  const submit = (input: ZoneInput, patch: Partial<ZoneInput>) => {
    setErrors(NO_ERRORS);
    if (mode.kind === 'create') {
      create.mutate(input, {
        onSuccess: (created) => {
          setNotice(t('zones_created', { name: created.name }));
          stop();
        },
        onError: fail,
      });
    } else if (mode.kind === 'edit') {
      if (Object.keys(patch).length === 0) {
        stop();
        return;
      }
      update.mutate(
        { id: mode.id, input: patch },
        {
          onSuccess: (saved) => {
            setNotice(t('zones_saved', { name: saved.name }));
            stop();
          },
          onError: fail,
        },
      );
    }
  };

  if (access.ready && !access.can('zones.manage')) {
    return (
      <>
        <PageHeader title={t('nav_zones')} />
        <EmptyState icon={MapIcon} title={t('mgmt_forbidden')} text={t('zones_ownerOnly')} />
      </>
    );
  }

  const drawing = editor.drawing !== null;
  const lastShape = editor.drawing !== null ? editor.shapes[editor.drawing] : undefined;
  const pointCount = countPoints(editor.shapes);

  return (
    <>
      <PageHeader
        title={t('nav_zones')}
        subtitle={t('zones_subtitle')}
        action={
          !editing && (
            <Button icon={Plus} onClick={startCreate} disabled={!zones.isSuccess}>
              {t('zones_new')}
            </Button>
          )
        }
      />
      <Notice message={notice} />

      {zones.isPending ? (
        <CardSkeletons count={3} lines={3} label={t('loading')} />
      ) : zones.isError ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(zones.error, t)} retryLabel={t('retry')} retrying={zones.isFetching} onRetry={() => void zones.refetch()} />
      ) : (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
          <div className={cx('flex flex-col gap-3', editing && 'order-2 lg:order-none')}>
            {editing ? (
              <Card className="flex flex-col gap-4">
                <h2 className="text-headline font-bold text-fg">{mode.kind === 'create' ? t('zones_newTitle') : t('zones_editTitle', { name: selected?.name ?? '' })}</h2>
                <ZoneForm
                  key={mode.kind === 'edit' ? mode.id : 'new'}
                  zone={selected}
                  branches={branches.data ?? []}
                  geometry={geometry}
                  onApplyShapes={(shapes) => dispatch({ type: 'load', shapes })}
                  onSubmit={submit}
                  onCancel={stop}
                  onDelete={selected ? () => setConfirmDelete(selected) : undefined}
                  onPolygonMissing={() => setErrors((previous) => ({ ...previous, fields: { ...previous.fields, polygon: t('zones_needPolygon') } }))}
                  saving={create.isPending || update.isPending}
                  serverErrors={errors}
                />
              </Card>
            ) : zones.data.length === 0 ? (
              <EmptyState icon={MapIcon} title={t('zones_empty')} text={t('zones_emptyText')} action={<Button icon={Plus} onClick={startCreate}>{t('zones_new')}</Button>} />
            ) : (
              <ul className="flex flex-col gap-3">
                {zones.data.map((zone) => (
                  <li key={zone.id}>
                    <button
                      type="button"
                      onClick={() => startEdit(zone)}
                      aria-label={t('zones_openAria', { name: zone.name })}
                      className="flex min-h-11 w-full flex-col gap-2 rounded-card border border-line bg-surface p-4 text-left transition-colors hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                    >
                      <span className="flex items-start justify-between gap-3">
                        <span className="font-bold text-fg">{zone.name}</span>
                        <Pill tone={zone.active ? 'success' : 'neutral'}>{zone.active ? t('zones_activePill') : t('zones_inactivePill')}</Pill>
                      </span>
                      <span className="text-footnote text-fg-muted">
                        {t('zones_priceLine', { price: formatMoney(zone.delivery_price, language), min: formatMoney(zone.min_order_amount, language) })}
                        {zone.free_delivery_from !== null && ` · ${t('zones_freeLine', { amount: formatMoney(zone.free_delivery_from, language) })}`}
                        {zone.eta_minutes !== null && ` · ${t('minutes', { n: zone.eta_minutes })}`}
                      </span>
                      <span className="text-caption text-fg-muted">
                        {zone.branch ? zone.branch.name : t('zones_anyBranch')} · {t('zones_priorityLine', { n: zone.priority })}
                      </span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>

          <div className={cx('flex flex-col gap-3', editing && 'order-1 lg:order-none')}>
            {editing && (
              <Card className="flex flex-col gap-3" aria-label={t('zones_toolbar')}>
                <div className="flex flex-wrap items-center gap-2">
                  <Button variant="secondary" icon={Shapes} onClick={() => dispatch({ type: 'startShape' })} disabled={drawing}>
                    {t('zones_newShape')}
                  </Button>
                  {drawing && (
                    <Button icon={Flag} onClick={() => dispatch({ type: 'finish' })} disabled={(lastShape?.outer.length ?? 0) < 3}>
                      {t('zones_finish')}
                    </Button>
                  )}
                  <Button variant="secondary" icon={Undo2} onClick={() => dispatch({ type: 'undo' })} disabled={!drawing || (lastShape?.outer.length ?? 0) === 0}>
                    {t('zones_undo')}
                  </Button>
                  <Button variant="ghost" icon={Eraser} onClick={() => dispatch({ type: 'clear' })} disabled={editor.shapes.length === 0}>
                    {t('zones_clear')}
                  </Button>
                </div>
                <p className="text-footnote text-fg-muted" aria-live="polite">
                  {drawing ? t('zones_hintDrawing') : editor.shapes.length === 0 ? t('zones_hintEmpty') : t('zones_hintEditing')}
                </p>
                <div className="flex flex-wrap items-center gap-2 text-footnote text-fg-muted">
                  <span>{t('zones_counts', { shapes: editor.shapes.length, points: pointCount })}</span>
                  {editor.shapes.map((_, index) => (
                    <span key={index} className="inline-flex items-center gap-1 rounded-full bg-surface-2 py-0.5 pl-3 pr-1 text-fg">
                      {t('zones_shapeN', { n: index + 1 })}
                      <button
                        type="button"
                        aria-label={t('zones_removeShape', { n: index + 1 })}
                        onClick={() => dispatch({ type: 'removeShape', shape: index })}
                        className="inline-flex size-7 items-center justify-center rounded-full text-fg-muted hover:bg-line focus-visible:outline-2 focus-visible:outline-brand"
                      >
                        <X aria-hidden="true" className="size-4" />
                      </button>
                    </span>
                  ))}
                </div>
                <FormError message={errors.fields.polygon} />
              </Card>
            )}
            <ZoneMap
              branches={mapBranches}
              zones={mapZones}
              selectedId={mode.kind === 'edit' ? mode.id : null}
              editor={editing ? editor : null}
              dispatch={dispatch}
              onSelectZone={(id) => {
                const zone = zones.data.find((candidate) => candidate.id === id);
                if (zone) startEdit(zone);
              }}
            />
            <p className="text-caption text-fg-muted">{t('zones_legend')}</p>
          </div>
        </div>
      )}

      {confirmDelete && (
        <ConfirmDialog
          title={t('zones_deleteTitle', { name: confirmDelete.name })}
          text={t('zones_deleteText')}
          confirmLabel={t('mgmt_delete')}
          busy={remove.isPending}
          error={deleteError}
          onClose={() => {
            setConfirmDelete(null);
            setDeleteError(null);
          }}
          onConfirm={() => {
            setDeleteError(null);
            remove.mutate(confirmDelete.id, {
              onSuccess: () => {
                setNotice(t('zones_deleted', { name: confirmDelete.name }));
                setConfirmDelete(null);
                stop();
              },
              onError: (err) => setDeleteError(errorText(err, t)),
            });
          }}
        />
      )}
    </>
  );
}
