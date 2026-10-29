'use client';

import { useState } from 'react';
import { Check, Lock, RotateCcw, Sparkles } from 'lucide-react';
import { Button, Card, cx, FormError, Notice, Pill, Switch } from '@rizqim/ui';
import { POS_MODES, useApplyFormatTemplate, usePosFormat, useSavePosFormat, type FormatProfileOption, type PosFormat, type PosFormatPatch, type PosModuleRow, type PosMode } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { ConfirmDialog } from '../management/ConfirmDialog';
import { errorText } from '../management/errors';
import { Gate } from '../pos/Gate';
import { SelectField } from '../pos/fields';
import { labelFor } from '../reports/format';

// The branch's format (P6-11): the profile picker, the till's mode, the module switches over the profile's defaults (the plan's
// "Asosiy tarifda bor" and the law's locks come from the server's module rows) and the profile's sample stations and menu.
// Every change is one PUT the server answers with the whole format, which replaces what is shown.
export function FormatPanel({ branchId }: { branchId: number }) {
  const format = usePosFormat(branchId);
  return <Gate query={format} count={3}>{(data) => <FormatForm key={branchId} branchId={branchId} data={data} />}</Gate>;
}

function FormatForm({ branchId, data }: { branchId: number; data: PosFormat }) {
  const { t } = useI18n();
  const save = useSavePosFormat();
  const template = useApplyFormatTemplate();
  const [asking, setAsking] = useState<FormatProfileOption | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const send = (patch: Omit<PosFormatPatch, 'branch_id'>, done: (answer: PosFormat) => string) => {
    setNotice(null);
    setError(null);
    save.mutate(
      { branch_id: branchId, ...patch },
      {
        onSuccess: (answer) => {
          setAsking(null);
          setNotice(done(answer));
        },
        onError: (failure) => setError(errorText(failure, t)),
      },
    );
  };

  const current = data.profiles.find((profile) => profile.key === data.profile);
  const mode = (POS_MODES as readonly string[]).includes(data.mode) ? (data.mode as PosMode) : 'hybrid';

  return (
    <div className="flex max-w-4xl flex-col gap-5">
      <p className="text-footnote text-fg-muted">{t('fmt_intro')}</p>
      <Notice message={notice} />
      {!asking && <FormError message={error} />}

      <Card className="flex flex-col gap-4">
        <h2 className="text-headline font-bold text-fg">{t('fmt_profile_title')}</h2>
        <div role="radiogroup" aria-label={t('fmt_profile_group')} className="grid gap-3 sm:grid-cols-2">
          {data.profiles.map((profile) => {
            const selected = profile.key === data.profile;
            return (
              <button
                key={profile.key}
                type="button"
                role="radio"
                aria-checked={selected}
                disabled={save.isPending}
                onClick={() => (selected ? undefined : setAsking(profile))}
                className={cx(
                  'flex flex-col items-start gap-1.5 rounded-card border p-3 text-left',
                  'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-60',
                  selected ? 'border-brand bg-brand-soft' : 'border-line bg-surface hover:bg-surface-2',
                )}
              >
                <span className="flex w-full items-center justify-between gap-2">
                  <span className="text-body font-bold text-fg">{profile.label}</span>
                  {selected && (
                    <Pill tone="success" icon={Check}>
                      {t('fmt_current')}
                    </Pill>
                  )}
                </span>
                <span className="text-footnote text-fg-muted">{profile.description}</span>
                <span className="flex flex-wrap gap-1.5">
                  <Pill tone="neutral">{t('fmt_recommended', { plan: labelFor(t, 'fmt_plan_', profile.recommended_plan) })}</Pill>
                  {profile.locks_service_charge && <Pill tone="info" icon={Lock}>{t('fmt_lawLock')}</Pill>}
                </span>
              </button>
            );
          })}
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField
            label={t('fmt_mode')}
            hint={t('fmt_firstScreen', { screen: labelFor(t, 'fmt_screen_', data.first_screen) })}
            value={mode}
            disabled={save.isPending}
            onChange={(next) => send({ mode: next }, () => t('fmt_module_saved'))}
            options={POS_MODES.map((value) => ({ value, label: labelFor(t, 'pos_mode_', value) }))}
          />
        </div>
      </Card>

      <Card className="flex flex-col gap-3">
        <h2 className="text-headline font-bold text-fg">{t('fmt_modules_title')}</h2>
        <p className="text-footnote text-fg-muted">{t('fmt_modules_intro')}</p>
        <ul className="flex flex-col divide-y divide-line">
          {data.module_rows.map((row) => (
            <ModuleRow key={row.key} row={row} busy={save.isPending} onChange={(on) => send({ modules: { [row.key]: on } }, () => t('fmt_module_saved'))} />
          ))}
        </ul>
      </Card>

      <Card className="flex flex-col gap-3">
        <h2 className="text-headline font-bold text-fg">{t('fmt_template_title')}</h2>
        <p className="text-footnote text-fg-muted">{t('fmt_template_intro', { profile: current?.label ?? data.profile_label })}</p>
        <div className="grid gap-4 sm:grid-cols-2">
          <TemplateList title={t('fmt_template_stations')} empty={t('fmt_template_none')} items={data.template.stations.map((station) => ({ name: `${station.name} (${station.kind_label})`, exists: station.exists }))} />
          <TemplateList title={t('fmt_template_dishes')} empty={t('fmt_template_none')} items={data.template.dishes.map((dish) => ({ name: `${dish.name} — ${dish.variants.join(', ')}`, exists: dish.exists }))} />
        </div>
        <Button
          type="button"
          icon={Sparkles}
          className="w-fit"
          loading={template.isPending}
          disabled={[...data.template.stations, ...data.template.dishes].every((item) => item.exists)}
          onClick={() => {
            setNotice(null);
            setError(null);
            template.mutate(
              { branch_id: branchId },
              {
                onSuccess: (result) => {
                  const added = { stations: result.stations_created.length, dishes: result.dishes_created.length };
                  setNotice(added.stations + added.dishes > 0 ? t('fmt_template_added', added) : t('fmt_template_nothing'));
                },
                onError: (failure) => setError(errorText(failure, t)),
              },
            );
          }}
        >
          {t('fmt_template_apply')}
        </Button>
      </Card>

      {asking && (
        <ConfirmDialog
          title={t('fmt_change_title')}
          text={t('fmt_change_text', { profile: asking.label })}
          confirmLabel={t('fmt_change_confirm')}
          tone="primary"
          busy={save.isPending}
          error={error}
          onConfirm={() => send({ profile: asking.key }, () => t('fmt_changed', { profile: asking.label }))}
          onClose={() => {
            setAsking(null);
            setError(null);
          }}
        />
      )}
    </div>
  );
}

