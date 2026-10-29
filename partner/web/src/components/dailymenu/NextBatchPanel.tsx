'use client';

import { useState } from 'react';
import { Clock, Plus, X } from 'lucide-react';
import { Button, Card, Dialog, Field, fieldClass, InlineError } from '@rizqim/ui';
import { formatQuantityNumber, useOwnerProducts } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { ErrorBanner } from '../menu/ErrorBanner';
import { parseDecimal } from '../menu/numbers';
import { describeError } from '../menu/serverError';
import { TimeField } from '../pos/fields';
import type { BatchPlans } from './api';
import { useCancelBatchPlan, useCreateBatchPlan } from './hooks';

// "Keyingi partiya HH:MM": when the next kazan or tandir is planned. The till and the Mini App show it beside "Tugadi".
export function NextBatchPanel({ branchId, plans, canManage }: { branchId: number; plans: BatchPlans; canManage: boolean }) {
  const { t } = useI18n();
  const [adding, setAdding] = useState(false);
  const cancel = useCancelBatchPlan();

  return (
    <section aria-label={t('dm_nextTitle')} className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="text-headline font-bold text-fg">{t('dm_nextTitle')}</h2>
          <p className="text-footnote text-fg-muted">{t('dm_nextText')}</p>
        </div>
        {canManage && (
          <Button variant="secondary" icon={Plus} onClick={() => setAdding(true)}>
            {t('dm_nextAdd')}
          </Button>
        )}
      </div>

      {plans.plans.length === 0 ? (
        <p className="text-footnote text-fg-muted">{t('dm_nextEmpty')}</p>
      ) : (
        <ul className="grid gap-2 sm:grid-cols-2">
          {plans.plans.map((plan) => (
            <li key={plan.id}>
              <Card className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                  <p className="flex items-center gap-2 text-body font-bold text-fg">
                    <Clock aria-hidden="true" className="size-4 shrink-0 text-fg-muted" />
                    {plan.time}
                    <span className="truncate font-medium">
                      {plan.product_name}
                      {plan.variant_name ? ` · ${plan.variant_name}` : ''}
                    </span>
                  </p>
                  <p className="text-footnote text-fg-muted">
                    {[plan.quantity !== null ? t('dm_nextQty', { n: formatQuantityNumber(plan.quantity, 'uz') }) : null, plan.label, plan.overdue ? t('dm_nextOverdue') : null].filter(Boolean).join(' · ')}
                  </p>
                </div>
                {canManage && (
                  <Button variant="ghost" icon={X} loading={cancel.isPending && cancel.variables === plan.id} onClick={() => cancel.mutate(plan.id)}>
                    <span className="sr-only">{t('dm_nextCancel')}</span>
                  </Button>
                )}
              </Card>
            </li>
          ))}
        </ul>
      )}
      {cancel.error && <ErrorBanner message={describeError(cancel.error, t).message} />}
      {adding && <PlanDialog branchId={branchId} onClose={() => setAdding(false)} />}
    </section>
  );
}

function PlanDialog({ branchId, onClose }: { branchId: number; onClose: () => void }) {
  const { t } = useI18n();
  const products = useOwnerProducts();
  const create = useCreateBatchPlan({ onSuccess: onClose });
  const [productId, setProductId] = useState('');
  const [variantId, setVariantId] = useState('');
  const [time, setTime] = useState('');
  const [quantity, setQuantity] = useState('');
  const [label, setLabel] = useState('');
  const [problem, setProblem] = useState<Record<string, string>>({});
  const failure = create.error ? describeError(create.error, t) : null;
  const fieldError = (name: string) => problem[name] ?? failure?.fields[name];

  const sorted = [...(products.data ?? [])].filter((product) => product.status === 'active').sort((a, b) => Number(b.batch_tracked) - Number(a.batch_tracked));
  const variants = sorted.find((product) => String(product.id) === productId)?.variants ?? [];

  const submit = () => {
    const found: Record<string, string> = {};
    const amount = quantity.trim() === '' ? null : parseDecimal(quantity);
    if (productId === '') found.product_id = t('menu_err_required');
    if (time === '') found.time = t('menu_err_required');
    if (quantity.trim() !== '' && (amount === null || amount <= 0)) found.quantity = t('menu_err_number');
    setProblem(found);
    if (Object.keys(found).length > 0) return;
    create.mutate({ branchId, productId: Number(productId), variantId: variantId === '' ? null : Number(variantId), time, quantity: amount, label: label.trim() === '' ? null : label.trim() });
  };

  return (
    <Dialog
      open
      onClose={onClose}
      title={t('dm_nextAdd')}
      description={t('dm_nextDialogText')}
      busy={create.isPending}
      closeLabel={t('menu_close')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={create.isPending}>
            {t('menu_cancel')}
          </Button>
          <Button loading={create.isPending} onClick={submit}>
            {t('menu_save')}
          </Button>
        </>
      }
    >
      {products.isError && <InlineError title={t('loadErrorTitle')} message={describeError(products.error, t).message} retryLabel={t('retry')} onRetry={() => void products.refetch()} />}
      <Field label={t('menu_batch_product')} error={fieldError('product_id')}>
        {(control) => (
          <select
            {...control}
            className={fieldClass}
            value={productId}
            disabled={products.isPending}
            onChange={(event) => {
              setProductId(event.target.value);
              setVariantId('');
            }}
          >
            <option value="">{t('menu_batch_choose_product')}</option>
            {sorted.map((product) => (
              <option key={product.id} value={product.id}>
                {product.name}
                {product.batch_tracked ? ` (${t('menu_batch_badge')})` : ''}
              </option>
            ))}
          </select>
        )}
      </Field>
      {variants.length > 1 && (
        <Field label={t('menu_batch_variant')} hint={t('menu_batch_variant_hint')} error={fieldError('product_variant_id')}>
          {(control) => (
            <select {...control} className={fieldClass} value={variantId} onChange={(event) => setVariantId(event.target.value)}>
              <option value="">{t('menu_batch_all_variants')}</option>
              {variants.map((variant) => (
                <option key={variant.id} value={variant.id}>
                  {variant.name}
                </option>
              ))}
            </select>
          )}
        </Field>
      )}
      <TimeField label={t('dm_nextTime')} hint={t('dm_nextTimeHint')} value={time} onChange={setTime} error={fieldError('time')} />
      <Field label={t('dm_nextQuantity')} error={fieldError('quantity')}>
        {(control) => <input {...control} className={fieldClass} inputMode="decimal" value={quantity} onChange={(event) => setQuantity(event.target.value)} />}
      </Field>
      <Field label={t('menu_batch_label')} error={fieldError('label')}>
        {(control) => <input {...control} className={fieldClass} maxLength={100} value={label} onChange={(event) => setLabel(event.target.value)} />}
      </Field>
      {failure && <ErrorBanner message={failure.message} />}
    </Dialog>
  );
}
