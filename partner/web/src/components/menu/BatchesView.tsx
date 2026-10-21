'use client';

import { useMemo, useState } from 'react';
import { CookingPot, Minus, Plus, Power } from 'lucide-react';
import { Button, Card, CardSkeletons, Dialog, EmptyState, Field, fieldClass, formatDateTime, InlineError } from '@rizqim/ui';
import {
  formatQuantity,
  type MenuUnit,
  useAdjustBatch,
  useBatches,
  useBranchesBatches,
  useCloseBatch,
  useOpenBatch,
  useOwnerProducts,
  type Batch,
} from '@rizqim/staff';
import { cx } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { PageHeader } from '../PageHeader';
import { useMenuAccess } from './access';
import { BranchSelect, useBranchChoice } from './BranchChoice';
import { ErrorBanner } from './ErrorBanner';
import { parseDecimal } from './numbers';
import { describeError } from './serverError';
import { StockTabs } from './StockTabs';
import { Switch } from './Switch';

// The batches are refetched every 5 seconds (docs/MENU.md, "Qozon qoldig'i")
const REFRESH_MS = 5000;

// The batch's unit as the API sends it (piece, kg, l, or nothing for a batch of pieces)
const asUnit = (unit: string | null): MenuUnit | null => (unit === 'kg' || unit === 'l' || unit === 'piece' ? unit : null);

// Qozon: open a batch of a dish (a pot of plov), top it up, correct what is left, close it. What is left comes from
// the backend, which takes it down order by order.
export function BatchesView() {
  const { t } = useI18n();
  const access = useMenuAccess();
  const choice = useBranchChoice();
  const canManage = access.can('batches.manage');

  return (
    <>
      <PageHeader title={t('menu_batch_title')} subtitle={t('menu_batch_subtitle')} />
      <StockTabs current="batches" />
      {choice.isPending ? (
        <CardSkeletons count={3} lines={2} label={t('loading')} />
      ) : choice.isError ? (
        <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={choice.isFetching} onRetry={choice.refetch} />
      ) : choice.branchId === null ? (
        <EmptyState icon={CookingPot} title={t('menu_no_branches')} />
      ) : (
        <div className="flex flex-col gap-6">
          {choice.branches.length > 1 && <BranchOverview branches={choice.branches} current={choice.branchId} onChoose={choice.choose} />}
          <BatchBranch key={choice.branchId} branches={choice.branches} branchId={choice.branchId} onChoose={choice.choose} canManage={canManage} />
        </div>
      )}
    </>
  );
}

// Every branch with how many pots are open in it: a tap opens that branch
function BranchOverview({ branches, current, onChoose }: { branches: Array<{ id: number; name: string }>; current: number; onChoose: (id: number) => void }) {
  const { t } = useI18n();
  const all = useBranchesBatches(
    branches.map((branch) => branch.id),
    { refetchInterval: REFRESH_MS },
  );
  return (
    <section aria-label={t('menu_batch_overview')}>
      <ul className="flex flex-wrap gap-2">
        {branches.map((branch, index) => {
          const entry = all[index];
          const open = entry?.batches.filter((batch) => batch.is_open) ?? [];
          const soldOut = open.filter((batch) => Number(batch.quantity_left) <= 0).length;
          return (
            <li key={branch.id}>
              <button
                type="button"
                aria-pressed={branch.id === current}
                onClick={() => onChoose(branch.id)}
                className={cx(
                  'flex min-h-11 min-w-40 flex-col items-start rounded-card border px-3 py-2 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                  branch.id === current ? 'border-brand bg-brand-soft text-brand-soft-fg' : 'border-line bg-surface text-fg',
                )}
              >
                <span className="text-body font-bold">{branch.name}</span>
                <span className="text-footnote">
                  {entry?.isPending ? '…' : entry?.isError ? t('menu_batch_overview_error') : t('menu_batch_open_count', { n: open.length })}
                  {soldOut > 0 && ` · ${t('menu_batch_sold_out_count', { n: soldOut })}`}
                </span>
              </button>
            </li>
          );
        })}
      </ul>
    </section>
  );
}

type Action = { kind: 'add' | 'set'; batch: Batch } | { kind: 'close'; batch: Batch } | { kind: 'open' };

