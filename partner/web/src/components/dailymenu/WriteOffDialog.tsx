'use client';

import { useRef, useState } from 'react';
import { Button, Dialog, Field, fieldClass } from '@rizqim/ui';
import { formatQuantity, type MenuUnit } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { ErrorBanner } from '../menu/ErrorBanner';
import { parseDecimal } from '../menu/numbers';
import { describeError } from '../menu/serverError';
import { WRITE_OFF_REASONS, type WriteOffReason } from './api';
import { useWriteOffBatch } from './hooks';

const asUnit = (unit: string | null): MenuUnit | null => (unit === 'kg' || unit === 'l' || unit === 'piece' ? unit : null);

interface BatchLike {
  id: number;
  product_name?: string | null;
  variant_name?: string | null;
  quantity_left?: number | string | null;
  unit: string | null;
}

// Hisobdan chiqarish: unsold portions of an open batch taken off with a reason. "Qolganini chiqarib, yopish" writes off the
// rest and closes the batch (the end of the day). The retry of a lost answer carries the same Idempotency-Key.
export function WriteOffDialog({ batch, onClose }: { batch: BatchLike; onClose: () => void }) {
  const { t, language } = useI18n();
  const left = Number(batch.quantity_left ?? 0);
  const key = useRef<string>(globalThis.crypto.randomUUID());
  const write = useWriteOffBatch({ onSuccess: onClose });
  const [quantity, setQuantity] = useState('');
  const [reason, setReason] = useState<WriteOffReason>('unsold');
  const [note, setNote] = useState('');
  const [close, setClose] = useState(false);
  const [problem, setProblem] = useState<Record<string, string>>({});
  const failure = write.error ? describeError(write.error, t) : null;
  const fieldError = (name: string) => problem[name] ?? failure?.fields[name];

  const submit = () => {
    const amount = parseDecimal(quantity);
    const found: Record<string, string> = {};
    if (amount === null || amount <= 0) found.quantity = quantity.trim() === '' ? t('menu_err_required') : t('menu_err_number');
    else if (amount > left) found.quantity = t('dm_writeOffTooMuch', { n: formatQuantity(left, asUnit(batch.unit), language) });
    if (reason === 'other' && note.trim() === '') found.note = t('dm_writeOffNoteRequired');
    setProblem(found);
    if (Object.keys(found).length > 0 || amount === null) return;
    write.mutate({ id: batch.id, key: key.current, quantity: amount, reason, note: note.trim() === '' ? null : note.trim(), close });
  };

  const everything = () => {
    setQuantity(String(left).replace('.', ','));
    setClose(true);
  };

  return (
    <Dialog
      open
      onClose={onClose}
      title={t('dm_writeOffTitle')}
      description={`${batch.product_name ?? ''}${batch.variant_name ? ` · ${batch.variant_name}` : ''} · ${t('menu_batch_left_now', { n: formatQuantity(left, asUnit(batch.unit), language) })}`}
      busy={write.isPending}
      closeLabel={t('menu_close')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={write.isPending}>
            {t('menu_cancel')}
          </Button>
          <Button loading={write.isPending} onClick={submit}>
            {t('dm_writeOffSubmit')}
          </Button>
        </>
      }
    >
      <Field label={t('dm_writeOffQuantity')} error={fieldError('quantity')}>
        {(control) => <input {...control} data-autofocus className={fieldClass} inputMode="decimal" value={quantity} onChange={(event) => setQuantity(event.target.value)} />}
      </Field>
      <div>
        <Button variant="ghost" onClick={everything} disabled={left <= 0}>
          {t('dm_writeOffAll')}
        </Button>
      </div>
      <Field label={t('dm_writeOffReason')} error={fieldError('reason')}>
        {(control) => (
          <select {...control} className={fieldClass} value={reason} onChange={(event) => setReason(event.target.value as WriteOffReason)}>
            {WRITE_OFF_REASONS.map((value) => (
              <option key={value} value={value}>
                {t(`dm_reason_${value}`)}
              </option>
            ))}
          </select>
        )}
      </Field>
      <Field label={t('dm_writeOffNote')} error={fieldError('note')}>
        {(control) => <input {...control} className={fieldClass} maxLength={255} value={note} onChange={(event) => setNote(event.target.value)} />}
      </Field>
      <label className="flex min-h-11 cursor-pointer items-center gap-3 text-body text-fg">
        <input type="checkbox" className="size-5 shrink-0 accent-[var(--color-brand)]" checked={close} onChange={(event) => setClose(event.target.checked)} />
        {t('dm_writeOffClose')}
      </label>
      {failure && <ErrorBanner message={failure.message} />}
    </Dialog>
  );
}
