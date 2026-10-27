'use client';

import { useState, type FormEvent } from 'react';
import { Button, Dialog, FormError } from '@rizqim/ui';
import { useCreateCustomerAccount, useUpdateCustomerAccount, type CustomerAccount, type CustomerAccountPatch } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Check, NumberField, parseDecimal, PhoneField, phoneToE164, phoneToInput } from '../management/fields';
import { SelectField, TextField } from '../pos/fields';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';

type LimitKind = 'none' | 'zero' | 'amount';
const FIELDS = ['name', 'phone', 'group_name', 'credit_limit', 'is_blocked', 'note', 'branch_id'];

const kindOf = (account: CustomerAccount | null): LimitKind => (account === null || account.credit_limit === null ? 'none' : Number(account.credit_limit) === 0 ? 'zero' : 'amount');

// Open an account (a person or a group) or change one's name, phone, credit limit and block. A blocked account takes no new
// credit; repayments are still accepted. The phone is unique in the store (the server says so).
export function AccountDialog({ account, onClose, onSaved }: { account: CustomerAccount | null; onClose: () => void; onSaved: (account: CustomerAccount) => void }) {
  const { t } = useI18n();
  const [name, setName] = useState(account?.name ?? '');
  const [group, setGroup] = useState(account?.group_name ?? '');
  const originalPhone = phoneToInput(account?.phone);
  const [phone, setPhone] = useState(originalPhone);
  const [limitKind, setLimitKind] = useState<LimitKind>(kindOf(account));
  const [limit, setLimit] = useState(account?.credit_limit && Number(account.credit_limit) > 0 ? String(Number(account.credit_limit)) : '');
  const [blocked, setBlocked] = useState(account?.is_blocked ?? false);
  const [note, setNote] = useState(account?.note ?? '');
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<Record<string, string>>({});
  const create = useCreateCustomerAccount();
  const update = useUpdateCustomerAccount();
  const busy = create.isPending || update.isPending;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const problems: Record<string, string> = {};
    if (name.trim() === '') problems.name = t('acct_err_name');
    const e164 = phoneToE164(phone);
    if (e164 === undefined) problems.phone = t('mgmt_phoneInvalid');
    const amount = parseDecimal(limit);
    if (limitKind === 'amount' && (amount === undefined || amount === null || amount <= 0)) problems.credit_limit = t('acct_err_limit');
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;

    const creditLimit = limitKind === 'none' ? null : limitKind === 'zero' ? 0 : (amount as number);
    const options = { onSuccess: onSaved, onError: (error: unknown) => setErrors(sortServerErrors(error, FIELDS, t)) };
    if (!account) {
      create.mutate({ name: name.trim(), phone: e164 ?? null, group_name: group.trim() || null, credit_limit: creditLimit, is_blocked: blocked, note: note.trim() || null }, options);
      return;
    }
    // Only what changed: a phone in the request counts as the manager's word for it
    const patch: CustomerAccountPatch = {};
    if (name.trim() !== account.name) patch.name = name.trim();
    if (phone !== originalPhone) patch.phone = e164 ?? null;
    if (group.trim() !== (account.group_name ?? '')) patch.group_name = group.trim() || null;
    const was = account.credit_limit === null ? null : Number(account.credit_limit);
    if (creditLimit !== was) patch.credit_limit = creditLimit;
    if (blocked !== account.is_blocked) patch.is_blocked = blocked;
    if (note.trim() !== (account.note ?? '')) patch.note = note.trim() || null;
    if (Object.keys(patch).length === 0) return onClose();
    update.mutate({ uuid: account.uuid, patch }, options);
  };

  const error = (field: string) => local[field] ?? errors.fields[field];

  return (
    <Dialog open title={account ? t('acct_editTitle') : t('acct_newTitle')} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <TextField label={t('acct_name')} value={name} onChange={setName} maxLength={100} autoFocus error={error('name')} />
        <div className="grid gap-4 sm:grid-cols-2">
          <PhoneField label={t('acct_phone')} value={phone} onChange={setPhone} error={error('phone')} />
          <TextField label={t('acct_group')} hint={t('acct_groupHint')} value={group} onChange={setGroup} maxLength={100} error={error('group_name')} />
        </div>
        <SelectField
          label={t('acct_limit')}
          value={limitKind}
          onChange={setLimitKind}
          options={[
            { value: 'none', label: t('acct_limitNone') },
            { value: 'amount', label: t('acct_limitAmount') },
            { value: 'zero', label: t('acct_limitZero') },
          ]}
          hint={t('acct_limitHint')}
        />
        {limitKind === 'amount' && <NumberField label={t('acct_limitSum')} value={limit} onChange={setLimit} suffix="so'm" error={error('credit_limit')} />}
        <Check checked={blocked} onChange={setBlocked} label={t('acct_blockLabel')} hint={t('acct_blockHint')} />
        <TextField label={t('acct_note')} value={note} onChange={setNote} maxLength={500} error={error('note')} />
        <FormError message={errors.form} />
        <div className="flex flex-wrap justify-end gap-3">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={busy}>
            {t('mgmt_save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
