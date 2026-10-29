'use client';

import { useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { Button, Card, CardSkeletons, ConfirmDialog, Dialog, EmptyState, Field, fieldClass, formatMoney, InlineError, Switch } from '@rizqim/ui';
import { formatQuantityNumber, useOwnerProducts, type Product } from '@rizqim/staff';
import { Salad } from 'lucide-react';
import { useI18n } from '../I18nProvider';
import { useMenuAccess } from '../menu/access';
import { ErrorBanner } from '../menu/ErrorBanner';
import { parseDecimal } from '../menu/numbers';
import { describeError } from '../menu/serverError';
import type { MealSet } from './api';
import { useDeleteMealSet, useMealSets, useSaveMealSet } from './hooks';

// Kompleks: a menu product sold at its own price as one line; here the dishes the kitchen prepares for it.
export function MealSetsPanel() {
  const { t } = useI18n();
  const access = useMenuAccess();
  const canEdit = access.can('menu.manage');
  const query = useMealSets();
  const [editing, setEditing] = useState<MealSet | 'new' | null>(null);
  const [deleting, setDeleting] = useState<MealSet | null>(null);
  const toggle = useSaveMealSet();
  const remove = useDeleteMealSet({ onSuccess: () => setDeleting(null) });

  if (query.isPending) return <CardSkeletons count={2} lines={3} label={t('loading')} />;
  if (query.isError) return <InlineError title={t('loadErrorTitle')} message={describeError(query.error, t).message} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;

  return (
    <div className="flex flex-col gap-4">
      <p className="text-footnote text-fg-muted">{t('dm_setsIntro')}</p>
      {canEdit && (
        <div>
          <Button icon={Plus} onClick={() => setEditing('new')}>
            {t('dm_setAdd')}
          </Button>
        </div>
      )}
      {toggle.error && <ErrorBanner message={describeError(toggle.error, t).message} />}

      {query.data.length === 0 ? (
        <EmptyState icon={Salad} title={t('dm_setsEmptyTitle')} text={t('dm_setsEmptyText')} />
      ) : (
        <ul className="grid gap-3 lg:grid-cols-2">
          {query.data.map((set) => (
            <li key={set.id}>
              <Card className="flex flex-col gap-3">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="text-body font-bold text-fg">{set.product_name}</p>
                    <p className="text-footnote text-fg-muted">{set.prices.map((price) => formatMoney(price, 'uz')).join(' · ')}</p>
                  </div>
                  <div className="flex items-center gap-2">
                    <Switch checked={set.is_active} disabled={!canEdit || toggle.isPending} onChange={(isActive) => toggle.mutate({ id: set.id, isActive })} label={t('dm_setActive')} />
                    <span className="text-footnote text-fg">{set.is_active ? t('dm_setActive') : t('dm_setOff')}</span>
                  </div>
                </div>
                <ul className="flex flex-col gap-1">
                  {set.parts.map((part) => (
                    <li key={part.variant_id} className="flex items-baseline justify-between gap-3 border-b border-line py-1 text-body last:border-b-0">
                      <span className="min-w-0 text-fg">
                        {part.label && <span className="mr-2 text-caption font-bold text-fg-muted">{part.label}</span>}
                        {part.name}
                        {part.variant_name ? ` · ${part.variant_name}` : ''}
                      </span>
                      <span className="shrink-0 font-bold text-fg">× {formatQuantityNumber(Number(part.quantity), 'uz')}</span>
                    </li>
                  ))}
                </ul>
                {canEdit && (
                  <div className="flex flex-wrap gap-2">
                    <Button variant="secondary" icon={Pencil} onClick={() => setEditing(set)}>
                      {t('dm_setEdit')}
                      <span className="sr-only"> {set.product_name}</span>
                    </Button>
                    <Button variant="ghost" icon={Trash2} onClick={() => setDeleting(set)}>
                      {t('dm_setDelete')}
                      <span className="sr-only"> {set.product_name}</span>
                    </Button>
                  </div>
                )}
              </Card>
            </li>
          ))}
        </ul>
      )}

      {editing !== null && <SetDialog set={editing === 'new' ? null : editing} sets={query.data} onClose={() => setEditing(null)} />}
      {deleting && (
        <ConfirmDialog
          title={t('dm_setDeleteTitle')}
          text={t('dm_setDeleteText', { name: deleting.product_name ?? '' })}
          confirmLabel={t('dm_setDelete')}
          cancelLabel={t('menu_cancel')}
          closeLabel={t('menu_close')}
          busy={remove.isPending}
          error={remove.error ? describeError(remove.error, t).message : null}
          onConfirm={() => remove.mutate(deleting.id)}
          onClose={() => setDeleting(null)}
        />
      )}
    </div>
  );
}

interface PartDraft {
  variantId: string;
  quantity: string;
  label: string;
}

const MAX_PARTS = 12;

function SetDialog({ set, sets, onClose }: { set: MealSet | null; sets: MealSet[]; onClose: () => void }) {
  const { t } = useI18n();
  const products = useOwnerProducts();
  const save = useSaveMealSet({ onSuccess: onClose });
  const [productId, setProductId] = useState(set ? String(set.product_id) : '');
  const [parts, setParts] = useState<PartDraft[]>(() =>
    set ? set.parts.map((part) => ({ variantId: String(part.variant_id), quantity: String(Number(part.quantity)).replace('.', ','), label: part.label ?? '' })) : [{ variantId: '', quantity: '1', label: '' }],
  );
  const [problem, setProblem] = useState<string | null>(null);
  const failure = save.error ? describeError(save.error, t) : null;

  const setProducts = new Set(sets.map((existing) => existing.product_id));
  const all: Product[] = products.data ?? [];
  const active = all.filter((product) => product.status === 'active');
  // A kompleks is a product that is not one yet; its parts are dishes that are not complexes
  const candidates = set ? [] : active.filter((product) => !setProducts.has(product.id));
  const dishes = active.filter((product) => !setProducts.has(product.id) && String(product.id) !== productId);
  const options = dishes.flatMap((product) =>
    (product.variants ?? []).filter((variant) => variant.is_active !== false).map((variant) => ({ id: variant.id, label: variant.name && variant.name !== product.name ? `${product.name} · ${variant.name}` : product.name })),
  );

  const update = (index: number, patch: Partial<PartDraft>) => setParts((current) => current.map((part, position) => (position === index ? { ...part, ...patch } : part)));

  const submit = () => {
    const body = parts.map((part) => ({ variant_id: Number(part.variantId), quantity: parseDecimal(part.quantity), label: part.label.trim() === '' ? null : part.label.trim() }));
    if (!set && productId === '') return setProblem(t('menu_err_required'));
    if (body.some((part) => !part.variant_id || part.quantity === null || part.quantity <= 0)) return setProblem(t('dm_setPartsInvalid'));
    setProblem(null);
    save.mutate({ id: set?.id ?? null, productId: Number(productId), parts: body.map((part) => ({ ...part, quantity: part.quantity as number })) });
  };

  return (
    <Dialog
      open
      onClose={onClose}
      title={set ? t('dm_setEditTitle', { name: set.product_name ?? '' }) : t('dm_setAdd')}
      description={t('dm_setDialogText')}
      busy={save.isPending}
      closeLabel={t('menu_close')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            {t('menu_cancel')}
          </Button>
          <Button loading={save.isPending} onClick={submit}>
            {t('menu_save')}
          </Button>
        </>
      }
    >
      {!set && (
        <Field label={t('dm_setProduct')} hint={t('dm_setProductHint')} error={failure?.fields.product_id}>
          {(control) => (
            <select {...control} className={fieldClass} value={productId} disabled={products.isPending} onChange={(event) => setProductId(event.target.value)}>
              <option value="">{t('dm_setChoose')}</option>
              {candidates.map((product) => (
                <option key={product.id} value={product.id}>
                  {product.name}
                </option>
              ))}
            </select>
          )}
        </Field>
      )}

      <fieldset className="flex flex-col gap-3">
        <legend className="text-body font-bold text-fg">{t('dm_setParts')}</legend>
        {parts.map((part, index) => (
          <div key={index} className="grid gap-2 rounded-card border border-line p-3 sm:grid-cols-[1fr_6rem_8rem_auto]">
            <Field label={t('dm_setPartDish')} error={failure?.fields[`parts.${index}.variant_id`]}>
              {(control) => (
                <select {...control} className={fieldClass} value={part.variantId} onChange={(event) => update(index, { variantId: event.target.value })}>
                  <option value="">{t('dm_setChoose')}</option>
                  {options.map((option) => (
                    <option key={option.id} value={option.id}>
                      {option.label}
                    </option>
                  ))}
                </select>
              )}
            </Field>
            <Field label={t('dm_setPartQty')} error={failure?.fields[`parts.${index}.quantity`]}>
              {(control) => <input {...control} className={fieldClass} inputMode="decimal" value={part.quantity} onChange={(event) => update(index, { quantity: event.target.value })} />}
            </Field>
            <Field label={t('dm_setPartLabel')}>
              {(control) => <input {...control} className={fieldClass} maxLength={60} value={part.label} placeholder={t('dm_setPartLabelHint')} onChange={(event) => update(index, { label: event.target.value })} />}
            </Field>
            <div className="flex items-end">
              <Button variant="ghost" icon={Trash2} disabled={parts.length === 1} onClick={() => setParts((current) => current.filter((_, position) => position !== index))}>
                <span className="sr-only">{t('dm_setPartRemove')}</span>
              </Button>
            </div>
          </div>
        ))}
        <div>
          <Button variant="secondary" icon={Plus} disabled={parts.length >= MAX_PARTS} onClick={() => setParts((current) => [...current, { variantId: '', quantity: '1', label: '' }])}>
            {t('dm_setPartAdd')}
          </Button>
        </div>
      </fieldset>

      {problem && <ErrorBanner message={problem} />}
      {failure && <ErrorBanner message={failure.message} />}
    </Dialog>
  );
}
