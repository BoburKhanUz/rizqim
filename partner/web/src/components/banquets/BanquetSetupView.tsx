'use client';

import { useState, type FormEvent } from 'react';
import Link from 'next/link';
import { CalendarHeart, Pencil, Plus, Trash2 } from 'lucide-react';
import { Button, Card, CardSkeletons, Dialog, EmptyState, FormError, IconButton, InlineError, Notice, PageHeader, Tabs, formatMoney } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { Check, NumberField, parseDecimal } from '../management/fields';
import { BranchSelect, useBranchChoice } from '../menu/BranchChoice';
import { SelectField, TextField, TimeField } from '../pos/fields';
import { DataTable, Td, Th } from '../reports/ui';
import { NAMED_SLOTS, SLOTS, type Extra, type HallRates, type Package, type PackageItem, type Pricing, type Settings, type Slot } from './api';
import { useBanquetExtras, useBanquetHalls, useBanquetPackages, useBanquetSettings, useDeleteExtra, useDeletePackage, useSaveExtra, useSaveHallRates, useSavePackage, useSaveSettings } from './hooks';

type Tab = 'packages' | 'extras' | 'halls' | 'settings';
const TABS: readonly Tab[] = ['packages', 'extras', 'halls', 'settings'];
const UNITS: readonly PackageItem['unit'][] = ['portion', 'piece', 'kg', 'l'];

// /banquets/setup: the owner's banquet catalogue and settings (P6-52): packages (per-person price, minimum guests, what is
// included, menu items per person), extras (per event or per person), each hall's rent per slot, and the settings —
// slot times, hold hours, the guest and time warnings (never refusals) and the MXIK codes. Events whose prices were frozen
// on the contract day keep theirs.
export function BanquetSetupView() {
  const { t } = useI18n();
  const access = useCan();
  const [tab, setTab] = useState<Tab>('packages');
  const [notice, setNotice] = useState<string | null>(null);

  if (access.ready && !access.can('banquets.manage')) {
    return (
      <>
        <PageHeader title={t('bq_setupTitle')} />
        <EmptyState icon={CalendarHeart} title={t('mgmt_forbidden')} text={t('bq_noAccess')} />
      </>
    );
  }

  return (
    <>
      <PageHeader
        title={t('bq_setupTitle')}
        subtitle={t('bq_setupSubtitle')}
        action={
          <Link href="/banquets" className="font-bold text-brand underline-offset-2 hover:underline">
            {t('bq_backToCalendar')}
          </Link>
        }
      />
      <Notice message={notice} />
      <Tabs tabs={TABS.map((id) => ({ id, label: t(`bq_tab_${id}`) }))} value={tab} onChange={setTab} label={t('bq_setupTitle')} idPrefix="bq-setup" />
      <div role="tabpanel" id={`bq-setup-panel-${tab}`} aria-labelledby={`bq-setup-tab-${tab}`}>
        {tab === 'packages' && <PackagesTab onNotice={setNotice} />}
        {tab === 'extras' && <ExtrasTab onNotice={setNotice} />}
        {tab === 'halls' && <HallsTab onNotice={setNotice} />}
        {tab === 'settings' && <SettingsTab onNotice={setNotice} />}
      </div>
    </>
  );
}

