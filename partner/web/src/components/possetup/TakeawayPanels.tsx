'use client';

import { useMemo, useState, type FormEvent } from 'react';
import { Package, Pencil, Plus, Save, Trash2 } from 'lucide-react';
import { Button, Card, EmptyState, FormError, Notice, Pill } from '@rizqim/ui';
import {
  formatMoney,
  PACKAGING_BASES,
  PACKAGING_ORDER_TYPES,
  useBranchPosSettings,
  useBusinessDays,
  useChangePackagingRule,
  useOwnerCategories,
  useOwnerProducts,
  usePackagingRules,
  usePickupSettings,
  useSavePickupSettings,
  type BusinessDayReport,
  type PackagingBasis,
  type PackagingOrderType,
  type PackagingRule,
  type PackagingRuleInput,
  type PickupSettings,
  type PickupSettingsPatch,
} from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Check, NumberField, parseDecimal } from '../management/fields';
import { errorText } from '../management/errors';
import { Gate } from '../pos/Gate';
import { DateField, SelectField, TextField } from '../pos/fields';
import { changedKeys, useServerDraft } from '../pos/serverDraft';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';

// P6-34 tabs of the POS setup page (docs/API.md "Qadoq, 24/7 va olib ketish vaqti"): the branch's packaging rules, the rules of
// a pickup for later and the business days of a 24/7 branch. Each shows the module's note when the module is off. The limits
// are the server's; the forms check the same ones so a typo does not need a round trip.

const moduleOff = (text: string) => (
  <p role="note" className="rounded-control bg-info-soft px-3 py-2 text-footnote font-bold text-info-soft-fg">
    {text}
  </p>
);

// A whole number in [min, max] typed as text; undefined when it is not one
function whole(value: string, min: number, max: number): number | undefined {
  const parsed = parseDecimal(value);
  return parsed !== undefined && parsed !== null && Number.isInteger(parsed) && parsed >= min && parsed <= max ? parsed : undefined;
}

// --- Qadoq qoidalari ---------------------------------------------------------------------------------------------------------

type Target = 'category' | 'product';

interface RuleDraft {
  name: string;
  basis: PackagingBasis;
  target: Target;
  categoryId: string;
  productId: string;
  quantity: string;
  price: string;
  mxik: string;
  packageCode: string;
  unit: string;
  types: PackagingOrderType[];
  active: boolean;
}

const EMPTY_RULE: RuleDraft = { name: '', basis: 'order', target: 'category', categoryId: '', productId: '', quantity: '1', price: '', mxik: '', packageCode: '', unit: 'dona', types: ['takeaway', 'pickup', 'delivery'], active: true };

function ruleDraft(rule: PackagingRule): RuleDraft {
  return {
    name: rule.name,
    basis: rule.basis,
    target: rule.product_id !== null ? 'product' : 'category',
    categoryId: rule.category_id !== null ? String(rule.category_id) : '',
    productId: rule.product_id !== null ? String(rule.product_id) : '',
    quantity: String(rule.quantity),
    price: String(Number(rule.unit_price)),
    mxik: rule.mxik ?? '',
    packageCode: rule.package_code ?? '',
    unit: rule.unit,
    types: rule.order_types,
    active: rule.is_active,
  };
}

const RULE_FIELDS = ['name', 'basis', 'category_id', 'product_id', 'quantity', 'unit_price', 'mxik', 'package_code', 'unit', 'order_types', 'is_active'];

export function PackagingRulesPanel({ branchId, enabled }: { branchId: number; enabled: boolean }) {
  const { t } = useI18n();
  const rules = usePackagingRules(enabled ? branchId : null);
  if (!enabled) return moduleOff(t('tkw_pkg_moduleOff'));
  return <Gate query={rules} count={2}>{(data) => <RulesList key={branchId} branchId={branchId} rules={data} />}</Gate>;
}

