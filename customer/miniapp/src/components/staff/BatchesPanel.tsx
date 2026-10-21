import { useId, useState } from 'react';
import { CookingPot, Lock, Plus } from 'lucide-react';
import {
  formatQuantityNumber,
  unitLabel,
  useAdjustBatch,
  useBatches,
  useBranchVariants,
  useCloseBatch,
  useOpenBatch,
  type Batch,
  type Localized,
  type MenuUnit,
} from '@rizqim/staff';
import { useLanguageStore } from '../../store/languageStore';
import { useRealtimeStore } from '../../store/realtimeStore';
import { hapticFeedback } from '../../lib/telegram';
import { notificationOccurred } from '../../lib/twa';
import { showAlert, showConfirm } from '../../lib/dialogs';
import { getApiErrorMessage, getApiFieldError, getApiFieldErrors, logApiError } from '../../lib/apiError';
import { formatTime } from '../../lib/format';
import { getPollInterval, POLL_INTERVAL_MS, pollingQueryOptions } from '../../lib/polling';
import { useMainButton } from '../../hooks/useMainButton';
import { useStaffText } from '../../hooks/useStaffText';
import { Button, Card, CardSkeletons, Dialog, EmptyState, Field, InlineError, cx, fieldClass } from '../ui';
import { Pill } from './Pill';
import { SegmentedControl } from './SegmentedControl';

const TEXT = {
  open: { uz: 'Yangi qozon ochish', ru: 'Открыть новый котёл' },
  openTitle: { uz: 'Yangi qozon', ru: 'Новый котёл' },
  openAction: { uz: 'Qozonni ochish', ru: 'Открыть котёл' },
  product: { uz: 'Taom', ru: 'Блюдо' },
  chooseProduct: { uz: 'Taomni tanlang', ru: 'Выберите блюдо' },
  variant: { uz: 'Variant', ru: 'Вариант' },
  allVariants: { uz: 'Hamma variantlar', ru: 'Все варианты' },
  quantity: { uz: 'Necha porsiya (miqdor)', ru: 'Сколько порций (количество)' },
  quantityHint: { uz: 'Masalan: 40 yoki 12,5', ru: 'Например: 40 или 12,5' },
  label: { uz: 'Izoh (ixtiyoriy)', ru: 'Заметка (необязательно)' },
  labelHint: { uz: 'Masalan: Tushlik qozoni', ru: 'Например: обеденный котёл' },
  left: { uz: 'Qoldi', ru: 'Осталось' },
  of: { uz: '{{left}} / {{total}} {{unit}}', ru: '{{left}} / {{total}} {{unit}}' },
  soldOut: { uz: 'Tugadi', ru: 'Закончился' },
  startedAt: { uz: 'Ochilgan: {{time}}', ru: 'Открыт: {{time}}' },
  refill: { uz: "To'ldirish", ru: 'Пополнить' },
  close: { uz: 'Yopish', ru: 'Закрыть' },
  closeAsk: { uz: '"{{name}}" qozoni yopiladi. Qolgan miqdor hisobdan chiqadi.', ru: 'Котёл «{{name}}» будет закрыт. Остаток спишется.' },
  closeTitle: { uz: 'Qozonni yopish', ru: 'Закрыть котёл' },
  closeConfirm: { uz: 'Yopish', ru: 'Закрыть' },
  adjustTitle: { uz: '{{name}}: qoldiq', ru: '{{name}}: остаток' },
  modeAdd: { uz: "Qo'shish", ru: 'Добавить' },
  modeSet: { uz: 'Qoldiq', ru: 'Остаток' },
  mode: { uz: 'Amal', ru: 'Действие' },
  addAmount: { uz: "Qancha qo'shiladi", ru: 'Сколько добавить' },
  setAmount: { uz: 'Hozirgi qoldiq', ru: 'Текущий остаток' },
  save: { uz: 'Saqlash', ru: 'Сохранить' },
  cancel: { uz: 'Bekor qilish', ru: 'Отмена' },
  empty: { uz: "Ochiq qozon yo'q", ru: 'Открытых котлов нет' },
  emptyText: { uz: "Osh yoki boshqa qozon bo'yicha taom tayyorlansa, shu yerdan oching.", ru: 'Когда готовится блюдо котлами, откройте котёл здесь.' },
  loading: { uz: 'Qozonlar yuklanmoqda', ru: 'Загрузка котлов' },
  failed: { uz: "Qozon bilan amalni bajarib bo'lmadi", ru: 'Не удалось выполнить действие с котлом' },
  invalidQuantity: { uz: 'Miqdorni raqam bilan kiriting', ru: 'Введите количество числом' },
} satisfies Record<string, Localized>;