function PackagesTab({ onNotice }: { onNotice: (message: string) => void }) {
  const { t, language } = useI18n();
  const query = useBanquetPackages();
  const [editing, setEditing] = useState<Package | 'new' | null>(null);
  const remove = useDeletePackage({
    onSuccess: (deleted) => onNotice(deleted ? t('bq_packageDeleted') : t('bq_packageArchived')),
  });

  if (query.isError) return <InlineError title={t('loadErrorTitle')} message={errorText(query.error, t)} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;
  if (!query.data) return <CardSkeletons count={2} lines={3} label={t('loading')} />;

  return (
    <div className="flex flex-col gap-3">
      <div>
        <Button icon={Plus} onClick={() => setEditing('new')}>
          {t('bq_packageAdd')}
        </Button>
      </div>
      {query.data.length === 0 ? (
        <EmptyState icon={CalendarHeart} title={t('bq_packagesEmpty')} text={t('bq_packagesEmptyText')} />
      ) : (
        <DataTable caption={t('bq_tab_packages')}>
          <thead>
            <tr>
              <Th>{t('bq_packageName')}</Th>
              <Th numeric>{t('bq_perPersonPrice')}</Th>
              <Th numeric>{t('bq_minimumGuests')}</Th>
              <Th>{t('bq_included')}</Th>
              <Th />
            </tr>
          </thead>
          <tbody>
            {query.data.map((p) => (
              <tr key={p.id}>
                <Td>
                  {p.name}
                  {!p.is_active && <span className="block text-caption text-fg-muted">{t('bq_archived')}</span>}
                </Td>
                <Td numeric>{formatMoney(p.per_person_price, language)}</Td>
                <Td numeric>{p.minimum_guests}</Td>
                <Td muted>{[...p.included, ...p.items.map((item) => `${item.name} ${Number(item.quantity_per_person)} ${t(`bq_unit_${item.unit}`)}`)].join(', ') || '—'}</Td>
                <Td>
                  <div className="flex gap-1">
                    <IconButton icon={Pencil} label={t('bq_editOf', { name: p.name })} onClick={() => setEditing(p)} />
                    <IconButton icon={Trash2} label={t('bq_deleteOf', { name: p.name })} onClick={() => remove.mutate(p.id)} />
                  </div>
                </Td>
              </tr>
            ))}
          </tbody>
        </DataTable>
      )}
      {editing && (
        <PackageDialog
          pkg={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onSaved={(name) => {
            onNotice(t('bq_packageSaved', { name }));
            setEditing(null);
          }}
        />
      )}
    </div>
  );
}

interface ItemRow {
  key: string;
  name: string;
  quantity: string;
  unit: PackageItem['unit'];
  product_id: number | null;
  product_variant_id: number | null;
}

function PackageDialog({ pkg, onClose, onSaved }: { pkg: Package | null; onClose: () => void; onSaved: (name: string) => void }) {
  const { t } = useI18n();
  const [name, setName] = useState(pkg?.name ?? '');
  const [price, setPrice] = useState(pkg ? String(Number(pkg.per_person_price)) : '');
  const [minimum, setMinimum] = useState(pkg ? String(pkg.minimum_guests) : '0');
  const [included, setIncluded] = useState((pkg?.included ?? []).join('\n'));
  const [active, setActive] = useState(pkg?.is_active ?? true);
  const [items, setItems] = useState<ItemRow[]>(() =>
    (pkg?.items ?? []).map((item, i) => ({
      key: `i${i}`,
      name: item.name,
      quantity: String(Number(item.quantity_per_person)),
      unit: item.unit,
      product_id: item.product_id,
      product_variant_id: item.product_variant_id,
    })),
  );
  const [local, setLocal] = useState<string | null>(null);
  const save = useSavePackage();

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const amount = parseDecimal(price);
    const min = Number(minimum);
    if (name.trim() === '' || amount === undefined || amount === null || amount < 0 || !Number.isInteger(min) || min < 0) {
      setLocal(t('bq_err_package'));
      return;
    }
    const rows = [];
    for (const item of items) {
      const q = parseDecimal(item.quantity);
      if (item.name.trim() === '' || q === undefined || q === null || q <= 0) {
        setLocal(t('bq_err_item'));
        return;
      }
      rows.push({
        name: item.name.trim(),
        quantity_per_person: String(q),
        unit: item.unit,
        product_id: item.product_id,
        product_variant_id: item.product_variant_id,
      });
    }
    setLocal(null);
    save.mutate(
      {
        id: pkg?.id ?? null,
        input: {
          name: name.trim(),
          per_person_price: String(amount),
          minimum_guests: min,
          included: included
            .split('\n')
            .map((line) => line.trim())
            .filter((line) => line !== ''),
          is_active: active,
          items: rows,
        },
      },
      {
        onSuccess: () => onSaved(name.trim()),
        onError: (error) => setLocal(errorText(error, t)),
      },
    );
  };

  return (
    <Dialog open title={pkg ? t('bq_packageEdit') : t('bq_packageAdd')} busy={save.isPending} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form noValidate onSubmit={submit} className="flex flex-col gap-4">
        <div className="grid gap-4 sm:grid-cols-3">
          <TextField label={t('bq_packageName')} value={name} onChange={setName} maxLength={120} autoFocus />
          <NumberField label={t('bq_perPersonPrice')} value={price} onChange={setPrice} suffix="so'm" />
          <TextField label={t('bq_minimumGuests')} value={minimum} onChange={setMinimum} inputMode="numeric" />
        </div>
        <label className="flex flex-col gap-1 text-footnote font-bold text-fg-muted">
          {t('bq_included')}
          <textarea value={included} onChange={(e) => setIncluded(e.target.value)} rows={4} className="rounded-control border border-line bg-surface p-2 text-body font-normal text-fg" />
          <span className="font-normal">{t('bq_includedHint')}</span>
        </label>
        <fieldset className="flex flex-col gap-2">
          <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('bq_items')}</legend>
          <p className="text-footnote text-fg-muted">{t('bq_itemsHint')}</p>
          {items.map((item, index) => (
            <div key={item.key} className="flex flex-wrap items-end gap-3">
              <TextField label={t('bq_itemName')} value={item.name} onChange={(value) => setItems(items.map((row, i) => (i === index ? { ...row, name: value } : row)))} maxLength={255} />
              <NumberField label={t('bq_itemQuantity')} value={item.quantity} onChange={(value) => setItems(items.map((row, i) => (i === index ? { ...row, quantity: value } : row)))} />
              <SelectField
                label={t('bq_itemUnit')}
                value={item.unit}
                onChange={(value) => setItems(items.map((row, i) => (i === index ? { ...row, unit: value } : row)))}
                options={UNITS.map((unit) => ({
                  value: unit,
                  label: t(`bq_unit_${unit}`),
                }))}
              />
              <IconButton icon={Trash2} label={t('bq_removeItem', { name: item.name || '—' })} onClick={() => setItems(items.filter((_, i) => i !== index))} />
            </div>
          ))}
          <div>
            <Button
              variant="secondary"
              onClick={() =>
                setItems([
                  ...items,
                  {
                    key: `n${Date.now()}${items.length}`,
                    name: '',
                    quantity: '1',
                    unit: 'portion',
                    product_id: null,
                    product_variant_id: null,
                  },
                ])
              }
            >
              {t('bq_addItem')}
            </Button>
          </div>
        </fieldset>
        <Check checked={active} onChange={setActive} label={t('bq_active')} />
        <FormError message={local} />
        <div className="flex flex-wrap justify-end gap-3">
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={save.isPending}>
            {t('mgmt_save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}

function ExtrasTab({ onNotice }: { onNotice: (message: string) => void }) {
  const { t, language } = useI18n();
  const query = useBanquetExtras();
  const [editing, setEditing] = useState<Extra | 'new' | null>(null);
  const remove = useDeleteExtra({
    onSuccess: (deleted) => onNotice(deleted ? t('bq_extraDeleted') : t('bq_extraArchived')),
  });

  if (query.isError) return <InlineError title={t('loadErrorTitle')} message={errorText(query.error, t)} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;
  if (!query.data) return <CardSkeletons count={2} lines={3} label={t('loading')} />;

  return (
    <div className="flex flex-col gap-3">
      <div>
        <Button icon={Plus} onClick={() => setEditing('new')}>
          {t('bq_extraAdd')}
        </Button>
      </div>
      {query.data.length === 0 ? (
        <EmptyState icon={CalendarHeart} title={t('bq_extrasEmpty')} text={t('bq_extrasEmptyText')} />
      ) : (
        <DataTable caption={t('bq_tab_extras')}>
          <thead>
            <tr>
              <Th>{t('bq_extraName')}</Th>
              <Th numeric>{t('bq_price')}</Th>
              <Th>{t('bq_pricing')}</Th>
              <Th />
            </tr>
          </thead>
          <tbody>
            {query.data.map((extra) => (
              <tr key={extra.id}>
                <Td>
                  {extra.name}
                  {!extra.is_active && <span className="block text-caption text-fg-muted">{t('bq_archived')}</span>}
                </Td>
                <Td numeric>{formatMoney(extra.price, language)}</Td>
                <Td>{extra.pricing === 'per_person' ? t('bq_perPerson') : t('bq_perEvent')}</Td>
                <Td>
                  <div className="flex gap-1">
                    <IconButton icon={Pencil} label={t('bq_editOf', { name: extra.name })} onClick={() => setEditing(extra)} />
                    <IconButton icon={Trash2} label={t('bq_deleteOf', { name: extra.name })} onClick={() => remove.mutate(extra.id)} />
                  </div>
                </Td>
              </tr>
            ))}
          </tbody>
        </DataTable>
      )}
      {editing && (
        <ExtraDialog
          extra={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onSaved={(name) => {
            onNotice(t('bq_extraSaved', { name }));
            setEditing(null);
          }}
        />
      )}
    </div>
  );
}

function ExtraDialog({ extra, onClose, onSaved }: { extra: Extra | null; onClose: () => void; onSaved: (name: string) => void }) {
  const { t } = useI18n();
  const [name, setName] = useState(extra?.name ?? '');
  const [price, setPrice] = useState(extra ? String(Number(extra.price)) : '');
  const [pricing, setPricing] = useState<Pricing>(extra?.pricing ?? 'per_event');
  const [active, setActive] = useState(extra?.is_active ?? true);
  const [local, setLocal] = useState<string | null>(null);
  const save = useSaveExtra();

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const amount = parseDecimal(price);
    if (name.trim() === '' || amount === undefined || amount === null || amount < 0) {
      setLocal(t('bq_err_extra'));
      return;
    }
    setLocal(null);
    save.mutate(
      {
        id: extra?.id ?? null,
        input: {
          name: name.trim(),
          price: String(amount),
          pricing,
          is_active: active,
        },
      },
      {
        onSuccess: () => onSaved(name.trim()),
        onError: (error) => setLocal(errorText(error, t)),
      },
    );
  };

  return (
    <Dialog open title={extra ? t('bq_extraEdit') : t('bq_extraAdd')} busy={save.isPending} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form noValidate onSubmit={submit} className="flex flex-col gap-4">
        <TextField label={t('bq_extraName')} value={name} onChange={setName} maxLength={120} autoFocus />
        <div className="grid gap-4 sm:grid-cols-2">
          <NumberField label={t('bq_price')} value={price} onChange={setPrice} suffix="so'm" />
          <SelectField
            label={t('bq_pricing')}
            value={pricing}
            onChange={setPricing}
            options={[
              { value: 'per_event', label: t('bq_perEvent') },
              { value: 'per_person', label: t('bq_perPerson') },
            ]}
          />
        </div>
        <Check checked={active} onChange={setActive} label={t('bq_active')} />
        <FormError message={local} />
        <div className="flex flex-wrap justify-end gap-3">
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={save.isPending}>
            {t('mgmt_save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}

function HallsTab({ onNotice }: { onNotice: (message: string) => void }) {
  const { t } = useI18n();
  const choice = useBranchChoice();
  const query = useBanquetHalls(choice.branchId);

  if (query.isError) return <InlineError title={t('loadErrorTitle')} message={errorText(query.error, t)} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;
  if (!query.data) return <CardSkeletons count={2} lines={3} label={t('loading')} />;

  return (
    <div className="flex flex-col gap-3">
      {choice.branches.length > 1 && <BranchSelect branches={choice.branches} value={choice.branchId} onChange={choice.choose} className="sm:max-w-72" />}
      <p className="text-footnote text-fg-muted">{t('bq_hallsRentHint')}</p>
      {query.data.length === 0 ? (
        <EmptyState icon={CalendarHeart} title={t('bq_noHalls')} text={t('bq_noHallsText')} />
      ) : (
        query.data.map((hall) => <HallRow key={hall.hall_id} hall={hall} onNotice={onNotice} />)
      )}
    </div>
  );
}

function HallRow({ hall, onNotice }: { hall: HallRates; onNotice: (message: string) => void }) {
  const { t } = useI18n();
  const [rates, setRates] = useState<Record<Slot, string>>(
    () => Object.fromEntries(SLOTS.map((slot) => [slot, hall.rates[slot] !== null ? String(Number(hall.rates[slot])) : ''])) as Record<Slot, string>,
  );
  const [local, setLocal] = useState<string | null>(null);
  const save = useSaveHallRates({
    onSuccess: () => onNotice(t('bq_ratesSaved', { name: hall.hall_name })),
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const out: Partial<Record<Slot, string | null>> = {};
    for (const slot of SLOTS) {
      const value = parseDecimal(rates[slot]);
      if (value === undefined || (value !== null && value < 0)) {
        setLocal(t('mgmt_numberInvalid'));
        return;
      }
      out[slot] = value === null ? null : String(value);
    }
    setLocal(null);
    save.mutate({ hallId: hall.hall_id, rates: out }, { onError: (error) => setLocal(errorText(error, t)) });
  };

  return (
    <Card>
      <form noValidate onSubmit={submit} className="flex flex-col gap-3" aria-label={hall.hall_name}>
        <h3 className="text-headline font-bold">{hall.hall_name}</h3>
        <div className="grid gap-3 sm:grid-cols-4">
          {SLOTS.map((slot) => (
            <NumberField
              key={slot}
              label={t('bq_rentOf', {
                hall: hall.hall_name,
                slot: t(`bq_slot_${slot}`),
              })}
              value={rates[slot]}
              onChange={(value) => setRates({ ...rates, [slot]: value })}
              suffix="so'm"
            />
          ))}
        </div>
        <FormError message={local} />
        <div>
          <Button type="submit" variant="secondary" loading={save.isPending}>
            {t('mgmt_save')}
          </Button>
        </div>
      </form>
    </Card>
  );
}

function SettingsTab({ onNotice }: { onNotice: (message: string) => void }) {
  const { t } = useI18n();
  const query = useBanquetSettings();
  if (query.isError) return <InlineError title={t('loadErrorTitle')} message={errorText(query.error, t)} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;
  if (!query.data) return <CardSkeletons count={1} lines={4} label={t('loading')} />;
  return <SettingsForm key={query.data.version} settings={query.data} onNotice={onNotice} />;
}

function SettingsForm({ settings, onNotice }: { settings: Settings; onNotice: (message: string) => void }) {
  const { t } = useI18n();
  const [slots, setSlots] = useState(settings.slots);
  const [hold, setHold] = useState(String(settings.hold_hours));
  const [thresholds, setThresholds] = useState(settings.guest_warning_thresholds.join(', '));
  const [windowStart, setWindowStart] = useState(settings.window_start);
  const [windowEnd, setWindowEnd] = useState(settings.window_end);
  const [advanceMxik, setAdvanceMxik] = useState(settings.advance_mxik ?? '');
  const [cancelMxik, setCancelMxik] = useState(settings.cancellation_mxik ?? '');
  const [local, setLocal] = useState<string | null>(null);
  const save = useSaveSettings({
    onSuccess: () => onNotice(t('bq_settingsSaved')),
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const hours = Number(hold);
    const limits = thresholds
      .split(/[,\s]+/)
      .filter((part) => part !== '')
      .map(Number);
    if (!Number.isInteger(hours) || hours < 0 || hours > 2160) return setLocal(t('bq_err_hold'));
    if (limits.some((n) => !Number.isInteger(n) || n < 1)) return setLocal(t('bq_err_thresholds'));
    for (const mxik of [advanceMxik, cancelMxik]) if (mxik.trim() !== '' && !/^\d{17}$/.test(mxik.trim())) return setLocal(t('bq_err_mxik'));
    setLocal(null);
    save.mutate(
      {
        slots,
        hold_hours: hours,
        guest_warning_thresholds: limits,
        window_start: windowStart,
        window_end: windowEnd,
        advance_mxik: advanceMxik.trim() === '' ? null : advanceMxik.trim(),
        cancellation_mxik: cancelMxik.trim() === '' ? null : cancelMxik.trim(),
      },
      { onError: (error) => setLocal(errorText(error, t)) },
    );
  };

  return (
    <Card>
      <form noValidate onSubmit={submit} className="flex flex-col gap-4">
        <fieldset className="grid gap-3 sm:grid-cols-3">
          <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('bq_slotTimes')}</legend>
          {NAMED_SLOTS.map((slot) => (
            <div key={slot} className="flex gap-2">
              <TimeField
                label={t('bq_slotStart', { slot: t(`bq_slot_${slot}`) })}
                value={slots[slot].start}
                onChange={(value) =>
                  setSlots({
                    ...slots,
                    [slot]: { ...slots[slot], start: value },
                  })
                }
              />
              <TimeField label={t('bq_slotEnd', { slot: t(`bq_slot_${slot}`) })} value={slots[slot].end} onChange={(value) => setSlots({ ...slots, [slot]: { ...slots[slot], end: value } })} />
            </div>
          ))}
        </fieldset>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label={t('bq_holdHours')} hint={t('bq_holdHoursHint')} value={hold} onChange={setHold} inputMode="numeric" />
          <TextField label={t('bq_thresholds')} hint={t('bq_thresholdsHint')} value={thresholds} onChange={setThresholds} />
          <TimeField label={t('bq_windowStart')} value={windowStart} onChange={setWindowStart} />
          <TimeField label={t('bq_windowEnd')} value={windowEnd} onChange={setWindowEnd} hint={t('bq_windowHint')} />
          <TextField label={t('bq_advanceMxik')} hint={t('bq_mxikHint')} value={advanceMxik} onChange={setAdvanceMxik} inputMode="numeric" maxLength={17} />
          <TextField label={t('bq_cancelMxik')} hint={t('bq_mxikHint')} value={cancelMxik} onChange={setCancelMxik} inputMode="numeric" maxLength={17} />
        </div>
        <FormError message={local} />
        <div>
          <Button type="submit" loading={save.isPending}>
            {t('mgmt_save')}
          </Button>
        </div>
      </form>
    </Card>
  );
}
