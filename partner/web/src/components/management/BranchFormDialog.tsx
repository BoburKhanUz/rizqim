'use client';

import { useMemo, useState, type FormEvent } from 'react';
import { Button, Dialog, Field, fieldClass, FormError } from '@rizqim/ui';
import {
  getApiFieldError,
  useCreateBranch,
  useRegionsAndDistricts,
  useUpdateBranch,
  type BranchCreateInput,
  type BranchPatchInput,
  type ManagedBranch,
} from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { errorText } from './errors';
import { Check, NumberField, parseDecimal } from './fields';
import { clock } from './hours';

type Status = 'active' | 'inactive' | 'temporarily_closed';
const STATUSES: readonly Status[] = ['active', 'inactive', 'temporarily_closed'];
const FIELDS = ['name', 'phone', 'address', 'region_id', 'district_id', 'latitude', 'longitude', 'radius_km', 'open_time', 'close_time', 'status', 'is_active'];

const asStatus = (value: string): Status => (STATUSES.includes(value as Status) ? (value as Status) : 'active');

// Create a branch or change one: name, phone, address, place, delivery radius, ONE daily working window (overnight allowed,
// "24 soat" for none) and whether it takes orders. A change sends only what was edited. The server judges every value
// (422 with a message per field, shown under it).
export function BranchFormDialog({ branch, onClose, onSaved }: { branch: ManagedBranch | null; onClose: () => void; onSaved: (branch: ManagedBranch, created: boolean) => void }) {
  const { t } = useI18n();
  const editing = branch !== null;
  const locations = useRegionsAndDistricts();
  const [name, setName] = useState(branch?.name ?? '');
  const [phone, setPhone] = useState(branch?.phone ?? '');
  const [address, setAddress] = useState(branch?.address ?? '');
  const [regionId, setRegionId] = useState(String(branch?.region_id ?? ''));
  const [districtId, setDistrictId] = useState(String(branch?.district_id ?? ''));
  const [latitude, setLatitude] = useState(branch?.latitude != null ? String(branch.latitude) : '');
  const [longitude, setLongitude] = useState(branch?.longitude != null ? String(branch.longitude) : '');
  const [radius, setRadius] = useState(branch ? String(branch.radius_km) : '');
  const [allDay, setAllDay] = useState(branch ? branch.working_hours.all_day : true);
  const [openTime, setOpenTime] = useState(clock(branch?.working_hours.open) || '09:00');
  const [closeTime, setCloseTime] = useState(clock(branch?.working_hours.close) || '22:00');
  const [status, setStatus] = useState<Status>(asStatus(branch?.status ?? 'active'));
  const [isActive, setIsActive] = useState(branch?.is_active ?? true);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);

  const create = useCreateBranch();
  const update = useUpdateBranch();
  const busy = create.isPending || update.isPending;

  const districts = useMemo(() => (locations.data?.districts ?? []).filter((district) => String(district.region_id) === regionId), [locations.data, regionId]);

  const fail = (err: unknown) => {
    const next: Record<string, string> = {};
    for (const field of FIELDS) {
      const message = getApiFieldError(err, field);
      if (message) next[field] = message;
    }
    setErrors(next);
    setFormError(Object.keys(next).length === 0 ? errorText(err, t) : null);
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const local: Record<string, string> = {};
    const lat = parseDecimal(latitude);
    const lng = parseDecimal(longitude);
    const radiusValue = parseDecimal(radius);
    if (name.trim() === '') local.name = t('branches_nameRequired');
    if (!editing) {
      if (address.trim() === '') local.address = t('branches_addressRequired');
      if (regionId === '') local.region_id = t('branches_regionRequired');
      if (districtId === '') local.district_id = t('branches_districtRequired');
    }
    if (lat === undefined || (!editing && lat === null)) local.latitude = t('branches_coordinateInvalid');
    if (lng === undefined || (!editing && lng === null)) local.longitude = t('branches_coordinateInvalid');
    if (radiusValue === undefined) local.radius_km = t('mgmt_numberInvalid');
    if (!allDay && (openTime === '' || closeTime === '')) local.open_time = t('branches_hoursRequired');
    setErrors(local);
    setFormError(null);
    if (Object.keys(local).length > 0) return;

    const hours = allDay ? { open_time: null, close_time: null } : { open_time: openTime, close_time: closeTime };
    if (!editing) {
      const input: BranchCreateInput = {
        name: name.trim(),
        address: address.trim(),
        region_id: Number(regionId),
        district_id: Number(districtId),
        latitude: lat as number,
        longitude: lng as number,
        status,
        is_active: isActive,
        ...hours,
        ...(phone.trim() ? { phone: phone.trim() } : {}),
        ...(radiusValue !== null && radiusValue !== undefined ? { radius_km: radiusValue } : {}),
      };
      create.mutate(input, { onSuccess: (created) => onSaved(created, true), onError: fail });
      return;
    }

    const patch: BranchPatchInput = {};
    if (name.trim() !== branch.name) patch.name = name.trim();
    if (phone.trim() !== (branch.phone ?? '')) patch.phone = phone.trim() || null;
    if (address.trim() !== (branch.address ?? '')) patch.address = address.trim();
    if (regionId !== '' && Number(regionId) !== branch.region_id) patch.region_id = Number(regionId);
    if (districtId !== '' && Number(districtId) !== branch.district_id) patch.district_id = Number(districtId);
    if (lat !== null && lat !== branch.latitude) patch.latitude = lat;
    if (lng !== null && lng !== branch.longitude) patch.longitude = lng;
    if (radiusValue !== null && radiusValue !== undefined && radiusValue !== branch.radius_km) patch.radius_km = radiusValue;
    const hoursChanged = allDay ? !branch.working_hours.all_day : branch.working_hours.all_day || openTime !== clock(branch.working_hours.open) || closeTime !== clock(branch.working_hours.close);
    if (hoursChanged) Object.assign(patch, hours);
    if (status !== branch.status) patch.status = status;
    if (isActive !== branch.is_active) patch.is_active = isActive;
    if (Object.keys(patch).length === 0) {
      onClose();
      return;
    }
    update.mutate({ id: branch.id, input: patch }, { onSuccess: (saved) => onSaved(saved, false), onError: fail });
  };

  return (
    <Dialog open title={editing ? t('branches_editTitle') : t('branches_addTitle')} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <Field label={t('branches_name')} error={errors.name}>
          {(props) => <input {...props} data-autofocus type="text" maxLength={255} autoComplete="off" value={name} onChange={(event) => setName(event.target.value)} className={fieldClass} />}
        </Field>
        <Field label={t('branches_phone')} error={errors.phone}>
          {(props) => <input {...props} type="tel" inputMode="tel" maxLength={255} autoComplete="off" value={phone} onChange={(event) => setPhone(event.target.value)} className={fieldClass} />}
        </Field>
        <Field label={t('branches_address')} error={errors.address}>
          {(props) => <input {...props} type="text" maxLength={255} autoComplete="off" value={address} onChange={(event) => setAddress(event.target.value)} className={fieldClass} />}
        </Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={t('branches_region')} error={errors.region_id}>
            {(props) => (
              <select
                {...props}
                value={regionId}
                onChange={(event) => {
                  setRegionId(event.target.value);
                  setDistrictId('');
                }}
                className={fieldClass}
              >
                <option value="">{locations.isPending ? t('loading') : t('branches_choose')}</option>
                {(locations.data?.regions ?? []).map((region) => (
                  <option key={region.id} value={region.id}>
                    {region.name}
                  </option>
                ))}
              </select>
            )}
          </Field>
          <Field label={t('branches_district')} error={errors.district_id}>
            {(props) => (
              <select {...props} value={districtId} disabled={regionId === ''} onChange={(event) => setDistrictId(event.target.value)} className={fieldClass}>
                <option value="">{t('branches_choose')}</option>
                {districts.map((district) => (
                  <option key={district.id} value={district.id}>
                    {district.name}
                  </option>
                ))}
              </select>
            )}
          </Field>
        </div>
        <div className="grid gap-4 sm:grid-cols-3">
          <NumberField label={t('branches_latitude')} value={latitude} onChange={setLatitude} error={errors.latitude} placeholder="41.3111" />
          <NumberField label={t('branches_longitude')} value={longitude} onChange={setLongitude} error={errors.longitude} placeholder="69.2797" />
          <NumberField label={t('branches_radius')} value={radius} onChange={setRadius} error={errors.radius_km} suffix="km" />
        </div>
        <p className="-mt-2 text-footnote text-fg-muted">{t('branches_coordinatesHint')}</p>

        <fieldset className="flex flex-col gap-2 rounded-card border border-line p-3">
          <legend className="px-1 text-footnote font-bold text-fg-muted">{t('branches_hours')}</legend>
          <Check checked={allDay} onChange={setAllDay} label={t('branches_allDay')} hint={t('branches_allDayHint')} />
          {!allDay && (
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label={t('branches_opens')} error={errors.open_time}>
                {(props) => <input {...props} type="time" value={openTime} onChange={(event) => setOpenTime(event.target.value)} className={fieldClass} />}
              </Field>
              <Field label={t('branches_closes')} error={errors.close_time}>
                {(props) => <input {...props} type="time" value={closeTime} onChange={(event) => setCloseTime(event.target.value)} className={fieldClass} />}
              </Field>
              {closeTime !== '' && openTime !== '' && closeTime < openTime && <p className="text-footnote text-fg-muted sm:col-span-2">{t('branches_overnightHint')}</p>}
            </div>
          )}
        </fieldset>

        <Field label={t('branches_status')} error={errors.status}>
          {(props) => (
            <select {...props} value={status} onChange={(event) => setStatus(asStatus(event.target.value))} className={fieldClass}>
              {STATUSES.map((value) => (
                <option key={value} value={value}>
                  {t(`branches_status_${value}`)}
                </option>
              ))}
            </select>
          )}
        </Field>
        <Check checked={isActive} onChange={setIsActive} label={t('branches_isActive')} hint={t('branches_isActiveHint')} />
        {errors.is_active && <p role="alert" className="text-footnote font-bold text-danger-soft-fg">{errors.is_active}</p>}

        <FormError message={formError} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={busy}>
            {editing ? t('mgmt_save') : t('branches_add')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
