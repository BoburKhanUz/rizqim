'use client';

import { useMemo, useState, type FormEvent } from 'react';
import { ChevronRight, FileDown, FilePlus2, Plus, SlidersHorizontal } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, Dialog, EmptyState, Field, fieldClass, FormError, InlineError, Pill } from '@rizqim/ui';
import { saveFile } from '@/lib/api/client';
import { idempotencyKey } from '@/lib/api/orders';
import {
  ADJUSTMENT_FILTERS,
  ADJUSTMENT_KINDS,
  downloadAdjustmentDocument,
  useAdjustment,
  useAdjustments,
  useCreateAdjustment,
  useDecideAdjustment,
  type Adjustment,
  type AdjustmentFilter,
  type AdjustmentKind,
} from '@/lib/api/finance';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { StorePicker } from '../stores/StorePicker';
import { ADJUSTMENT_FILTER_LABEL, adjustmentTone, KIND_LABEL, MAX_DOCUMENT_BYTES, parseAmountInput, reachesThreshold, signed, uzs } from './financeModel';
import { CHIP, chipTone } from './PayoutsPanel';
import { reasonOk, ReasonFields, Row } from './ReasonDialog';

// Qo'lda tuzatish: manual changes of a store's debt or wallet with a reason and an optional document. From the threshold
// (1 000 000 so'm) one waits until a different member approves it; the ledger changes only then.
export function AdjustmentsPanel({ enabled, canManage, threshold, onOpen, onDone }: { enabled: boolean; canManage: boolean; threshold: string | undefined; onOpen: (id: number) => void; onDone: (message: string) => void }) {
  const t = useT();
  const [filter, setFilter] = useState<AdjustmentFilter>('all');
  const [creating, setCreating] = useState(false);
  const adjustments = useAdjustments(filter, enabled);
  const rows = useMemo(() => adjustments.data?.pages.flatMap((page) => page.data) ?? [], [adjustments.data]);
  const pending = adjustments.data?.pages[0]?.counts.pending;

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div role="group" aria-label={t('finance_adjustmentFilter')} className="flex flex-wrap gap-2">
          {ADJUSTMENT_FILTERS.map((value) => (
            <button key={value} type="button" aria-pressed={filter === value} className={cx(CHIP, chipTone(filter === value))} onClick={() => setFilter(value)}>
              {value === 'pending' && pending !== undefined ? t('finance_tabCount', { label: t(ADJUSTMENT_FILTER_LABEL[value]), count: pending }) : t(ADJUSTMENT_FILTER_LABEL[value])}
            </button>
          ))}
        </div>
        {canManage && (
          <Button icon={Plus} onClick={() => setCreating(true)}>
            {t('finance_adjustmentNew')}
          </Button>
        )}
      </div>
      {adjustments.isError ? (
        <InlineError title={t('finance_loadError')} message={errorText(adjustments.error, t)} retryLabel={t('retry')} retrying={adjustments.isFetching} onRetry={() => void adjustments.refetch()} />
      ) : !adjustments.isSuccess ? (
        <CardSkeletons count={3} lines={2} label={t('loading')} />
      ) : rows.length === 0 ? (
        <EmptyState icon={SlidersHorizontal} title={t('finance_adjustmentsEmpty')} />
      ) : (
        <>
          <ul aria-label={t('finance_adjustmentsLabel')} className="flex flex-col gap-2">
            {rows.map((row) => (
              <li key={row.id}>
                <Card padded={false}>
                  <button
                    type="button"
                    onClick={() => onOpen(row.id)}
                    aria-label={t('finance_adjustmentOpen', { id: row.id })}
                    className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 text-left hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                  >
                    <span className="flex min-w-0 flex-1 flex-col gap-1">
                      <span className="flex flex-wrap items-center gap-2">
                        <span className="text-body font-bold text-fg">{signed(row.signed_amount)}</span>
                        <Pill tone={adjustmentTone(row.status)}>{row.status_label}</Pill>
                      </span>
                      <span className="break-words text-footnote text-fg">{[row.store?.name, t(KIND_LABEL[row.kind])].filter(Boolean).join(' · ')}</span>
                      <span className="break-words text-footnote text-fg-muted">{row.reason}</span>
                      <span className="text-caption text-fg-muted">{t('finance_adjustmentRequested', { name: row.requested_by?.name ?? '—', time: when(row.requested_at) })}</span>
                    </span>
                    <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
                  </button>
                </Card>
              </li>
            ))}
          </ul>
          {adjustments.hasNextPage && (
            <Button variant="secondary" loading={adjustments.isFetchingNextPage} onClick={() => void adjustments.fetchNextPage()}>
              {t('finance_loadMore')}
            </Button>
          )}
        </>
      )}
      {creating && (
        <NewAdjustmentDialog
          threshold={threshold}
          onClose={() => setCreating(false)}
          onDone={(message, created) => {
            setCreating(false);
            onDone(message);
            if (created.status === 'pending') onOpen(created.id);
          }}
        />
      )}
    </div>
  );
}

