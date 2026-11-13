'use client';

import { useState, type FormEvent } from 'react';
import { Button, Dialog, FormError } from '@rizqim/ui';
import { useCreateCustomerAccount, useUpdateCustomerAccount, type CustomerAccount, type CustomerAccountKind, type CustomerAccountPatch, type TalonMode } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Check, NumberField, parseDecimal, PhoneField, phoneToE164, phoneToInput } from '../management/fields';
import { SelectField, TextField } from '../pos/fields';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';

type LimitKind = 'none' | 'zero' | 'amount';
const FIELDS = ['name', 'phone', 'group_name', 'credit_limit', 'is_blocked', 'note', 'branch_id', 'kind', 'company_name', 'inn', 'contact_name', 'contact_phone', 'invoice_email', 'monthly_limit', 'charge_approval', 'talon_mode'];
const TALON_MODES: readonly TalonMode[] = ['none', 'daily_limit', 'subscription'];
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const kindOf = (account: CustomerAccount | null): LimitKind => (account === null || account.credit_limit === null ? 'none' : Number(account.credit_limit) === 0 ? 'zero' : 'amount');

// Open an account (a person or a group) or change one's name, phone, credit limit and block. A blocked account takes no new
// credit; repayments are still accepted. The phone is unique in the store (the server says so). P6-44: the kind — nasiya or
// a company (its details, monthly limit, talon mode and whether a lunch at the till needs a manager); a company's members
// are on its own page (CorporateAccountView).
export function AccountDialog({ account, onClose, onSaved, initialKind = 'customer' }: { account: CustomerAccount | null; onClose: () => void; onSaved: (account: CustomerAccount) => void; initialKind?: CustomerAccountKind }) {
  const { t } = useI18n();
  const [kind, setKind] = useState<CustomerAccountKind>(account?.kind ?? initialKind);
  const [company, setCompany] = useState(account?.company_name ?? '');
  const [inn, setInn] = useState(account?.inn ?? '');
  const [contact, setContact] = useState(account?.contact_name ?? '');
  const [contactPhone, setContactPhone] = useState(phoneToInput(account?.contact_phone));
  const [email, setEmail] = useState(account?.invoice_email ?? '');
  const [monthly, setMonthly] = useState(account?.monthly_limit ? String(Number(account.monthly_limit)) : '');
  const [talonMode, setTalonMode] = useState<TalonMode>(account?.talon_mode ?? 'daily_limit');
  const [approval, setApproval] = useState(account?.kind === 'corporate' ? (account.charge_approval ?? false) : false);
  const corporate = kind === 'corporate';
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
    const contactE164 = phoneToE164(contactPhone);
    const monthlyAmount = parseDecimal(monthly);
    if (corporate) {
      if (inn.trim() !== '' && !/^(\d{9}|\d{14})$/.test(inn.replace(/\s/g, ''))) problems.inn = t('corp_err_inn');
      if (contactE164 === undefined) problems.contact_phone = t('mgmt_phoneInvalid');
      if (email.trim() !== '' && !EMAIL.test(email.trim())) problems.invoice_email = t('corp_err_email');
      if (monthlyAmount === undefined || (monthlyAmount !== null && monthlyAmount <= 0)) problems.monthly_limit = t('corp_err_monthly');
    }
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;

    const creditLimit = limitKind === 'none' ? null : limitKind === 'zero' ? 0 : (amount as number);
    const options = { onSuccess: onSaved, onError: (error: unknown) => setErrors(sortServerErrors(error, FIELDS, t)) };
    // P6-44: a company's details (a nasiya account sends none of them)
    const corporateFields = corporate
      ? {
          company_name: company.trim() || null,
          inn: inn.replace(/\s/g, '') || null,
          contact_name: contact.trim() || null,
          contact_phone: contactE164 ?? null,
          invoice_email: email.trim() || null,
          monthly_limit: monthlyAmount ?? null,
          talon_mode: talonMode,
          charge_approval: approval,
        }
      : {};
    if (!account) {
      create.mutate({ name: name.trim(), phone: e164 ?? null, group_name: group.trim() || null, credit_limit: creditLimit, is_blocked: blocked, note: note.trim() || null, ...(corporate ? { kind, ...corporateFields } : {}) }, options);
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
    if (kind !== (account.kind ?? 'customer')) patch.kind = kind;
    if (corporate) {
      const before: Record<string, unknown> = {
        company_name: account.company_name ?? null,
        inn: account.inn ?? null,
        contact_name: account.contact_name ?? null,
        contact_phone: account.contact_phone ?? null,
        invoice_email: account.invoice_email ?? null,
        monthly_limit: account.monthly_limit ? String(Number(account.monthly_limit)) : null,
        talon_mode: account.talon_mode ?? 'none',
        charge_approval: account.kind === 'corporate' ? (account.charge_approval ?? false) : null,
      };
      for (const [field, value] of Object.entries(corporateFields)) {
        if (value !== before[field]) Object.assign(patch, { [field]: value });
      }
    }
    if (Object.keys(patch).length === 0) return onClose();
    update.mutate({ uuid: account.uuid, patch }, options);
  };

  const error = (field: string) => local[field] ?? errors.fields[field];

  return (
    <Dialog open title={account ? t('acct_editTitle') : corporate ? t('corp_newTitle') : t('acct_newTitle')} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <SelectField
          label={t('corp_kind')}
          value={kind}
          onChange={setKind}
          options={[
            { value: 'customer', label: t('corp_kind_customer') },
            { value: 'corporate', label: t('corp_kind_corporate') },
          ]}
          hint={t('corp_kindHint')}
          error={error('kind')}
        />
        <TextField label={t('acct_name')} value={name} onChange={setName} maxLength={100} autoFocus error={error('name')} />
        {corporate && (
          <fieldset className="flex flex-col gap-4 rounded-card border border-line p-3">
            <legend className="px-1 text-footnote font-bold text-fg-muted">{t('corp_kind_corporate')}</legend>
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField label={t('corp_companyName')} value={company} onChange={setCompany} maxLength={160} error={error('company_name')} />
              <TextField label={t('corp_inn')} hint={t('corp_innHint')} value={inn} onChange={setInn} maxLength={16} inputMode="numeric" error={error('inn')} />
              <TextField label={t('corp_contactName')} value={contact} onChange={setContact} maxLength={120} error={error('contact_name')} />
              <PhoneField label={t('corp_contactPhone')} value={contactPhone} onChange={setContactPhone} error={error('contact_phone')} />
            </div>
            <TextField label={t('corp_invoiceEmail')} value={email} onChange={setEmail} maxLength={160} error={error('invoice_email')} />
            <NumberField label={t('corp_monthlyLimit')} hint={t('corp_monthlyLimitHint')} value={monthly} onChange={setMonthly} suffix="so'm" error={error('monthly_limit')} />
            <SelectField
              label={t('corp_talonMode')}
              value={talonMode}
              onChange={setTalonMode}
              options={TALON_MODES.map((mode) => ({ value: mode, label: t(`corp_talon_${mode}` as const) }))}
              hint={t('corp_talonModeHint')}
              error={error('talon_mode')}
            />
            <Check checked={approval} onChange={setApproval} label={t('corp_chargeApproval')} hint={t('corp_chargeApprovalHint')} />
          </fieldset>
        )}
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