function BatchBranch({ branches, branchId, onChoose, canManage }: { branches: Array<{ id: number; name: string }>; branchId: number; onChoose: (id: number) => void; canManage: boolean }) {
  const { t, language } = useI18n();
  const [includeClosed, setIncludeClosed] = useState(false);
  const query = useBatches({ branchId, includeClosed, refetchInterval: REFRESH_MS });
  const [action, setAction] = useState<Action | null>(null);
  const done = () => setAction(null);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end gap-4">
        <BranchSelect branches={branches} value={branchId} onChange={onChoose} className="min-w-52 max-w-xs" />
        <div className="flex items-center gap-2">
          <Switch checked={includeClosed} onChange={setIncludeClosed} label={t('menu_batch_show_closed')} />
          <span className="text-body text-fg">{t('menu_batch_show_closed')}</span>
        </div>
        {canManage && (
          <Button icon={Plus} className="sm:ml-auto" onClick={() => setAction({ kind: 'open' })}>
            {t('menu_batch_open')}
          </Button>
        )}
      </div>

      {query.isPending ? (
        <CardSkeletons count={3} lines={3} label={t('loading')} />
      ) : query.isError ? (
        <InlineError title={t('loadErrorTitle')} message={describeError(query.error, t).message} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />
      ) : query.data.length === 0 ? (
        <EmptyState
          icon={CookingPot}
          title={t('menu_batch_empty_title')}
          text={t('menu_batch_empty_text')}
          action={canManage ? <Button onClick={() => setAction({ kind: 'open' })}>{t('menu_batch_open')}</Button> : undefined}
        />
      ) : (
        <ul className="grid gap-3 lg:grid-cols-2">
          {query.data.map((batch) => {
            const produced = Number(batch.quantity_produced ?? 0);
            const left = Number(batch.quantity_left ?? 0);
            const share = produced > 0 ? Math.max(0, Math.min(100, Math.round((left / produced) * 100))) : 0;
            const soldOut = batch.is_open && left <= 0;
            return (
              <li key={batch.id}>
                <Card className="flex flex-col gap-3">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="text-body font-bold text-fg">
                        {batch.product_name}
                        {batch.variant_name ? ` · ${batch.variant_name}` : ''}
                      </p>
                      <p className="text-footnote text-fg-muted">
                        {[batch.label, batch.started_at ? t('menu_batch_started', { time: formatDateTime(batch.started_at, language) }) : null, batch.closed_at ? t('menu_batch_closed_at', { time: formatDateTime(batch.closed_at, language) }) : null]
                          .filter(Boolean)
                          .join(' · ')}
                      </p>
                    </div>
                    <span
                      className={cx(
                        'rounded-control px-2 py-1 text-caption font-bold',
                        !batch.is_open ? 'bg-surface-2 text-fg-muted' : soldOut ? 'bg-danger-soft text-danger-soft-fg' : 'bg-success-soft text-success-soft-fg',
                      )}
                    >
                      {!batch.is_open ? t('menu_batch_status_closed') : soldOut ? t('menu_batch_status_sold_out') : t('menu_batch_status_open')}
                    </span>
                  </div>
                  <div>
                    <p className="text-display font-extrabold text-fg">
                      {formatQuantity(left, asUnit(batch.unit), language)}
                      <span className="text-body font-medium text-fg-muted"> {t('menu_batch_of', { total: formatQuantity(produced, asUnit(batch.unit), language) })}</span>
                    </p>
                    <div role="progressbar" aria-label={t('menu_batch_left')} aria-valuemin={0} aria-valuemax={100} aria-valuenow={share} className="mt-2 h-2 overflow-hidden rounded-full bg-surface-2">
                      <div className={cx('h-full rounded-full', soldOut ? 'bg-danger' : 'bg-brand')} style={{ width: `${share}%` }} />
                    </div>
                  </div>
                  {canManage && batch.is_open && (
                    <div className="flex flex-wrap gap-2">
                      <Button variant="secondary" icon={Plus} onClick={() => setAction({ kind: 'add', batch })}>
                        {t('menu_batch_add')}
                        <span className="sr-only"> {batch.product_name}</span>
                      </Button>
                      <Button variant="secondary" icon={Minus} onClick={() => setAction({ kind: 'set', batch })}>
                        {t('menu_batch_correct')}
                        <span className="sr-only"> {batch.product_name}</span>
                      </Button>
                      <Button variant="ghost" icon={Power} onClick={() => setAction({ kind: 'close', batch })}>
                        {t('menu_batch_close')}
                        <span className="sr-only"> {batch.product_name}</span>
                      </Button>
                    </div>
                  )}
                </Card>
              </li>
            );
          })}
        </ul>
      )}

      {action?.kind === 'open' && <OpenBatchDialog branchId={branchId} onClose={done} />}
      {(action?.kind === 'add' || action?.kind === 'set') && <AdjustDialog mode={action.kind} batch={action.batch} onClose={done} />}
      {action?.kind === 'close' && <CloseDialog batch={action.batch} onClose={done} />}
    </div>
  );
}

