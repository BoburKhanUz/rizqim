'use client';

import { useState } from 'react';
import { Check, Layers, X } from 'lucide-react';
import { Button, Card, CardSkeletons, EmptyState, Field, fieldClass, InlineError, Notice, Pill, Section, Switch } from '@rizqim/ui';
import { useCatalog, useUpdatePlan, type FeatureInfo, type Plan, type PlanChange } from '@/lib/api/pos';
import { errorText, fieldError } from '@/lib/errors';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { featureLabel } from './featureLabels';
import { money } from './posModel';
import { Facts, ReasonDialog } from './PosParts';

// Tariflar (P4-24): the tariff catalog (P4-21). Reading needs subscriptions.manage or finance.view; changing a plan needs
// system.manage, a reason and the step-up. A price change reaches the invoices of the months that end after it; the invoices
// already issued keep their lines. The P1-23 plans stay readable for the stores still on them.

export function PlansTab() {
  const t = useT();
  const { can } = useCan();
  const catalog = useCatalog();
  const [editing, setEditing] = useState<Plan | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  if (catalog.isError) return <InlineError title={t('pos_loadError')} message={errorText(catalog.error, t)} retryLabel={t('retry')} retrying={catalog.isFetching} onRetry={() => void catalog.refetch()} />;
  if (!catalog.data) return <CardSkeletons count={3} lines={4} label={t('loading')} />;

  const { plans, feature_catalog: features } = catalog.data;
  const current = plans.filter((plan) => plan.version >= 2);
  const old = plans.filter((plan) => plan.version < 2);
  const manage = can('system.manage');

  return (
    <div className="flex max-w-5xl flex-col gap-4">
      <Notice message={notice} />
      {current.length === 0 ? (
        <EmptyState icon={Layers} title={t('pos_plans_empty')} />
      ) : (
        <ul aria-label={t('pos_plans_list')} className="grid grid-cols-1 gap-3 lg:grid-cols-3">
          {current.map((plan) => (
            <li key={plan.code}>
              <PlanCard plan={plan} features={features} onEdit={manage ? () => setEditing(plan) : undefined} />
            </li>
          ))}
        </ul>
      )}
      {old.length > 0 && (
        <Section title={t('pos_plans_old')}>
          <Card padded={false}>
            <ul aria-label={t('pos_plans_old')} className="divide-y divide-line">
              {old.map((plan) => (
                <li key={plan.code} className="flex flex-wrap items-center justify-between gap-2 p-3 text-body">
                  <span className="font-bold">{plan.name}</span>
                  <span className="text-footnote text-fg-muted">{t('pos_plans_stores', { count: plan.subscriptions })}</span>
                </li>
              ))}
            </ul>
          </Card>
          <p className="px-1 text-caption text-fg-muted">{t('pos_plans_oldNote')}</p>
        </Section>
      )}
      {editing && (
        <PlanDialog
          plan={editing}
          features={features}
          onClose={() => setEditing(null)}
          onDone={(message) => {
            setEditing(null);
            setNotice(message);
          }}
        />
      )}
    </div>
  );
}

function PlanCard({ plan, features, onEdit }: { plan: Plan; features: FeatureInfo[]; onEdit?: () => void }) {
  const t = useT();
  return (
    <Card className="flex h-full flex-col gap-3">
      <div className="flex flex-wrap items-center gap-2">
        <h2 className="text-headline font-bold text-fg">{plan.name}</h2>
        {!plan.is_active && <Pill tone="neutral">{t('pos_plans_inactive')}</Pill>}
        <Pill tone="info">{t('pos_plans_stores', { count: plan.subscriptions })}</Pill>
      </div>
      <Facts
        rows={[
          [t('pos_plans_monthly'), money(plan.monthly_fee)],
          [t('pos_plans_yearly'), money(plan.yearly_fee)],
          [t('pos_plans_pos'), t('pos_plans_included', { count: plan.included_pos, price: money(plan.extra_pos_fee) })],
          [t('pos_plans_kds'), t('pos_plans_included', { count: plan.included_kds, price: money(plan.extra_kds_fee) })],
          [t('pos_plans_trial'), t('pos_plans_days', { count: plan.trial_days })],
        ]}
      />
      <ul aria-label={t('pos_store_features')} className="flex flex-col gap-1">
        {features.map((feature) => {
          const on = plan.features.includes(feature.key);
          return (
            <li key={feature.key} className="flex items-center gap-2 text-footnote">
              {on ? <Check aria-hidden="true" className="size-4 text-success-soft-fg" /> : <X aria-hidden="true" className="size-4 text-fg-muted" />}
              <span className={on ? 'text-fg' : 'text-fg-muted'}>
                {featureLabel(feature.key, t)}
                <span className="sr-only"> — {t(on ? 'pos_feature_on' : 'pos_feature_off')}</span>
              </span>
            </li>
          );
        })}
      </ul>
      {onEdit && (
        <div className="mt-auto">
          <Button variant="secondary" onClick={onEdit} aria-label={t('pos_plans_edit', { name: plan.name })}>
            {t('pos_plans_editShort')}
          </Button>
        </div>
      )}
    </Card>
  );
}