// "12,5" or "12.5" -> 12.5; null when it is not a positive number
function parseQuantity(text: string): number | null {
  const value = Number(text.trim().replace(',', '.'));
  return Number.isFinite(value) && value > 0 ? value : null;
}

function batchName(batch: Batch): string {
  return batch.variant_name ? `${batch.product_name} (${batch.variant_name})` : batch.product_name;
}

// How much of a batch is left, 0..100
function leftPercent(batch: Batch): number {
  const total = batch.quantity_produced ?? 0;
  const left = batch.quantity_left ?? 0;
  return total > 0 ? Math.max(0, Math.min(100, Math.round((left / total) * 100))) : 0;
}

function AdjustDialog({ batch, onClose }: { batch: Batch; onClose: () => void }) {
  const tx = useStaffText(TEXT);
  const [mode, setMode] = useState<'add' | 'set'>('add');
  const [amount, setAmount] = useState('');
  const [error, setError] = useState<string | null>(null);
  const adjust = useAdjustBatch({
    onSuccess: () => {
      notificationOccurred('success');
      onClose();
    },
    onError: (err) => {
      logApiError('Failed to adjust the batch:', err);
      notificationOccurred('error');
      setError(getApiFieldError(err, 'add') ?? getApiFieldError(err, 'quantity_left') ?? getApiErrorMessage(err) ?? tx('failed'));
    },
  });
  const fieldId = useId();

  const submit = () => {
    const quantity = mode === 'add' ? parseQuantity(amount) : amount.trim() === '0' ? 0 : parseQuantity(amount);
    if (quantity === null) {
      setError(tx('invalidQuantity'));
      return;
    }
    setError(null);
    hapticFeedback('medium');
    adjust.mutate({ id: batch.id, adjustment: mode === 'add' ? { add: quantity } : { quantity_left: quantity } });
  };

  return (
    <Dialog
      open
      hideClose
      busy={adjust.isPending}
      onClose={onClose}
      title={tx('adjustTitle', { name: batchName(batch) })}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={adjust.isPending}>
            {tx('cancel')}
          </Button>
          <Button onClick={submit} loading={adjust.isPending}>
            {tx('save')}
          </Button>
        </>
      }
    >
      <SegmentedControl
        label={tx('mode')}
        value={mode}
        onChange={(value) => {
          setMode(value);
          setError(null);
        }}
        segments={[
          { value: 'add', label: tx('modeAdd') },
          { value: 'set', label: tx('modeSet') },
        ]}
      />
      <div className="flex flex-col gap-1.5">
        <label htmlFor={fieldId} className="text-footnote font-bold text-fg-muted">
          {mode === 'add' ? tx('addAmount') : tx('setAmount')}
        </label>
        <input
          id={fieldId}
          type="text"
          inputMode="decimal"
          data-autofocus
          value={amount}
          onChange={(e) => setAmount(e.target.value)}
          className={fieldClass}
          placeholder={tx('quantityHint')}
        />
      </div>
      {error && (
        <p role="alert" className="text-footnote font-medium text-danger-soft-fg">
          {error}
        </p>
      )}
    </Dialog>
  );
}

