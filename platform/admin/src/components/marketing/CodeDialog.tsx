'use client';

import { useState } from 'react';
import { Lock } from 'lucide-react';
import { Field, fieldClass, Switch } from '@rizqim/ui';
import { useCreateCode, useUpdateCode, type CodeInput, type CodeType, type PromoCode } from '@/lib/api/marketing';
import { errorText, fieldError } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { fromLocalInput, toLocalInput, TYPE_LABEL } from './marketingModel';
import { LookupPicker, ReasonDialog } from './MarketingParts';

// A new promo code or the change of one (marketing.manage + a reason). The rules are the order flow's own. Once an order carries
// the code, what it gives (the code itself, its type, value, cap and store) is locked: a new campaign is a new code.

const CODE = /^[A-Za-z0-9_-]{3,50}$/;
const DECIMAL = /^\d{1,9}(\.\d{1,2})?$/;
const clean = (value: string) => value.replace(/\s+/g, '').replace(',', '.');
const count = (value: string) => (value.trim() === '' ? null : Number(value));
const COUNT = /^[1-9]\d{0,7}$/;

export function CodeDialog({ code, onDone, onClose }: { code: PromoCode | null; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const create = useCreateCode();
  const update = useUpdateCode();
  const mutation = code ? update : create;
  const locked = code?.locked ?? false;

  const [text, setText] = useState(code?.code ?? '');
  const [type, setType] = useState<CodeType>(code?.type ?? 'percentage');
  const [value, setValue] = useState(code ? String(Number(code.value)) : '');
  const [minOrder, setMinOrder] = useState(code ? String(Number(code.min_order_amount)) : '0');
  const [maxDiscount, setMaxDiscount] = useState(code?.max_discount_amount ? String(Number(code.max_discount_amount)) : '');
  const [usageLimit, setUsageLimit] = useState(code?.usage_limit ? String(code.usage_limit) : '');
  const [perUser, setPerUser] = useState(code ? (code.per_user_limit ? String(code.per_user_limit) : '') : '1');
  const [store, setStore] = useState(code?.store ?? null);
  const [active, setActive] = useState(code?.is_active ?? true);
  const [expires, setExpires] = useState(toLocalInput(code?.expires_at));

  const amount = clean(value);
  const valueBad = value !== '' && (!DECIMAL.test(amount) || Number(amount) <= 0 || (type === 'percentage' ? Number(amount) > 100 : !Number.isInteger(Number(amount))));
  const minBad = minOrder !== '' && !DECIMAL.test(clean(minOrder));
  const capBad = type === 'percentage' && maxDiscount !== '' && (!DECIMAL.test(clean(maxDiscount)) || Number(clean(maxDiscount)) <= 0);
  const usageBad = usageLimit !== '' && !COUNT.test(usageLimit.trim());
  const perUserBad = perUser !== '' && !COUNT.test(perUser.trim());
  const expiresIso = fromLocalInput(expires);
  const valid = CODE.test(text.trim()) && amount !== '' && !valueBad && !minBad && !capBad && !usageBad && !perUserBad;

  const changes = (): Omit<CodeInput, 'reason'> => {
    const next: Omit<CodeInput, 'reason'> = {
      code: text.trim().toUpperCase(),
      type,
      value: amount,
      min_order_amount: minOrder === '' ? '0' : clean(minOrder),
      max_discount_amount: type === 'percentage' && maxDiscount !== '' ? clean(maxDiscount) : null,
      usage_limit: count(usageLimit),
      per_user_limit: count(perUser),
      store_id: store?.id ?? null,
      is_active: active,
      expires_at: expiresIso,
    };
    if (!code) return next;
    const result: Omit<CodeInput, 'reason'> = {};
    if (next.code !== code.code) result.code = next.code;
    if (next.type !== code.type) result.type = next.type;
    if (Number(next.value) !== Number(code.value)) result.value = next.value;
    if (Number(next.min_order_amount) !== Number(code.min_order_amount)) result.min_order_amount = next.min_order_amount;
    if ((next.max_discount_amount === null ? null : Number(next.max_discount_amount)) !== (code.max_discount_amount === null ? null : Number(code.max_discount_amount))) result.max_discount_amount = next.max_discount_amount;
    if (next.usage_limit !== code.usage_limit) result.usage_limit = next.usage_limit;
    if (next.per_user_limit !== code.per_user_limit) result.per_user_limit = next.per_user_limit;
    if ((next.store_id ?? null) !== (code.store?.id ?? null)) result.store_id = next.store_id;
    if (next.is_active !== code.is_active) result.is_active = next.is_active;
    if (next.expires_at !== (code.expires_at ? fromLocalInput(toLocalInput(code.expires_at)) : null)) result.expires_at = next.expires_at;
    return result;
  };
  const changed = code === null || Object.keys(changes()).length > 0;

  const submit = (reason: string) => {
    const body = changes();
    if (code) update.mutate({ id: code.id, ...body, reason }, { onSuccess: () => onDone(t('marketing_code_saved')) });
    else create.mutate({ ...body, code: text.trim().toUpperCase(), type, value: amount, reason }, { onSuccess: () => onDone(t('marketing_code_created')) });
  };

  const err = (field: string) => fieldError(mutation.error, field);

  return (
    <ReasonDialog
      title={code ? t('marketing_code_editTitle', { code: code.code }) : t('marketing_code_newTitle')}
      text={t('marketing_code_dialogText')}
      submitLabel={t('save')}
      busy={mutation.isPending}
      error={mutation.error ? errorText(mutation.error, t) : null}
      reasonError={err('reason')}
      canSubmit={valid && changed}
      onClose={onClose}
      onSubmit={submit}
    >
      {locked && (
        <p className="flex items-start gap-2 rounded-control bg-warning-soft px-3 py-2.5 text-footnote font-bold text-warning-soft-fg">
          <Lock aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
          {t('marketing_code_lockedNote')}
        </p>
      )}
      <Field label={t('marketing_code_code')} hint={t('marketing_code_codeHint')} error={text !== '' && !CODE.test(text.trim()) ? t('marketing_code_codeBad') : err('code')}>
        {(control) => <input {...control} data-autofocus type="text" className={`${fieldClass} font-mono uppercase`} value={text} maxLength={50} disabled={locked} onChange={(event) => setText(event.target.value)} />}
      </Field>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label={t('marketing_code_type')} error={err('type')}>
          {(control) => (
            <select {...control} className={fieldClass} value={type} disabled={locked} onChange={(event) => setType(event.target.value as CodeType)}>
              {(['percentage', 'fixed'] as const).map((option) => (
                <option key={option} value={option}>
                  {t(TYPE_LABEL[option])}
                </option>
              ))}
            </select>
          )}
        </Field>
        <Field
          label={type === 'percentage' ? t('marketing_code_valuePercent') : t('marketing_code_valueFixed')}
          error={valueBad ? (type === 'percentage' ? t('marketing_code_percentBad') : t('marketing_code_fixedBad')) : err('value')}
        >
          {(control) => <input {...control} type="text" inputMode="decimal" className={fieldClass} value={value} disabled={locked} onChange={(event) => setValue(event.target.value)} />}
        </Field>
      </div>
      {type === 'percentage' && (
        <Field label={t('marketing_code_cap')} hint={t('marketing_code_capHint')} error={capBad ? t('marketing_code_amountBad') : err('max_discount_amount')}>
          {(control) => <input {...control} type="text" inputMode="decimal" className={fieldClass} value={maxDiscount} disabled={locked} onChange={(event) => setMaxDiscount(event.target.value)} />}
        </Field>
      )}
      <Field label={t('marketing_code_minOrder')} error={minBad ? t('marketing_code_amountBad') : err('min_order_amount')}>
        {(control) => <input {...control} type="text" inputMode="decimal" className={fieldClass} value={minOrder} onChange={(event) => setMinOrder(event.target.value)} />}
      </Field>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label={t('marketing_code_usageLimit')} hint={t('marketing_code_usageHint')} error={usageBad ? t('marketing_code_countBad') : err('usage_limit')}>
          {(control) => <input {...control} type="text" inputMode="numeric" className={fieldClass} value={usageLimit} placeholder={t('marketing_code_unlimited')} onChange={(event) => setUsageLimit(event.target.value)} />}
        </Field>
        <Field label={t('marketing_code_perUser')} hint={t('marketing_code_perUserHint')} error={perUserBad ? t('marketing_code_countBad') : err('per_user_limit')}>
          {(control) => <input {...control} type="text" inputMode="numeric" className={fieldClass} value={perUser} placeholder={t('marketing_code_unlimited')} onChange={(event) => setPerUser(event.target.value)} />}
        </Field>
      </div>
      <LookupPicker label={t('marketing_code_store')} kind="store" value={store} onChange={setStore} disabled={locked} hint={t('marketing_code_storeHint')} />
      <Field label={t('marketing_code_expires')} hint={t('marketing_code_expiresHint')} error={err('expires_at')}>
        {(control) => <input {...control} type="datetime-local" className={fieldClass} value={expires} onChange={(event) => setExpires(event.target.value)} />}
      </Field>
      <Switch checked={active} onChange={setActive} label={t('marketing_code_active')} />
    </ReasonDialog>
  );
}