function RulesList({ branchId, rules }: { branchId: number; rules: PackagingRule[] }) {
  const { t, language } = useI18n();
  const categories = useOwnerCategories();
  const products = useOwnerProducts();
  const change = useChangePackagingRule();
  // null: no form open; 0: a new rule; else the rule being edited
  const [editing, setEditing] = useState<number | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [failure, setFailure] = useState<string | null>(null);

  const categoryName = useMemo(() => new Map((categories.data ?? []).map((category) => [category.id, category.name])), [categories.data]);
  const productName = useMemo(() => new Map((products.data ?? []).map((product) => [product.id, product.name])), [products.data]);
  const targetOf = (rule: PackagingRule) => (rule.product_id !== null ? (productName.get(rule.product_id) ?? `#${rule.product_id}`) : rule.category_id !== null ? (categoryName.get(rule.category_id) ?? `#${rule.category_id}`) : '');

  const remove = (rule: PackagingRule) => {
    if (!window.confirm(t('tkw_pkg_deleteConfirm', { name: rule.name }))) return;
    setNotice(null);
    setFailure(null);
    change.mutate({ action: 'delete', branchId, ruleId: rule.id }, { onSuccess: () => setNotice(t('tkw_pkg_deleted')), onError: (error) => setFailure(errorText(error, t)) });
  };

  return (
    <div className="flex max-w-4xl flex-col gap-4">
      <p className="text-footnote text-fg-muted">{t('tkw_pkg_intro')}</p>
      <Notice message={notice} />
      <FormError message={failure} />

      {rules.length === 0 && editing === null && <EmptyState icon={Package} title={t('tkw_pkg_empty')} text={t('tkw_pkg_emptyText')} />}

      <ul className="flex flex-col gap-3" aria-label={t('tkw_tab_packaging')}>
        {rules.map((rule) =>
          editing === rule.id ? (
            <li key={rule.id}>
              <RuleForm branchId={branchId} rule={rule} onDone={(message) => { setEditing(null); setNotice(message); }} />
            </li>
          ) : (
            <li key={rule.id}>
              <Card className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="text-body font-bold text-fg">{rule.name}</span>
                    <Pill tone={rule.is_active ? 'success' : 'neutral'}>{rule.is_active ? t('tkw_pkg_active') : t('tkw_pkg_inactive')}</Pill>
                  </div>
                  <span className="text-footnote text-fg-muted">
                    {rule.basis === 'order' ? t('tkw_pkg_perOrder', { n: rule.quantity }) : t('tkw_pkg_perItem', { n: rule.quantity, target: targetOf(rule) })}
                    {' · '}
                    {formatMoney(rule.unit_price, language)}
                    {' · '}
                    {t('tkw_pkg_mxik')}: {rule.mxik ?? t('tkw_pkg_defaultMxik')}
                  </span>
                  <span className="text-footnote text-fg-muted">{rule.order_types.map((type) => t(`tkw_pkg_type_${type}`)).join(', ')}</span>
                </div>
                <div className="flex gap-2">
                  <Button variant="secondary" icon={Pencil} onClick={() => { setEditing(rule.id); setNotice(null); }}>
                    {t('tkw_pkg_edit')}
                  </Button>
                  <Button variant="secondary" icon={Trash2} onClick={() => remove(rule)} loading={change.isPending && change.variables?.action === 'delete' && change.variables.ruleId === rule.id}>
                    {t('tkw_pkg_delete')}
                  </Button>
                </div>
              </Card>
            </li>
          ),
        )}
      </ul>

      {editing === 0 ? (
        <RuleForm branchId={branchId} rule={null} onDone={(message) => { setEditing(null); setNotice(message); }} />
      ) : (
        <div>
          <Button icon={Plus} onClick={() => { setEditing(0); setNotice(null); }} disabled={editing !== null}>
            {t('tkw_pkg_add')}
          </Button>
        </div>
      )}
    </div>
  );
}