function NewAdjustmentDialog({ threshold, onClose, onDone }: { threshold: string | undefined; onClose: () => void; onDone: (message: string, created: Adjustment) => void }) {
  const t = useT();
  const create = useCreateAdjustment();
  const [key] = useState(idempotencyKey);
  const [storeId, setStoreId] = useState<number | null>(null);
  const [kind, setKind] = useState<AdjustmentKind>('debt_charge');
  const [amountText, setAmountText] = useState('');
  const [reason, setReason] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const amount = parseAmountInput(amountText);
  const tooBig = file !== null && file.size > MAX_DOCUMENT_BYTES;
  const ready = storeId !== null && amount !== null && reasonOk(reason) && !tooBig;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!ready || create.isPending || storeId === null || amount === null) return;
    create.mutate(
      { store_id: storeId, kind, amount, reason: reason.trim(), document: file, key },
      { onSuccess: (created) => onDone(created.status === 'applied' ? t('finance_adjustmentApplied') : t('finance_adjustmentPending'), created) },
    );
  };

  return (
    <Dialog open title={t('finance_adjustmentNewTitle')} description={t('finance_adjustmentNewText', { amount: uzs(threshold ?? '1000000') })} busy={create.isPending} closeLabel={t('close')} onClose={onClose}>
      <form onSubmit={submit} className="flex flex-col gap-4">
        <StorePicker value={storeId} onChange={(id) => setStoreId(id)} label={t('finance_adjustmentStore')} />
        {fieldError(create.error, 'store_id') && <p className="text-footnote font-bold text-danger-soft-fg">{fieldError(create.error, 'store_id')}</p>}
        <Field label={t('finance_adjustmentKind')} error={fieldError(create.error, 'kind')}>
          {(control) => (
            <select {...control} className={fieldClass} value={kind} onChange={(event) => setKind(event.target.value as AdjustmentKind)}>
              {ADJUSTMENT_KINDS.map((value) => (
                <option key={value} value={value}>
                  {t(KIND_LABEL[value])}
                </option>
              ))}
            </select>
          )}
        </Field>
        <Field
          label={t('finance_adjustmentAmount')}
          hint={amountText !== '' && amount === null ? undefined : reachesThreshold(amount, threshold) ? t('finance_adjustmentWillWait') : t('finance_adjustmentAmountHint')}
          error={fieldError(create.error, 'amount') ?? (amountText !== '' && amount === null ? t('finance_adjustmentAmountBad') : undefined)}
        >
          {(control) => <input {...control} inputMode="decimal" autoComplete="off" className={fieldClass} value={amountText} onChange={(event) => setAmountText(event.target.value)} />}
        </Field>
        <ReasonFields reason={reason} onChange={setReason} error={fieldError(create.error, 'reason')} />
        <Field label={t('finance_adjustmentDocument')} hint={t('finance_adjustmentDocumentHint')} error={fieldError(create.error, 'document') ?? (tooBig ? t('finance_adjustmentDocumentTooBig') : undefined)}>
          {(control) => (
            <input
              {...control}
              type="file"
              accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"
              className={cx(fieldClass, 'py-2')}
              onChange={(event) => setFile(event.target.files?.[0] ?? null)}
            />
          )}
        </Field>
        <FormError message={create.error ? errorText(create.error, t) : null} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={create.isPending}>
            {t('cancel')}
          </Button>
          <Button type="submit" icon={FilePlus2} loading={create.isPending} disabled={!ready}>
            {t('finance_adjustmentCreate')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}

export function AdjustmentDialog({ id, onClose, onDone }: { id: number; onClose: () => void; onDone: (message: string) => void }) {
  const t = useT();
  const adjustment = useAdjustment(id);
  const decide = useDecideAdjustment();
  const [mode, setMode] = useState<'view' | 'approve' | 'reject'>('view');
  const [reason, setReason] = useState('');
  const [key, setKey] = useState(idempotencyKey);
  const [downloadError, setDownloadError] = useState<string | null>(null);
  const [downloading, setDownloading] = useState(false);
  const item = adjustment.data;

  const start = (next: 'approve' | 'reject') => {
    decide.reset();
    setReason('');
    setKey(idempotencyKey());
    setMode(next);
  };
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!item || mode === 'view' || decide.isPending || !reasonOk(reason)) return;
    decide.mutate(
      { id: item.id, decision: mode, reason: reason.trim(), key },
      {
        onSuccess: () => {
          setMode('view');
          onDone(mode === 'approve' ? t('finance_adjustmentApprovedDone') : t('finance_adjustmentRejectedDone'));
        },
      },
    );
  };
  const fetchDocument = async () => {
    if (!item) return;
    setDownloading(true);
    setDownloadError(null);
    try {
      saveFile(await downloadAdjustmentDocument(item));
    } catch (error) {
      setDownloadError(errorText(error, t));
    } finally {
      setDownloading(false);
    }
  };

  const title = mode === 'approve' ? t('finance_adjustmentApproveTitle') : mode === 'reject' ? t('finance_adjustmentRejectTitle') : t('finance_adjustmentTitle', { id });
  return (
    <Dialog
      open
      title={title}
      description={item && mode === 'approve' ? t('finance_adjustmentApproveText', { kind: t(KIND_LABEL[item.kind]), amount: signed(item.signed_amount) }) : undefined}
      busy={decide.isPending}
      closeLabel={t('close')}
      onClose={mode === 'view' ? onClose : () => setMode('view')}
    >
      {adjustment.isError ? (
        <InlineError title={t('finance_loadError')} message={errorText(adjustment.error, t)} retryLabel={t('retry')} onRetry={() => void adjustment.refetch()} />
      ) : !item ? (
        <CardSkeletons count={2} lines={3} label={t('loading')} />
      ) : mode === 'view' ? (
        <div className="flex flex-col gap-4">
          <div className="flex flex-wrap items-center gap-2">
            <Pill tone={adjustmentTone(item.status)}>{item.status_label}</Pill>
          </div>
          <dl className="divide-y divide-line">
            <Row label={t('finance_adjustmentStore')} value={item.store?.name ?? '—'} />
            <Row label={t('finance_adjustmentKind')} value={t(KIND_LABEL[item.kind])} />
            <Row label={t('finance_adjustmentAmount')} value={signed(item.signed_amount)} strong />
            <Row label={t('finance_reason')} value={item.reason} />
            {item.decision_note && <Row label={t('finance_payoutResolution')} value={item.decision_note} />}
          </dl>
          <p className="text-footnote text-fg-muted">{t('finance_adjustmentRequested', { name: item.requested_by?.name ?? '—', time: when(item.requested_at) })}</p>
          {item.approved_by && <p className="text-footnote text-fg-muted">{t('finance_adjustmentApproved', { name: item.approved_by.name, time: when(item.approved_at) })}</p>}
          {item.rejected_by && <p className="text-footnote text-fg-muted">{t('finance_adjustmentRejected', { name: item.rejected_by.name, time: when(item.rejected_at) })}</p>}
          {item.status === 'pending' && item.mine && <p className="text-footnote font-bold text-info-soft-fg">{t('finance_adjustmentMine')}</p>}
          {item.document && (
            <div className="flex flex-col gap-1">
              <Button variant="secondary" icon={FileDown} loading={downloading} onClick={() => void fetchDocument()}>
                {`${t('finance_documentDownload')}: ${item.document.name}`}
              </Button>
              <FormError message={downloadError} />
            </div>
          )}
          {item.transactions.length > 0 && (
            <section aria-label={t('finance_adjustmentRows')}>
              <h3 className="mb-1 text-footnote font-bold text-fg-muted">{t('finance_adjustmentRows')}</h3>
              <dl className="divide-y divide-line">
                {item.transactions.map((row) => (
                  <Row key={`${row.ledger}-${row.id}`} label={`${row.ledger === 'wallet' ? t('finance_ledgerWallet') : t('finance_ledgerDebt')} #${row.id}`} value={`${signed(row.amount)} (${uzs(row.balance_before)} → ${uzs(row.balance_after)})`} />
                ))}
              </dl>
            </section>
          )}
          {(item.can_approve || item.can_reject) && (
            <div className="flex gap-2 [&>*]:flex-1">
              {item.can_reject && (
                <Button variant="secondary" onClick={() => start('reject')}>
                  {t('finance_reject')}
                </Button>
              )}
              {item.can_approve && <Button onClick={() => start('approve')}>{t('finance_approve')}</Button>}
            </div>
          )}
        </div>
      ) : (
        <form onSubmit={submit} className="flex flex-col gap-4">
          <ReasonFields reason={reason} onChange={setReason} error={fieldError(decide.error, 'reason')} />
          <FormError message={decide.error ? errorText(decide.error, t) : null} />
          <div className="flex gap-2 [&>*]:flex-1">
            <Button variant="secondary" onClick={() => setMode('view')} disabled={decide.isPending}>
              {t('cancel')}
            </Button>
            <Button type="submit" variant={mode === 'reject' ? 'danger' : 'primary'} loading={decide.isPending} disabled={!reasonOk(reason)}>
              {mode === 'reject' ? t('finance_reject') : t('finance_approve')}
            </Button>
          </div>
        </form>
      )}
    </Dialog>
  );
}