function OpenBatchDialog({ branchId, onClose }: { branchId: number; onClose: () => void }) {
  const { t } = useI18n();
  const products = useOwnerProducts();
  const open = useOpenBatch({ onSuccess: onClose });
  const [productId, setProductId] = useState('');
  const [variantId, setVariantId] = useState('');
  const [quantity, setQuantity] = useState('');
  const [label, setLabel] = useState('');
  const [problem, setProblem] = useState<Record<string, string>>({});

  // The dishes sold by batch first
  const sorted = useMemo(() => [...(products.data ?? [])].sort((a, b) => Number(b.batch_tracked) - Number(a.batch_tracked)), [products.data]);
  const product = sorted.find((item) => String(item.id) === productId);
  const variants = product?.variants ?? [];
  const failure = open.error ? describeError(open.error, t) : null;
  const fieldError = (name: string) => problem[name] ?? failure?.fields[name];

  const submit = () => {
    const amount = parseDecimal(quantity);
    const found: Record<string, string> = {};
    if (productId === '') found.product_id = t('menu_err_required');
    if (amount === null || amount <= 0) found.quantity = quantity.trim() === '' ? t('menu_err_required') : t('menu_err_number');
    setProblem(found);
    if (Object.keys(found).length > 0 || amount === null) return;
    open.mutate({ branch_id: branchId, product_id: Number(productId), product_variant_id: variantId === '' ? null : Number(variantId), quantity: amount, label: label.trim() === '' ? null : label.trim() });
  };

  return (
    <Dialog
      open
      onClose={onClose}
      title={t('menu_batch_open')}
      description={t('menu_batch_open_text')}
      busy={open.isPending}
      closeLabel={t('menu_close')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={open.isPending}>
            {t('menu_cancel')}
          </Button>
          <Button loading={open.isPending} onClick={submit}>
            {t('menu_batch_open')}
          </Button>
        </>
      }
    >
      {products.isError && <ErrorBanner message={describeError(products.error, t).message} />}
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
            {sorted.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
                {item.batch_tracked ? ` (${t('menu_batch_badge')})` : ''}
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
      <Field label={t('menu_batch_quantity')} hint={t('menu_batch_quantity_hint')} error={fieldError('quantity')}>
        {(control) => <input {...control} className={fieldClass} inputMode="decimal" value={quantity} onChange={(event) => setQuantity(event.target.value)} />}
      </Field>
      <Field label={t('menu_batch_label')} error={fieldError('label')}>
        {(control) => <input {...control} className={fieldClass} value={label} maxLength={100} onChange={(event) => setLabel(event.target.value)} />}
      </Field>
      {failure && <ErrorBanner message={failure.message} />}
    </Dialog>
  );
}

function AdjustDialog({ mode, batch, onClose }: { mode: 'add' | 'set'; batch: Batch; onClose: () => void }) {
  const { t, language } = useI18n();
  const adjust = useAdjustBatch({ onSuccess: onClose });
  const [value, setValue] = useState('');
  const [reason, setReason] = useState('');
  const [problem, setProblem] = useState<string | null>(null);
  const failure = adjust.error ? describeError(adjust.error, t) : null;

  const submit = () => {
    const amount = parseDecimal(value);
    if (amount === null) return setProblem(value.trim() === '' ? t('menu_err_required') : t('menu_err_number'));
    setProblem(null);
    adjust.mutate({ id: batch.id, adjustment: { ...(mode === 'add' ? { add: amount } : { quantity_left: amount }), ...(reason.trim() ? { reason: reason.trim() } : {}) } });
  };

  return (
    <Dialog
      open
      onClose={onClose}
      title={mode === 'add' ? t('menu_batch_add') : t('menu_batch_correct')}
      description={`${batch.product_name}${batch.variant_name ? ` · ${batch.variant_name}` : ''} · ${t('menu_batch_left_now', { n: formatQuantity(Number(batch.quantity_left ?? 0), asUnit(batch.unit), language) })}`}
      busy={adjust.isPending}
      closeLabel={t('menu_close')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={adjust.isPending}>
            {t('menu_cancel')}
          </Button>
          <Button loading={adjust.isPending} onClick={submit}>
            {t('menu_save')}
          </Button>
        </>
      }
    >
      <Field label={mode === 'add' ? t('menu_batch_add_amount') : t('menu_batch_set_amount')} error={problem ?? failure?.fields.add ?? failure?.fields.quantity_left}>
        {(control) => <input {...control} data-autofocus className={fieldClass} inputMode="decimal" value={value} onChange={(event) => setValue(event.target.value)} />}
      </Field>
      <Field label={t('menu_batch_reason')} error={failure?.fields.reason}>
        {(control) => <input {...control} className={fieldClass} value={reason} maxLength={255} onChange={(event) => setReason(event.target.value)} />}
      </Field>
      {failure && <ErrorBanner message={failure.message} />}
    </Dialog>
  );
}

function CloseDialog({ batch, onClose }: { batch: Batch; onClose: () => void }) {
  const { t } = useI18n();
  const close = useCloseBatch({ onSuccess: onClose });
  return (
    <Dialog
      open
      onClose={onClose}
      variant="center"
      role="alertdialog"
      title={t('menu_batch_close_title')}
      description={t('menu_batch_close_text', { name: batch.product_name })}
      busy={close.isPending}
      closeLabel={t('menu_close')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={close.isPending}>
            {t('menu_cancel')}
          </Button>
          <Button variant="danger" loading={close.isPending} onClick={() => close.mutate(batch.id)}>
            {t('menu_batch_close')}
          </Button>
        </>
      }
    >
      {close.error && <ErrorBanner message={describeError(close.error, t).message} />}
    </Dialog>
  );
}