const MONEY = /^\d{1,9}(\.\d{1,2})?$/;
const INTEGER = /^\d{1,3}$/;

function PlanDialog({ plan, features, onDone, onClose }: { plan: Plan; features: FeatureInfo[]; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const update = useUpdatePlan();
  const gated = features.filter((feature) => !feature.base);
  const [form, setForm] = useState({
    name: plan.name,
    is_active: plan.is_active,
    monthly_fee: String(Number(plan.monthly_fee)),
    yearly_fee: String(Number(plan.yearly_fee)),
    included_pos: String(plan.included_pos),
    included_kds: String(plan.included_kds),
    extra_pos_fee: String(Number(plan.extra_pos_fee)),
    extra_kds_fee: String(Number(plan.extra_kds_fee)),
    trial_days: String(plan.trial_days),
    features: gated.filter((feature) => plan.features.includes(feature.key)).map((feature) => feature.key),
  });
  const set = <K extends keyof typeof form>(name: K, value: (typeof form)[K]) => setForm((previous) => ({ ...previous, [name]: value }));
  const clean = (value: string) => value.replace(/\s+/g, '').replace(',', '.');

  const moneyFields = ['monthly_fee', 'yearly_fee', 'extra_pos_fee', 'extra_kds_fee'] as const;
  const countFields = ['included_pos', 'included_kds', 'trial_days'] as const;
  const bad = (name: (typeof moneyFields)[number] | (typeof countFields)[number]) =>
    (moneyFields as readonly string[]).includes(name) ? !MONEY.test(clean(form[name])) : !INTEGER.test(form[name].trim());

  const change = (): PlanChange => {
    const result: PlanChange = {};
    if (form.name.trim() !== plan.name) result.name = form.name.trim();
    if (form.is_active !== plan.is_active) result.is_active = form.is_active;
    for (const name of moneyFields) if (Number(clean(form[name])) !== Number(plan[name])) result[name] = clean(form[name]);
    for (const name of countFields) if (Number(form[name]) !== plan[name]) result[name] = Number(form[name]);
    const before = gated.filter((feature) => plan.features.includes(feature.key)).map((feature) => feature.key).sort().join(',');
    if ([...form.features].sort().join(',') !== before) result.features = form.features;
    return result;
  };
  const changes = change();
  const valid = form.name.trim().length >= 2 && ![...moneyFields, ...countFields].some(bad);

  const LABEL = {
    monthly_fee: 'pos_plans_monthly',
    yearly_fee: 'pos_plans_yearly',
    extra_pos_fee: 'pos_plans_extraPos',
    extra_kds_fee: 'pos_plans_extraKds',
    included_pos: 'pos_plans_includedPos',
    included_kds: 'pos_plans_includedKds',
    trial_days: 'pos_plans_trial',
  } as const;

  return (
    <ReasonDialog
      title={t('pos_plans_editTitle', { name: plan.name })}
      text={t('pos_plans_editText')}
      submitLabel={t('save')}
      busy={update.isPending}
      error={update.error ? errorText(update.error, t) : null}
      reasonError={fieldError(update.error, 'reason')}
      canSubmit={valid && Object.keys(changes).length > 0}
      onClose={onClose}
      onSubmit={(reason) => update.mutate({ code: plan.code, ...changes, reason }, { onSuccess: () => onDone(t('pos_plans_saved', { name: plan.name })) })}
    >
      <Field label={t('pos_plans_name')} error={fieldError(update.error, 'name')}>
        {(control) => <input {...control} data-autofocus type="text" className={fieldClass} value={form.name} maxLength={100} onChange={(event) => set('name', event.target.value)} />}
      </Field>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {[...moneyFields, ...countFields].map((name) => (
          <Field key={name} label={t(LABEL[name])} error={bad(name) ? t('pos_plans_invalid') : fieldError(update.error, name)}>
            {(control) => <input {...control} type="text" inputMode="decimal" className={fieldClass} value={form[name]} onChange={(event) => set(name, event.target.value)} />}
          </Field>
        ))}
      </div>
      <fieldset className="flex flex-col gap-2">
        <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('pos_plans_features')}</legend>
        {gated.map((feature) => (
          <Switch
            key={feature.key}
            checked={form.features.includes(feature.key)}
            onChange={(on) => set('features', on ? [...form.features, feature.key] : form.features.filter((key) => key !== feature.key))}
            label={featureLabel(feature.key, t)}
          />
        ))}
      </fieldset>
      <Switch checked={form.is_active} onChange={(on) => set('is_active', on)} label={t('pos_plans_active')} />
    </ReasonDialog>
  );
}