function BatchCard({ batch, busy, onRefill, onClose }: { batch: Batch; busy: boolean; onRefill: () => void; onClose: () => void }) {
  const language = useLanguageStore((state) => state.language);
  const tx = useStaffText(TEXT);
  const unit = unitLabel((batch.unit ?? 'piece') as MenuUnit, language);
  const left = batch.quantity_left ?? 0;
  const total = batch.quantity_produced ?? 0;
  const soldOut = left <= 0 || batch.sold_out_at !== null;
  const percent = leftPercent(batch);
  const name = batchName(batch);

  return (
    <article aria-label={name} className="flex flex-col gap-3 rounded-card border border-line bg-surface p-3">
      <div className="flex items-start gap-2">
        <div className="min-w-0 flex-1">
          <h3 className="truncate text-body font-bold text-fg">{name}</h3>
          <p className="truncate text-footnote text-fg-muted">
            {batch.label ? `${batch.label} · ` : ''}
            {tx('startedAt', { time: formatTime(batch.started_at) })}
          </p>
        </div>
        {soldOut && <Pill tone="danger">{tx('soldOut')}</Pill>}
      </div>

      <div className="flex flex-col gap-1.5">
        <div className="flex items-baseline justify-between gap-3">
          <span className="text-footnote text-fg-muted">{tx('left')}</span>
          <span className="text-headline font-bold text-fg">
            {tx('of', { left: formatQuantityNumber(left, language), total: formatQuantityNumber(total, language), unit })}
          </span>
        </div>
        <div
          role="progressbar"
          aria-label={`${name}: ${tx('left')}`}
          aria-valuemin={0}
          aria-valuemax={100}
          aria-valuenow={percent}
          className="h-2 overflow-hidden rounded-full bg-surface-2"
        >
          <div className={cx('h-full rounded-full', soldOut ? 'bg-danger' : percent <= 20 ? 'bg-warning' : 'bg-success')} style={{ width: `${percent}%` }} />
        </div>
      </div>

      <div className="flex gap-2">
        <Button variant="secondary" icon={Plus} disabled={busy} onClick={onRefill} className="grow basis-0" aria-label={`${tx('refill')}: ${name}`}>
          {tx('refill')}
        </Button>
        <Button variant="danger" icon={Lock} disabled={busy} onClick={onClose} className="grow basis-0" aria-label={`${tx('close')}: ${name}`}>
          {tx('close')}
        </Button>
      </div>
    </article>
  );
}

function OpenBatchForm({ branchId, products, onDone }: { branchId: number | undefined; products: ReturnType<typeof useBranchVariants>['data']; onDone: () => void }) {
  const tx = useStaffText(TEXT);
  const [productId, setProductId] = useState('');
  const [variantId, setVariantId] = useState('');
  const [quantity, setQuantity] = useState('');
  const [label, setLabel] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});

  const product = (products ?? []).find((item) => String(item.product_id) === productId);
  const amount = parseQuantity(quantity);

  const open = useOpenBatch({
    onSuccess: () => {
      notificationOccurred('success');
      onDone();
    },
    onError: (err) => {
      logApiError('Failed to open the batch:', err);
      notificationOccurred('error');
      const found = getApiFieldErrors(err, ['product_id', 'quantity', 'label']);
      if (Object.keys(found).length > 0) setErrors(found);
      else void showAlert(getApiErrorMessage(err) || tx('failed'));
    },
  });

  const submit = () => {
    if (!product || amount === null) return;
    hapticFeedback('medium');
    setErrors({});
    open.mutate({
      ...(branchId === undefined ? {} : { branch_id: branchId }),
      product_id: product.product_id,
      ...(variantId ? { product_variant_id: Number(variantId) } : {}),
      quantity: amount,
      ...(label.trim() ? { label: label.trim() } : {}),
    });
  };

  // The one action of the open form, at the bottom
  useMainButton({ text: tx('openAction'), onClick: submit, enabled: product !== undefined && amount !== null, progress: open.isPending });

  return (
    <Card className="flex flex-col gap-4">
      <h2 className="flex items-center gap-2 text-headline font-bold text-fg">
        <span className="flex size-8 items-center justify-center rounded-control bg-brand-soft text-brand-soft-fg">
          <CookingPot aria-hidden="true" className="size-5" />
        </span>
        {tx('openTitle')}
      </h2>
      <Field label={tx('product')} error={errors.product_id}>
        {(props) => (
          <select
            {...props}
            value={productId}
            onChange={(e) => {
              setProductId(e.target.value);
              setVariantId('');
            }}
            className={fieldClass}
          >
            <option value="">{tx('chooseProduct')}</option>
            {(products ?? []).map((item) => (
              <option key={item.product_id} value={item.product_id}>
                {item.name}
              </option>
            ))}
          </select>
        )}
      </Field>
      {product && product.variants.length > 1 && (
        <Field label={tx('variant')}>
          {(props) => (
            <select {...props} value={variantId} onChange={(e) => setVariantId(e.target.value)} className={fieldClass}>
              <option value="">{tx('allVariants')}</option>
              {product.variants.map((variant) => (
                <option key={variant.variant_id} value={variant.variant_id}>
                  {variant.name}
                </option>
              ))}
            </select>
          )}
        </Field>
      )}
      <Field label={tx('quantity')} hint={tx('quantityHint')} error={errors.quantity}>
        {(props) => <input {...props} type="text" inputMode="decimal" value={quantity} onChange={(e) => setQuantity(e.target.value)} className={fieldClass} />}
      </Field>
      <Field label={tx('label')} error={errors.label}>
        {(props) => <input {...props} type="text" value={label} onChange={(e) => setLabel(e.target.value)} placeholder={tx('labelHint')} className={fieldClass} />}
      </Field>
    </Card>
  );
}