function ModuleRow({ row, busy, onChange }: { row: PosModuleRow; busy: boolean; onChange: (on: boolean | null) => void }) {
  const { t } = useI18n();
  return (
    <li className="flex items-start justify-between gap-3 py-3">
      <div className="flex min-w-0 flex-col gap-1">
        <span className="text-body font-bold text-fg">{row.label}</span>
        <span className="text-footnote text-fg-muted">{row.description}</span>
        <span className="flex flex-wrap items-center gap-1.5">
          {row.locked === 'law' && <Pill tone="info" icon={Lock}>{t('fmt_module_law')}</Pill>}
          {row.locked === 'plan' && <Pill tone="info" icon={Lock}>{row.required_plan_name ? t('billing_inPlan', { plan: row.required_plan_name }) : t('billing_notInPlan')}</Pill>}
          {row.override === null ? (
            <span className="text-caption text-fg-muted">{t('fmt_module_default')}</span>
          ) : (
            <>
              <span className="text-caption text-fg-muted">{t('fmt_module_changed')}</span>
              <Button type="button" variant="ghost" icon={RotateCcw} disabled={busy} aria-label={t('fmt_module_resetLabel', { module: row.label })} onClick={() => onChange(null)}>
                {t('fmt_module_reset')}
              </Button>
            </>
          )}
        </span>
      </div>
      <Switch checked={row.enabled} disabled={busy || (row.locked !== null && !row.enabled)} label={t('fmt_module_switch', { module: row.label })} onChange={(on) => onChange(on)} />
    </li>
  );
}

function TemplateList({ title, empty, items }: { title: string; empty: string; items: Array<{ name: string; exists: boolean }> }) {
  const { t } = useI18n();
  return (
    <section aria-label={title} className="flex flex-col gap-2">
      <h3 className="text-body font-bold text-fg">{title}</h3>
      {items.length === 0 ? (
        <p className="text-footnote text-fg-muted">{empty}</p>
      ) : (
        <ul className="flex flex-col gap-1">
          {items.map((item) => (
            <li key={item.name} className={cx('flex items-center gap-2 text-footnote', item.exists ? 'text-fg-muted' : 'text-fg')}>
              {item.exists ? <Check aria-hidden="true" className="size-4 text-success" /> : <span aria-hidden="true" className="size-4" />}
              {item.name}
              {item.exists && <span className="sr-only">({t('fmt_template_exists')})</span>}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
