'use client';

import { Card, Field, fieldClass } from '@rizqim/ui';
import type { ProductKind } from '@rizqim/staff';
import { useT } from '../I18nProvider';
import type { FiscalDraft } from './productFiscal';

// The fiscal part of the product form: what the item is on a receipt, its MXIK and package code, its own VAT rate.
export function ProductFiscalFields({ draft, onChange, errors }: { draft: FiscalDraft; onChange: (changes: Partial<FiscalDraft>) => void; errors: Record<string, string> }) {
  const t = useT();
  return (
    <Card className="flex flex-col gap-4" aria-label={t('fiscal_product_section')}>
      <div>
        <h2 className="text-headline font-bold text-fg">{t('fiscal_product_section')}</h2>
        <p className="text-footnote text-fg-muted">{t('fiscal_product_hint')}</p>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={t('fiscal_product_kind')} error={errors.kind}>
          {(control) => (
            <select {...control} className={fieldClass} value={draft.kind} onChange={(event) => onChange({ kind: event.target.value as ProductKind })}>
              <option value="dish">{t('fiscal_kind_dish')}</option>
              <option value="service">{t('fiscal_kind_service')}</option>
            </select>
          )}
        </Field>
        <Field label={t('fiscal_product_mxik')} hint={t('fiscal_product_mxikHint')} error={errors.mxik_code}>
          {(control) => <input {...control} className={fieldClass} inputMode="numeric" maxLength={17} value={draft.mxik} onChange={(event) => onChange({ mxik: event.target.value })} />}
        </Field>
        <Field label={t('fiscal_product_package')} hint={t('fiscal_product_packageHint')} error={errors.package_code}>
          {(control) => <input {...control} className={fieldClass} inputMode="numeric" maxLength={20} value={draft.packageCode} onChange={(event) => onChange({ packageCode: event.target.value })} />}
        </Field>
        <Field label={t('fiscal_product_vat')} hint={t('fiscal_product_vatHint')} error={errors.vat_rate}>
          {(control) => <input {...control} className={fieldClass} inputMode="decimal" value={draft.vat} onChange={(event) => onChange({ vat: event.target.value })} />}
        </Field>
      </div>
    </Card>
  );
}
