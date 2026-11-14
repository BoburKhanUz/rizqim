'use client';

import { useState, type FormEvent } from 'react';
import Link from 'next/link';
import { Plus, Settings2, Trash2 } from 'lucide-react';
import { Button, Card, EmptyState, Field, FormError, IconButton, InlineError, Notice, PageHeader, Skeleton, fieldClass } from '@rizqim/ui';
import { useOwnerProducts } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { SelectField, TextField } from '../pos/fields';
import { parseKazans, type Ingredient, type IngredientUnit, type OpsSettings, type OpsSettingsInput } from './api';
import { useOpsSettings, useOshCalculation, useSaveOpsSettings } from './hooks';
import { OshCard } from './ProductionSheetView';

const newKey = () => (typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`);
const UNITS: IngredientUnit[] = ['kg', 'l', 'dona'];

interface Draft {
  reserve: string;
  personsPerKg: string;
  ingredients: Ingredient[];
  kazans: string;
  oshProducts: number[];
  weights: { product_id: number; grams: string }[];
  mxik: Record<'package' | 'hall' | 'extra', { mxik: string; package_code: string }>;
  template: string;
}

export function draftOf(settings: OpsSettings): Draft {
  const mxik = (part: 'package' | 'hall' | 'extra') => ({ mxik: settings.mxik[part].mxik ?? '', package_code: settings.mxik[part].package_code ?? '' });
  return {
    reserve: String(Number(settings.reserve_percent)),
    personsPerKg: String(Number(settings.osh.persons_per_kg)),
    ingredients: settings.osh.ingredients,
    kazans: settings.osh.kazans.join(', '),
    oshProducts: settings.osh.product_ids,
    weights: settings.portion_weights.map((weight) => ({ product_id: weight.product_id, grams: String(weight.grams) })),
    mxik: { package: mxik('package'), hall: mxik('hall'), extra: mxik('extra') },
    template: settings.contract_template,
  };
}

export function inputOf(draft: Draft, version: number): OpsSettingsInput {
  const blank = (value: string) => (value.trim() === '' ? null : value.trim());
  return {
    version,
    reserve_percent: draft.reserve.trim().replace(',', '.') || '0',
    osh: { persons_per_kg: draft.personsPerKg.trim().replace(',', '.'), product_ids: draft.oshProducts, ingredients: draft.ingredients.map((ingredient) => ({ ...ingredient, per_kg: ingredient.per_kg.replace(',', '.') })), kazans: parseKazans(draft.kazans) },
    portion_weights: draft.weights.filter((weight) => Number(weight.grams) > 0).map((weight) => ({ product_id: weight.product_id, grams: Math.round(Number(weight.grams)) })),
    package_mxik: blank(draft.mxik.package.mxik),
    package_package_code: blank(draft.mxik.package.package_code),
    hall_mxik: blank(draft.mxik.hall.mxik),
    hall_package_code: blank(draft.mxik.hall.package_code),
    extra_mxik: blank(draft.mxik.extra.mxik),
    extra_package_code: blank(draft.mxik.extra.package_code),
    contract_template: draft.template,
  };
}

// /banquets/ops-settings (P6-54): the production sheet's reserve, the osh calculator (persons per kg, ingredients per kg,
// kazans, the osh dishes) with a live calculation, portion weights, the final receipt's MXIK codes and the contract
// template with its placeholders (banquets.manage, every change audited).
export function OpsSettingsView() {
  const { t } = useI18n();
  const access = useCan();
  const settings = useOpsSettings();
  // Kept here: a save moves the version, and the form starts again from what the server keeps
  const [notice, setNotice] = useState<string | null>(null);

  if (access.ready && !access.can('banquets.manage')) {
    return (
      <>
        <PageHeader title={t('bqo_settingsTitle')} />
        <EmptyState icon={Settings2} title={t('mgmt_forbidden')} text={t('bqo_noAccess')} />
      </>
    );
  }

  return (
    <>
      <PageHeader
        title={t('bqo_settingsTitle')}
        subtitle={t('bqo_settingsSubtitle')}
        action={
          <Link href="/banquets/shopping" className="font-bold text-brand underline-offset-2 hover:underline">
            {t('bqo_navShopping')}
          </Link>
        }
      />
      {settings.isError ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(settings.error, t)} retryLabel={t('retry')} retrying={settings.isFetching} onRetry={() => void settings.refetch()} />
      ) : settings.data ? (
        <>
          <Notice message={notice} />
          <SettingsForm key={settings.data.version} settings={settings.data} onSaved={() => setNotice(t('bqo_saved'))} onEdit={() => setNotice(null)} />
        </>
      ) : (
        <Skeleton className="h-96 w-full" />
      )}
    </>
  );
}

function SettingsForm({ settings, onSaved, onEdit }: { settings: OpsSettings; onSaved: () => void; onEdit: () => void }) {
  const { t } = useI18n();
  const products = useOwnerProducts();
  const [draft, setDraft] = useState(() => draftOf(settings));
  const [key, setKey] = useState(newKey);
  const [persons, setPersons] = useState('200');
  const calculation = useOshCalculation(Number(persons) || 0, Number.isInteger(Number(persons)) && Number(persons) <= 5000);
  const save = useSaveOpsSettings({
    onSuccess: () => {
      setKey(newKey());
      onSaved();
    },
  });
  const patch = (changes: Partial<Draft>) => {
    onEdit();
    setDraft((current) => ({ ...current, ...changes }));
  };
  const productOptions = (products.data ?? []).map((product) => ({ value: String(product.id), label: product.name }));
  const productName = (id: number) => productOptions.find((option) => option.value === String(id))?.label ?? `#${id}`;
  const unused = (taken: number[]) => productOptions.filter((option) => !taken.includes(Number(option.value)));
  const [oshPick, setOshPick] = useState('');
  const [weightPick, setWeightPick] = useState('');

  const submit = (event: FormEvent) => {
    event.preventDefault();
    save.mutate({ input: inputOf(draft, settings.version), key });
  };

  return (
    <form className="flex flex-col gap-4" onSubmit={submit}>
      {save.isError && <FormError message={errorText(save.error, t)} />}

      <Card className="flex flex-col gap-3">
        <TextField label={t('bqo_reserve')} hint={t('bqo_reserveHint')} value={draft.reserve} onChange={(reserve) => patch({ reserve })} inputMode="decimal" maxLength={5} className="max-w-48" />
      </Card>

      <Card className="flex flex-col gap-3">
        <h2 className="text-headline font-extrabold text-fg">{t('bqo_osh')}</h2>
        <div className="flex flex-wrap gap-3">
          <TextField label={t('bqo_personsPerKg')} hint={t('bqo_personsPerKgHint')} value={draft.personsPerKg} onChange={(personsPerKg) => patch({ personsPerKg })} inputMode="decimal" maxLength={4} className="max-w-48" />
          <TextField label={t('bqo_kazanSizes')} hint={t('bqo_kazanSizesHint')} value={draft.kazans} onChange={(kazans) => patch({ kazans })} maxLength={60} className="max-w-72" />
        </div>
        <span className="text-footnote font-bold text-fg-muted">{t('bqo_ingredients')}</span>
        {draft.ingredients.map((ingredient, index) => (
          <div key={`${ingredient.key}-${index}`} className="flex flex-wrap items-end gap-2">
            <TextField label={t('bqo_ingredientName')} value={ingredient.name} onChange={(name) => patch({ ingredients: draft.ingredients.map((item, i) => (i === index ? { ...item, name } : item)) })} maxLength={60} />
            <SelectField label={t('bqo_ingredientUnit')} value={ingredient.unit} onChange={(unit) => patch({ ingredients: draft.ingredients.map((item, i) => (i === index ? { ...item, unit } : item)) })} options={UNITS.map((unit) => ({ value: unit, label: unit }))} />
            <TextField label={t('bqo_ingredientPerKg')} value={ingredient.per_kg} onChange={(perKg) => patch({ ingredients: draft.ingredients.map((item, i) => (i === index ? { ...item, per_kg: perKg } : item)) })} inputMode="decimal" maxLength={7} className="max-w-36" />
            <IconButton icon={Trash2} label={t('bqo_remove')} onClick={() => patch({ ingredients: draft.ingredients.filter((_, i) => i !== index) })} />
          </div>
        ))}
        <div>
          <Button variant="secondary" icon={Plus} onClick={() => patch({ ingredients: [...draft.ingredients, { key: `item${draft.ingredients.length + 1}`, name: '', unit: 'kg', per_kg: '0' }] })}>
            {t('bqo_addIngredient')}
          </Button>
        </div>

        <span className="text-footnote font-bold text-fg-muted">{t('bqo_oshProducts')}</span>
        <span className="text-footnote text-fg-muted">{t('bqo_oshProductsHint')}</span>
        <ul className="flex flex-wrap gap-2">
          {draft.oshProducts.map((id) => (
            <li key={id} className="flex items-center gap-1 rounded-control border border-line px-2 py-1 text-footnote">
              {productName(id)}
              <IconButton icon={Trash2} label={t('bqo_remove')} onClick={() => patch({ oshProducts: draft.oshProducts.filter((other) => other !== id) })} />
            </li>
          ))}
        </ul>
        {unused(draft.oshProducts).length > 0 && (
          <div className="flex flex-wrap items-end gap-2">
            <SelectField label={t('bqo_colDish')} value={oshPick} onChange={setOshPick} options={[{ value: '', label: '—' }, ...unused(draft.oshProducts)]} />
            <Button
              variant="secondary"
              icon={Plus}
              disabled={oshPick === ''}
              onClick={() => {
                patch({ oshProducts: [...draft.oshProducts, Number(oshPick)] });
                setOshPick('');
              }}
            >
              {t('bqo_addWeight')}
            </Button>
          </div>
        )}
      </Card>

      <Card className="flex flex-col gap-3">
        <h2 className="text-headline font-extrabold text-fg">{t('bqo_calculator')}</h2>
        <TextField label={t('bqo_calculatorPersons')} value={persons} onChange={setPersons} inputMode="numeric" maxLength={4} className="max-w-48" />
        {calculation.data && <OshCard osh={calculation.data} />}
      </Card>

      <Card className="flex flex-col gap-3">
        <h2 className="text-headline font-extrabold text-fg">{t('bqo_portionWeights')}</h2>
        <span className="text-footnote text-fg-muted">{t('bqo_portionWeightsHint')}</span>
        {draft.weights.map((weight, index) => (
          <div key={weight.product_id} className="flex flex-wrap items-end gap-2">
            <span className="min-w-48 pb-2 text-body text-fg">{productName(weight.product_id)}</span>
            <TextField label={t('bqo_grams')} value={weight.grams} onChange={(grams) => patch({ weights: draft.weights.map((item, i) => (i === index ? { ...item, grams } : item)) })} inputMode="numeric" maxLength={6} className="max-w-36" />
            <IconButton icon={Trash2} label={t('bqo_remove')} onClick={() => patch({ weights: draft.weights.filter((_, i) => i !== index) })} />
          </div>
        ))}
        {unused(draft.weights.map((weight) => weight.product_id)).length > 0 && (
          <div className="flex flex-wrap items-end gap-2">
            <SelectField label={t('bqo_colDish')} value={weightPick} onChange={setWeightPick} options={[{ value: '', label: '—' }, ...unused(draft.weights.map((weight) => weight.product_id))]} />
            <Button
              variant="secondary"
              icon={Plus}
              disabled={weightPick === ''}
              onClick={() => {
                patch({ weights: [...draft.weights, { product_id: Number(weightPick), grams: '300' }] });
                setWeightPick('');
              }}
            >
              {t('bqo_addWeight')}
            </Button>
          </div>
        )}
      </Card>

      <Card className="flex flex-col gap-3">
        <h2 className="text-headline font-extrabold text-fg">{t('bqo_mxik')}</h2>
        <span className="text-footnote text-fg-muted">{t('bqo_mxikHint')}</span>
        {(['package', 'hall', 'extra'] as const).map((part) => (
          <div key={part} className="flex flex-wrap gap-3">
            <TextField
              label={t(part === 'package' ? 'bqo_mxikPackage' : part === 'hall' ? 'bqo_mxikHall' : 'bqo_mxikExtra')}
              value={draft.mxik[part].mxik}
              onChange={(mxik) => patch({ mxik: { ...draft.mxik, [part]: { ...draft.mxik[part], mxik } } })}
              inputMode="numeric"
              maxLength={17}
            />
            <TextField label={t('bqo_packageCode')} value={draft.mxik[part].package_code} onChange={(code) => patch({ mxik: { ...draft.mxik, [part]: { ...draft.mxik[part], package_code: code } } })} inputMode="numeric" maxLength={32} />
          </div>
        ))}
      </Card>

      <Card className="flex flex-col gap-3">
        <h2 className="text-headline font-extrabold text-fg">{t('bqo_contractTemplate')}</h2>
        <Field label={t('bqo_contractTemplate')} hint={t('bqo_contractTemplateHint')}>
          {(props) => <textarea {...props} rows={18} maxLength={20000} value={draft.template} onChange={(event) => patch({ template: event.target.value })} className={`${fieldClass} font-mono text-footnote`} />}
        </Field>
        <span className="text-footnote font-bold text-fg-muted">{t('bqo_placeholders')}</span>
        <div className="flex flex-wrap gap-1">
          {settings.contract_placeholders.map((name) => (
            <button key={name} type="button" className="rounded-control border border-line px-2 py-0.5 font-mono text-footnote text-fg hover:bg-line" onClick={() => patch({ template: `${draft.template}{{${name}}}` })}>
              {`{{${name}}}`}
            </button>
          ))}
        </div>
        <div>
          <Button variant="secondary" onClick={() => patch({ template: '' })}>
            {t('bqo_resetTemplate')}
          </Button>
        </div>
      </Card>

      <div>
        <Button type="submit" loading={save.isPending}>
          {t('bqo_save')}
        </Button>
      </div>
    </form>
  );
}