function RuleForm({ branchId, rule, onDone }: { branchId: number; rule: PackagingRule | null; onDone: (message: string | null) => void }) {
  const { t } = useI18n();
  const categories = useOwnerCategories();
  const products = useOwnerProducts();
  const change = useChangePackagingRule();
  const [draft, setDraft] = useState<RuleDraft>(rule ? ruleDraft(rule) : EMPTY_RULE);
  const [local, setLocal] = useState<Record<string, string>>({});
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const patch = (changes: Partial<RuleDraft>) => setDraft((current) => ({ ...current, ...changes }));
  const toggleType = (type: PackagingOrderType, on: boolean) => patch({ types: on ? [...draft.types.filter((item) => item !== type), type] : draft.types.filter((item) => item !== type) });

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const problems: Record<string, string> = {};
    if (draft.name.trim() === '') problems.name = t('tkw_pkg_err_name');
    const price = parseDecimal(draft.price);
    if (price === undefined || price === null || price < 0 || price > 9_999_999 || Math.abs(price * 100 - Math.round(price * 100)) > 1e-6) problems.price = t('tkw_pkg_err_price');
    const quantity = whole(draft.quantity, 1, 20);
    if (quantity === undefined) problems.quantity = t('tkw_pkg_err_quantity');
    const targetId = draft.basis === 'item' ? (draft.target === 'category' ? draft.categoryId : draft.productId) : '';
    if (draft.basis === 'item' && targetId === '') problems.target = t('tkw_pkg_err_target');
    if (draft.types.length === 0) problems.types = t('tkw_pkg_err_types');
    if (draft.mxik.trim() !== '' && !/^\d{17}$/.test(draft.mxik.trim())) problems.mxik = t('tkw_pkg_err_mxik');
    if (draft.packageCode.trim() !== '' && !/^\d{1,20}$/.test(draft.packageCode.trim())) problems.packageCode = t('tkw_pkg_err_packageCode');
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;

    const input: PackagingRuleInput = {
      name: draft.name.trim(),
      basis: draft.basis,
      category_id: draft.basis === 'item' && draft.target === 'category' ? Number(targetId) : null,
      product_id: draft.basis === 'item' && draft.target === 'product' ? Number(targetId) : null,
      quantity: quantity as number,
      unit_price: (price as number).toFixed(2),
      mxik: draft.mxik.trim() || null,
      package_code: draft.packageCode.trim() || null,
      unit: draft.unit.trim() || 'dona',
      // In the server's order, whatever order they were ticked in
      order_types: PACKAGING_ORDER_TYPES.filter((type) => draft.types.includes(type)),
      is_active: draft.active,
    };
    const done = () => onDone(t('tkw_pkg_saved'));
    const failed = (error: unknown) => setErrors(sortServerErrors(error, RULE_FIELDS, t));
    if (rule === null) change.mutate({ action: 'create', branchId, input }, { onSuccess: done, onError: failed });
    else change.mutate({ action: 'update', branchId, ruleId: rule.id, patch: input }, { onSuccess: done, onError: failed });
  };

  const field = (name: string) => errors.fields[name];
  const title = rule === null ? t('tkw_pkg_add') : rule.name;

  return (
    <Card>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4" aria-label={title}>
        <h2 className="text-headline font-bold text-fg">{title}</h2>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label={t('tkw_pkg_name')} value={draft.name} onChange={(name) => patch({ name })} placeholder={t('tkw_pkg_namePlaceholder')} maxLength={128} error={local.name ?? field('name')} autoFocus />
          <SelectField label={t('tkw_pkg_basis')} value={draft.basis} onChange={(basis) => patch({ basis })} options={PACKAGING_BASES.map((value) => ({ value, label: t(`tkw_pkg_basis_${value}`) }))} error={field('basis')} />
        </div>
        {draft.basis === 'item' && (
          <div className="grid gap-4 sm:grid-cols-2">
            <SelectField<Target> label={t('tkw_pkg_target')} value={draft.target} onChange={(target) => patch({ target })} options={[{ value: 'category', label: t('tkw_pkg_target_category') }, { value: 'product', label: t('tkw_pkg_target_product') }]} />
            {draft.target === 'category' ? (
              <SelectField
                label={t('tkw_pkg_target_category')}
                value={draft.categoryId}
                onChange={(categoryId) => patch({ categoryId })}
                options={[{ value: '', label: t('tkw_pkg_chooseCategory') }, ...(categories.data ?? []).map((category) => ({ value: String(category.id), label: category.name }))]}
                error={local.target ?? field('category_id')}
              />
            ) : (
              <SelectField
                label={t('tkw_pkg_target_product')}
                value={draft.productId}
                onChange={(productId) => patch({ productId })}
                options={[{ value: '', label: t('tkw_pkg_chooseProduct') }, ...(products.data ?? []).map((product) => ({ value: String(product.id), label: product.name }))]}
                error={local.target ?? field('product_id')}
              />
            )}
          </div>
        )}
        <div className="grid gap-4 sm:grid-cols-3">
          <NumberField label={t('tkw_pkg_quantity')} hint={t('tkw_pkg_quantityHint')} value={draft.quantity} onChange={(quantity) => patch({ quantity })} inputMode="numeric" error={local.quantity ?? field('quantity')} />
          <NumberField label={t('tkw_pkg_price')} value={draft.price} onChange={(price) => patch({ price })} suffix="so'm" error={local.price ?? field('unit_price')} />
          <TextField label={t('tkw_pkg_unit')} value={draft.unit} onChange={(unit) => patch({ unit })} maxLength={32} error={field('unit')} />
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label={t('tkw_pkg_mxik')} hint={t('tkw_pkg_mxikHint')} value={draft.mxik} onChange={(mxik) => patch({ mxik })} inputMode="numeric" maxLength={17} error={local.mxik ?? field('mxik')} />
          <TextField label={t('tkw_pkg_packageCode')} value={draft.packageCode} onChange={(packageCode) => patch({ packageCode })} inputMode="numeric" maxLength={20} error={local.packageCode ?? field('package_code')} />
        </div>
        <fieldset className="flex flex-col gap-1">
          <legend className="text-body font-bold text-fg">{t('tkw_pkg_types')}</legend>
          {PACKAGING_ORDER_TYPES.map((type) => (
            <Check key={type} checked={draft.types.includes(type)} onChange={(on) => toggleType(type, on)} label={t(`tkw_pkg_type_${type}`)} />
          ))}
          {(local.types ?? field('order_types')) && <p className="text-footnote font-bold text-danger">{local.types ?? field('order_types')}</p>}
        </fieldset>
        <Check checked={draft.active} onChange={(active) => patch({ active })} label={t('tkw_pkg_active')} />
        <FormError message={errors.form} />
        <div className="flex gap-2">
          <Button type="submit" icon={Save} loading={change.isPending}>
            {t('tkw_pkg_save')}
          </Button>
          <Button type="button" variant="secondary" onClick={() => onDone(null)}>
            {t('tkw_pkg_cancel')}
          </Button>
        </div>
      </form>
    </Card>
  );
}

