'use client';

import { useState, type FormEvent } from 'react';
import { Button, Field, fieldClass } from '@rizqim/ui';
import type { DeliveryZone, ManagedBranch, ZoneInput, ZonePolygon } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Check, NumberField, parseDecimal } from './fields';
import { FormError } from './Notice';
import { parseGeometry, shapesToGeometry, type Shape } from './polygon';

export interface ZoneFormErrors {
  fields: Record<string, string>;
  form: string | null;
}

// A typed number as text ("12000.00" from the server shows as 12000)
const asText = (value: string | number | null | undefined): string => (value === null || value === undefined || value === '' ? '' : String(Number(value)));

// The fields of a zone: name, branch, priority, the three prices and the time, whether it is on, and the GeoJSON of its shape
// (the map is the main way to draw it; the text box is for pasting a ready shape and for working without the map). It
// builds the ZoneInput; saving it, and what the server says about the polygon, is the screen's.
export function ZoneForm({
  zone,
  branches,
  geometry,
  onApplyShapes,
  onSubmit,
  onCancel,
  onDelete,
  onPolygonMissing,
  saving,
  serverErrors,
}: {
  zone: DeliveryZone | null;
  branches: ManagedBranch[];
  geometry: ZonePolygon | null;
  onApplyShapes: (shapes: Shape[]) => void;
  onSubmit: (input: ZoneInput, patch: Partial<ZoneInput>) => void;
  onCancel: () => void;
  onDelete?: () => void;
  // Saving without a shape: the screen says so beside the map
  onPolygonMissing: () => void;
  saving: boolean;
  serverErrors: ZoneFormErrors;
}) {
  const { t } = useI18n();
  const [name, setName] = useState(zone?.name ?? '');
  const [branchId, setBranchId] = useState(String(zone?.branch_id ?? ''));
  const [priority, setPriority] = useState(asText(zone?.priority ?? 0));
  const [price, setPrice] = useState(asText(zone?.delivery_price));
  const [minOrder, setMinOrder] = useState(asText(zone?.min_order_amount));
  const [freeFrom, setFreeFrom] = useState(asText(zone?.free_delivery_from));
  const [eta, setEta] = useState(asText(zone?.eta_minutes));
  const [active, setActive] = useState(zone?.active ?? true);
  const [local, setLocal] = useState<Record<string, string>>({});
  const errors = { ...serverErrors.fields, ...local };

  const geoJson = geometry ? JSON.stringify(geometry, null, 1) : '';
  const [geoText, setGeoText] = useState(geoJson);
  const [geoError, setGeoError] = useState<string | null>(null);
  const [shownGeo, setShownGeo] = useState(geoJson);
  // The map changed the shape: the text box shows the new one (unless it is what is being typed)
  if (shownGeo !== geoJson) {
    setShownGeo(geoJson);
    setGeoText(geoJson);
    setGeoError(null);
  }

  const applyText = () => {
    const shapes = parseGeometry(geoText);
    if (!shapes) {
      setGeoError(t('zones_geoJsonInvalid'));
      return;
    }
    setGeoError(null);
    onApplyShapes(shapes);
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const problems: Record<string, string> = {};
    const priorityValue = parseDecimal(priority);
    const priceValue = parseDecimal(price);
    const minValue = parseDecimal(minOrder);
    const freeValue = parseDecimal(freeFrom);
    const etaValue = parseDecimal(eta);
    if (name.trim() === '') problems.name = t('zones_nameRequired');
    if (priorityValue === undefined || (priorityValue !== null && !Number.isInteger(priorityValue))) problems.priority = t('mgmt_integerInvalid');
    if (priceValue === undefined) problems.delivery_price = t('mgmt_numberInvalid');
    if (minValue === undefined) problems.min_order_amount = t('mgmt_numberInvalid');
    if (freeValue === undefined) problems.free_delivery_from = t('mgmt_numberInvalid');
    if (etaValue === undefined || (etaValue !== null && !Number.isInteger(etaValue))) problems.eta_minutes = t('mgmt_integerInvalid');
    setLocal(problems);
    if (!geometry) onPolygonMissing();
    if (Object.keys(problems).length > 0 || !geometry) return;

    const input: ZoneInput = {
      name: name.trim(),
      polygon: geometry,
      branch_id: branchId === '' ? null : Number(branchId),
      priority: (priorityValue as number | null) ?? 0,
      active,
      delivery_price: (priceValue as number | null) ?? 0,
      min_order_amount: (minValue as number | null) ?? 0,
      free_delivery_from: (freeValue as number | null) ?? null,
      eta_minutes: (etaValue as number | null) ?? null,
    };
    // A change sends what differs, and the polygon only when the shape itself moved
    const patch: Partial<ZoneInput> = {};
    if (zone) {
      const before = shapesToGeometry(parseGeometry(zone.polygon) ?? []);
      if (JSON.stringify(before) !== JSON.stringify(geometry)) patch.polygon = geometry;
      if (input.name !== zone.name) patch.name = input.name;
      if (input.branch_id !== zone.branch_id) patch.branch_id = input.branch_id;
      if (input.priority !== zone.priority) patch.priority = input.priority;
      if (input.active !== zone.active) patch.active = input.active;
      if (input.delivery_price !== Number(zone.delivery_price)) patch.delivery_price = input.delivery_price;
      if (input.min_order_amount !== Number(zone.min_order_amount)) patch.min_order_amount = input.min_order_amount;
      if (input.free_delivery_from !== (zone.free_delivery_from === null ? null : Number(zone.free_delivery_from))) patch.free_delivery_from = input.free_delivery_from;
      if (input.eta_minutes !== zone.eta_minutes) patch.eta_minutes = input.eta_minutes;
    }
    onSubmit(input, patch);
  };

  return (
    <form onSubmit={submit} noValidate className="flex flex-col gap-4">
      <Field label={t('zones_name')} error={errors.name}>
        {(props) => <input {...props} type="text" maxLength={120} autoComplete="off" value={name} onChange={(event) => setName(event.target.value)} className={fieldClass} />}
      </Field>
      <Field label={t('zones_branch')} hint={t('zones_branchHint')} error={errors.branch_id}>
        {(props) => (
          <select {...props} value={branchId} onChange={(event) => setBranchId(event.target.value)} className={fieldClass}>
            <option value="">{t('zones_anyBranch')}</option>
            {branches.map((branch) => (
              <option key={branch.id} value={branch.id}>
                {branch.name}
              </option>
            ))}
          </select>
        )}
      </Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <NumberField label={t('zones_price')} value={price} onChange={setPrice} error={errors.delivery_price} suffix="so'm" placeholder="0" />
        <NumberField label={t('zones_minOrder')} value={minOrder} onChange={setMinOrder} error={errors.min_order_amount} suffix="so'm" placeholder="0" />
        <NumberField label={t('zones_freeFrom')} value={freeFrom} onChange={setFreeFrom} error={errors.free_delivery_from} suffix="so'm" hint={t('zones_freeFromHint')} />
        <NumberField label={t('zones_eta')} value={eta} onChange={setEta} error={errors.eta_minutes} suffix="daq" inputMode="numeric" />
        <NumberField label={t('zones_priority')} value={priority} onChange={setPriority} error={errors.priority} inputMode="numeric" hint={t('zones_priorityHint')} />
      </div>
      <Check checked={active} onChange={setActive} label={t('zones_active')} hint={t('zones_activeHint')} />

      {zone && (
        <p className="text-footnote text-fg-muted">
          {t('zones_area', { km: zone.area_km2 })}
        </p>
      )}

      <details className="rounded-card border border-line p-3" open={Boolean(errors.polygon) || Boolean(geoError)}>
        <summary className="min-h-11 cursor-pointer py-2.5 text-footnote font-bold text-fg-muted">{t('zones_geoJson')}</summary>
        <div className="flex flex-col gap-2">
          <p className="text-footnote text-fg-muted">{t('zones_geoJsonHint')}</p>
          <Field label={t('zones_geoJson')} error={geoError ?? undefined}>
            {(props) => (
              <textarea {...props} rows={6} spellCheck={false} value={geoText} onChange={(event) => setGeoText(event.target.value)} className={`${fieldClass} font-mono text-footnote`} />
            )}
          </Field>
          <Button variant="secondary" onClick={applyText} className="self-start">
            {t('zones_geoJsonApply')}
          </Button>
        </div>
      </details>

      <FormError message={serverErrors.form} />
      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" onClick={onCancel} disabled={saving} className="flex-1">
          {t('mgmt_cancel')}
        </Button>
        <Button type="submit" loading={saving} className="flex-1">
          {zone ? t('mgmt_save') : t('zones_create')}
        </Button>
        {onDelete && (
          <Button variant="danger" onClick={onDelete} disabled={saving} className="w-full">
            {t('zones_delete')}
          </Button>
        )}
      </div>
    </form>
  );
}
