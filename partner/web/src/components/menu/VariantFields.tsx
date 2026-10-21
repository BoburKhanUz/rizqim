'use client';

import { Plus, Trash2 } from 'lucide-react';
import { Button, Card, Field, fieldClass, IconButton } from '@rizqim/ui';
import type { MenuUnit } from '@rizqim/staff';
import { useT } from '../I18nProvider';
import { toInputText } from './numbers';

// One variant of a product as the form holds it: every number is the text of its input
export interface VariantDraft {
  key: string;
  id?: number;
  name: string;
  price: string;
  unit: MenuUnit;
  step: string;
  min: string;
  consumption: string;
  // What the API says is left (read only: the product form does not set it)
  stock?: number | string | null;
}

let counter = 0;
export const newVariantKey = () => `variant-${++counter}`;

export const emptyVariant = (name = ''): VariantDraft => ({ key: newVariantKey(), name, price: '', unit: 'piece', step: '', min: '', consumption: '' });

export function variantFromApi(variant: {
  id: number;
  name: string | null;
  price: string;
  unit: MenuUnit;
  quantity_step: string | null;
  min_quantity: string | null;
  batch_consumption: string;
  stock: number;
}): VariantDraft {
  return {
    key: newVariantKey(),
    id: variant.id,
    name: variant.name ?? '',
    price: toInputText(variant.price),
    unit: variant.unit,
    step: toInputText(variant.quantity_step),
    min: toInputText(variant.min_quantity),
    // 1 is the default of the API: shown empty, so only a changed consumption is sent
    consumption: Number(variant.batch_consumption) === 1 ? '' : toInputText(variant.batch_consumption),
    stock: variant.stock,
  };
}

const UNITS: ReadonlyArray<MenuUnit> = ['piece', 'kg', 'l'];

interface VariantFieldsProps {
  variants: VariantDraft[];
  onChange: (variants: VariantDraft[]) => void;
  // true: the product is sold by batch ("qozon"), so a variant says how much of a batch one unit takes
  batchTracked: boolean;
  // The messages of the server and of the light checks, keyed "variants.0.price"
  errors: Record<string, string>;
}

// The variants of a product: name, price (per piece, kg or litre), unit, the step and minimum of a weighed one, the
// share of a batch one unit takes. Whether the numbers are valid is the server's to say; its messages show under the field.
export function VariantFields({ variants, onChange, batchTracked, errors }: VariantFieldsProps) {
  const t = useT();
  const update = (index: number, patch: Partial<VariantDraft>) => onChange(variants.map((variant, i) => (i === index ? { ...variant, ...patch } : variant)));

  return (
    <div className="flex flex-col gap-3">
      {variants.map((variant, index) => {
        const measured = variant.unit !== 'piece';
        const error = (field: string) => errors[`variants.${index}.${field}`];
        const unitName = variant.unit === 'kg' ? t('menu_unit_kg') : t('menu_unit_l');
        return (
          <Card key={variant.key} role="group" aria-label={t('menu_variant_n', { n: index + 1 })} className="flex flex-col gap-3 bg-surface-2/40">
            <div className="flex items-center justify-between gap-2">
              <h3 className="text-body font-bold text-fg">{t('menu_variant_n', { n: index + 1 })}</h3>
              <IconButton icon={Trash2} label={`${t('menu_remove_variant')}: ${index + 1}`} onClick={() => onChange(variants.filter((_, i) => i !== index))} disabled={variants.length <= 1} />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label={t('menu_variant_name')} error={error('name')}>
                {(control) => <input {...control} className={fieldClass} value={variant.name} maxLength={255} onChange={(event) => update(index, { name: event.target.value })} />}
              </Field>
              <Field label={measured ? t('menu_price_per', { unit: unitName }) : t('menu_price')} error={error('price')}>
                {(control) => <input {...control} className={fieldClass} inputMode="decimal" value={variant.price} onChange={(event) => update(index, { price: event.target.value })} />}
              </Field>
              <Field label={t('menu_unit')} error={error('unit')}>
                {(control) => (
                  <select {...control} className={fieldClass} value={variant.unit} onChange={(event) => update(index, { unit: event.target.value as MenuUnit })}>
                    {UNITS.map((unit) => (
                      <option key={unit} value={unit}>
                        {unit === 'piece' ? t('menu_unit_piece') : unit === 'kg' ? t('menu_unit_kg') : t('menu_unit_l')}
                      </option>
                    ))}
                  </select>
                )}
              </Field>
              {variant.id !== undefined && variant.stock !== undefined && variant.stock !== null && (
                <div className="flex flex-col gap-1.5">
                  <span className="text-footnote font-bold text-fg-muted">{t('menu_stock')}</span>
                  <p className="flex min-h-11 items-center text-body text-fg">{toInputText(variant.stock)}</p>
                  <p className="text-footnote text-fg-muted">{t('menu_stock_hint')}</p>
                </div>
              )}
              {measured && (
                <>
                  <Field label={t('menu_step', { unit: unitName })} hint={t('menu_step_hint')} error={error('quantity_step')}>
                    {(control) => <input {...control} className={fieldClass} inputMode="decimal" value={variant.step} onChange={(event) => update(index, { step: event.target.value })} />}
                  </Field>
                  <Field label={t('menu_min', { unit: unitName })} hint={t('menu_min_hint')} error={error('min_quantity')}>
                    {(control) => <input {...control} className={fieldClass} inputMode="decimal" value={variant.min} onChange={(event) => update(index, { min: event.target.value })} />}
                  </Field>
                </>
              )}
              {batchTracked && (
                <Field label={t('menu_consumption')} hint={t('menu_consumption_hint')} error={error('menu_batch_consumption')}>
                  {(control) => <input {...control} className={fieldClass} inputMode="decimal" value={variant.consumption} onChange={(event) => update(index, { consumption: event.target.value })} />}
                </Field>
              )}
            </div>
          </Card>
        );
      })}
      {errors.variants && (
        <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
          {errors.variants}
        </p>
      )}
      <div>
        <Button variant="secondary" icon={Plus} onClick={() => onChange([...variants, emptyVariant()])}>
          {t('menu_add_variant')}
        </Button>
      </div>
    </div>
  );
}