// --- Oldindan buyurtma (olib ketish vaqti) ------------------------------------------------------------------------------------

interface PickupDraft {
  lead: string;
  slot: string;
  days: string;
  prep: string;
}

const pickupDraft = (settings: PickupSettings): PickupDraft => ({
  lead: String(settings.pickup_min_lead_minutes),
  slot: String(settings.pickup_slot_minutes),
  days: String(settings.pickup_max_days_ahead),
  prep: String(settings.pickup_prep_minutes),
});

const PICKUP_FIELDS = ['pickup_min_lead_minutes', 'pickup_slot_minutes', 'pickup_max_days_ahead', 'pickup_prep_minutes'];

export function PickupSettingsPanel({ branchId, enabled }: { branchId: number; enabled: boolean }) {
  const { t } = useI18n();
  const settings = usePickupSettings(enabled ? branchId : null);
  if (!enabled) return moduleOff(t('tkw_pick_moduleOff'));
  return <Gate query={settings} count={2}>{(data) => <PickupForm key={branchId} branchId={branchId} data={data} />}</Gate>;
}

function PickupForm({ branchId, data }: { branchId: number; data: PickupSettings }) {
  const { t } = useI18n();
  const save = useSavePickupSettings();
  const { draft, setDraft, base, dirty } = useServerDraft(data, pickupDraft);
  const [local, setLocal] = useState<Record<string, string>>({});
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [saved, setSaved] = useState<string | null>(null);
  const patch = (changes: Partial<PickupDraft>) => {
    setDraft((current) => ({ ...current, ...changes }));
    setSaved(null);
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setSaved(null);
    const values = { lead: whole(draft.lead, 0, 720), slot: whole(draft.slot, 5, 120), days: whole(draft.days, 0, 7), prep: whole(draft.prep, 0, 240) };
    const problems: Record<string, string> = {};
    if (values.lead === undefined) problems.lead = t('tkw_pick_err_lead');
    if (values.slot === undefined) problems.slot = t('tkw_pick_err_slot');
    if (values.days === undefined) problems.days = t('tkw_pick_err_days');
    if (values.prep === undefined) problems.prep = t('tkw_pick_err_prep');
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;

    const changed = new Set(changedKeys(draft, base));
    const body: PickupSettingsPatch = {};
    if (changed.has('lead')) body.pickup_min_lead_minutes = values.lead as number;
    if (changed.has('slot')) body.pickup_slot_minutes = values.slot as number;
    if (changed.has('days')) body.pickup_max_days_ahead = values.days as number;
    if (changed.has('prep')) body.pickup_prep_minutes = values.prep as number;
    save.mutate(
      { branchId, patch: body },
      {
        onSuccess: (answer) => {
          setDraft(pickupDraft(answer));
          setSaved(t('tkw_pick_saved'));
        },
        onError: (error) => setErrors(sortServerErrors(error, PICKUP_FIELDS, t)),
      },
    );
  };

  const hours = data.round_the_clock
    ? t('tkw_pick_roundClock')
    : data.opening_hours.open && data.opening_hours.close && data.opening_hours.open !== data.opening_hours.close
      ? t('tkw_pick_hours', { open: data.opening_hours.open, close: data.opening_hours.close })
      : t('tkw_pick_hoursAllDay');

  return (
    <form onSubmit={submit} noValidate className="flex max-w-4xl flex-col gap-5">
      <p className="text-footnote text-fg-muted">{t('tkw_pick_intro')}</p>
      <Notice message={saved} />
      <Card className="flex flex-col gap-4">
        <p className="text-footnote font-bold text-fg">{hours}</p>
        <div className="grid gap-4 sm:grid-cols-2">
          <NumberField label={t('tkw_pick_lead')} hint={t('tkw_pick_leadHint')} value={draft.lead} onChange={(lead) => patch({ lead })} inputMode="numeric" suffix="daq" error={local.lead ?? errors.fields.pickup_min_lead_minutes} />
          <NumberField label={t('tkw_pick_slot')} hint={t('tkw_pick_slotHint')} value={draft.slot} onChange={(slot) => patch({ slot })} inputMode="numeric" suffix="daq" error={local.slot ?? errors.fields.pickup_slot_minutes} />
          <NumberField label={t('tkw_pick_days')} hint={t('tkw_pick_daysHint')} value={draft.days} onChange={(days) => patch({ days })} inputMode="numeric" suffix="kun" error={local.days ?? errors.fields.pickup_max_days_ahead} />
          <NumberField label={t('tkw_pick_prep')} hint={t('tkw_pick_prepHint')} value={draft.prep} onChange={(prep) => patch({ prep })} inputMode="numeric" suffix="daq" error={local.prep ?? errors.fields.pickup_prep_minutes} />
        </div>
      </Card>
      <FormError message={errors.form} />
      <div>
        <Button type="submit" size="lg" icon={Save} loading={save.isPending} disabled={!dirty}>
          {t('tkw_pick_save')}
        </Button>
      </div>
    </form>
  );
}