// The "qozon" of a branch: the open batches with what is left, a top-up or a new count, closing, and opening a new one.
// The numbers are the backend's (it takes the portions off as orders come in).
export function BatchesPanel({ branchId, ready }: { branchId: number | undefined; ready: boolean }) {
  const tx = useStaffText(TEXT);
  const realtimeConnected = useRealtimeStore((state) => state.connected);
  const [adjusting, setAdjusting] = useState<Batch | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [closing, setClosing] = useState<number | null>(null);

  const polling = { ...pollingQueryOptions(getPollInterval(POLL_INTERVAL_MS.stopList, realtimeConnected)), enabled: ready };
  const batches = useBatches({ branchId, ...polling });
  const products = useBranchVariants(branchId, { enabled: ready && formOpen });

  const closeBatch = useCloseBatch({
    onSuccess: () => notificationOccurred('success'),
    onError: (err) => {
      logApiError('Failed to close the batch:', err);
      notificationOccurred('error');
      void showAlert(getApiErrorMessage(err) || tx('failed'));
    },
    onSettled: () => setClosing(null),
  });

  const askClose = async (batch: Batch) => {
    hapticFeedback('light');
    const confirmed = await showConfirm(tx('closeAsk', { name: batchName(batch) }), { title: tx('closeTitle'), confirmText: tx('closeConfirm'), destructive: true });
    if (!confirmed) return;
    setClosing(batch.id);
    closeBatch.mutate(batch.id);
  };

  if (!ready || (batches.isLoading && !batches.data)) return <CardSkeletons count={2} lines={3} label={tx('loading')} />;
  if (batches.error && !batches.data) return <InlineError onRetry={() => void batches.refetch()} retrying={batches.isRefetching} />;

  const list = (batches.data ?? []).filter((batch) => batch.is_open);

  return (
    <div className="flex flex-col gap-4">
      {formOpen ? (
        <>
          <OpenBatchForm branchId={branchId} products={products.data} onDone={() => setFormOpen(false)} />
          <Button variant="secondary" onClick={() => setFormOpen(false)} fullWidth>
            {tx('cancel')}
          </Button>
        </>
      ) : (
        <Button icon={Plus} onClick={() => setFormOpen(true)} fullWidth>
          {tx('open')}
        </Button>
      )}

      {list.length === 0 ? (
        <EmptyState icon={CookingPot} title={tx('empty')} text={tx('emptyText')} />
      ) : (
        <ul className="flex flex-col gap-3">
          {list.map((batch) => (
            <li key={batch.id}>
              <BatchCard batch={batch} busy={closing === batch.id} onRefill={() => setAdjusting(batch)} onClose={() => void askClose(batch)} />
            </li>
          ))}
        </ul>
      )}

      {adjusting && <AdjustDialog batch={adjusting} onClose={() => setAdjusting(null)} />}
    </div>
  );
}