// --- 24/7: biznes kunlar ------------------------------------------------------------------------------------------------------

// "05:00" → "04:59": the last minute before the day start
function minuteBefore(start: string): string {
  const [hour, minute] = start.split(':').map(Number);
  const total = ((hour ?? 0) * 60 + (minute ?? 0) + 1439) % 1440;
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

const isoDay = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

export function RoundTheClockPanel({ branchId, enabled }: { branchId: number; enabled: boolean }) {
  const { t } = useI18n();
  const settings = useBranchPosSettings(enabled ? branchId : null);
  const [range, setRange] = useState<{ from: string; to: string } | null>(null);
  const [typed, setTyped] = useState<{ from: string; to: string }>({ from: '', to: '' });
  const [rangeError, setRangeError] = useState<string | null>(null);
  const report = useBusinessDays(enabled ? { branchId, ...(range ?? {}) } : null);
  if (!enabled) return moduleOff(t('tkw_rtc_moduleOff'));

  const start = settings.data?.business_day_starts_at ?? report.data?.business_day.starts_at ?? '05:00';
  const apply = (event: FormEvent) => {
    event.preventDefault();
    if (typed.from === '' || typed.to === '') return;
    const days = (Date.parse(`${typed.to}T00:00:00Z`) - Date.parse(`${typed.from}T00:00:00Z`)) / 86_400_000;
    if (!(days >= 0 && days < 31)) {
      setRangeError(t('tkw_rtc_err_range'));
      return;
    }
    setRangeError(null);
    setRange({ ...typed });
  };

  return (
    <div className="flex max-w-5xl flex-col gap-4">
      <Card className="flex flex-col gap-2">
        <p className="text-body text-fg">{t('tkw_rtc_intro', { start, before: minuteBefore(start) })}</p>
        <p className="text-footnote text-fg-muted">{t('tkw_rtc_rules')}</p>
        <p className="text-footnote text-fg-muted">{t('tkw_rtc_dayStartHint')}</p>
      </Card>
      <form onSubmit={apply} className="flex flex-wrap items-end gap-3" aria-label={t('tkw_rtc_reportTitle')}>
        <DateField label={t('tkw_rtc_from')} value={typed.from} onChange={(from) => setTyped((current) => ({ ...current, from }))} />
        <DateField label={t('tkw_rtc_to')} value={typed.to} onChange={(to) => setTyped((current) => ({ ...current, to }))} />
        <Button type="submit" variant="secondary">
          {t('tkw_rtc_reportTitle')}
        </Button>
      </form>
      <FormError message={rangeError} />
      <Gate query={report} count={2}>
        {(data) => <BusinessDays data={data} />}
      </Gate>
    </div>
  );
}

function BusinessDays({ data }: { data: BusinessDayReport }) {
  const { t, language } = useI18n();
  const next = new Date(data.business_day.next_starts_at);
  return (
    <Card className="flex flex-col gap-3 overflow-x-auto">
      <p className="text-footnote font-bold text-fg">{t('tkw_rtc_today', { date: data.business_day.business_date, next: `${isoDay(next)} ${String(next.getHours()).padStart(2, '0')}:${String(next.getMinutes()).padStart(2, '0')}` })}</p>
      <table className="w-full min-w-[640px] text-left text-footnote">
        <caption className="sr-only">{t('tkw_rtc_reportTitle')}</caption>
        <thead className="text-fg-muted">
          <tr>
            <th scope="col" className="py-2 pr-3">{t('tkw_rtc_col_day')}</th>
            <th scope="col" className="py-2 pr-3 text-right">{t('tkw_rtc_col_orders')}</th>
            <th scope="col" className="py-2 pr-3 text-right">{t('tkw_rtc_col_afterMidnight')}</th>
            <th scope="col" className="py-2 pr-3 text-right">{t('tkw_rtc_col_revenue')}</th>
            <th scope="col" className="py-2 pr-3 text-right">{t('tkw_rtc_col_packaging')}</th>
            <th scope="col" className="py-2">{t('tkw_rtc_col_shifts')}</th>
          </tr>
        </thead>
        <tbody>
          {[...data.days].reverse().map((day) => (
            <tr key={day.business_date} className="border-t border-line">
              <th scope="row" className="py-2 pr-3 font-bold text-fg">{day.business_date}</th>
              <td className="py-2 pr-3 text-right tabular-nums">{day.orders}</td>
              <td className="py-2 pr-3 text-right tabular-nums">{day.after_midnight_orders}</td>
              <td className="py-2 pr-3 text-right tabular-nums">{formatMoney(day.revenue, language)}</td>
              <td className="py-2 pr-3 text-right tabular-nums">{formatMoney(day.packaging_amount, language)}</td>
              <td className="py-2">
                {day.shifts.length === 0
                  ? t('tkw_rtc_noShifts')
                  : day.shifts.map((shift) => (shift.z_number !== null ? t('tkw_rtc_shift', { n: shift.z_number }) : t('tkw_rtc_shiftOpen'))).join(', ')}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </Card>
  );
}
