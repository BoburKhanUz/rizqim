'use client';

import { useState, type FormEvent } from 'react';
import { Lock } from 'lucide-react';
import { Button, Dialog, Field, fieldClass, FormError } from '@rizqim/ui';
import { getApiFieldError, useCreateManagedStaff, useUpdateManagedStaff, type AssignableRole, type CreatedStaff, type ManagedBranch, type StaffMember } from '@rizqim/staff';
import { roleLabel } from '@/lib/labels';
import { useI18n } from '../I18nProvider';
import { errorText } from './errors';
import { PhoneField, phoneToE164, phoneToInput } from './fields';

export const ASSIGNABLE_ROLES: readonly AssignableRole[] = ['manager', 'cashier', 'waiter', 'cook', 'hostess', 'courier'];

// Add a member (name, role, branch, login phone) or change one's name, branch and login phone. A role of an existing member
// has its own dialog; the phone is sent only when it was really changed, because the backend treats a phone in the request
// as the owner's word for it (docs/AUTH.md: it becomes the login phone). A phone the member proved by SMS themselves
// (`login_phone_proof: 'sms'` in the staff list) cannot be replaced or cleared here: the form shows it as fixed.
export function StaffFormDialog({
  member,
  branches,
  onClose,
  onCreated,
  onSaved,
}: {
  member: StaffMember | null;
  branches: ManagedBranch[];
  onClose: () => void;
  onCreated: (created: CreatedStaff) => void;
  onSaved: (member: StaffMember) => void;
}) {
  const { t } = useI18n();
  const editing = member !== null;
  const [name, setName] = useState(member?.name ?? '');
  const [role, setRole] = useState<AssignableRole>('cashier');
  const [branchId, setBranchId] = useState<string>(String(member?.branch?.id ?? (branches.length === 1 ? branches[0]!.id : '')));
  const originalPhone = phoneToInput(member?.phone);
  const [phone, setPhone] = useState(originalPhone);
  const phoneLocked = member?.login_phone_proof === 'sms';
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);

  const create = useCreateManagedStaff();
  const update = useUpdateManagedStaff();
  const busy = create.isPending || update.isPending;

  const fail = (err: unknown) => {
    const next: Record<string, string> = {};
    for (const field of ['name', 'role', 'branch_id', 'phone']) {
      const message = getApiFieldError(err, field);
      if (message) next[field] = message;
    }
    setErrors(next);
    setFormError(Object.keys(next).length === 0 ? errorText(err, t) : null);
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const local: Record<string, string> = {};
    if (name.trim() === '') local.name = t('staff_nameRequired');
    if (branchId === '') local.branch_id = t('staff_branchRequired');
    const e164 = phoneToE164(phone);
    if (e164 === undefined) local.phone = t('mgmt_phoneInvalid');
    setErrors(local);
    setFormError(null);
    if (Object.keys(local).length > 0) return;

    if (!editing) {
      create.mutate(
        { name: name.trim(), role, branch_id: Number(branchId), ...(e164 ? { phone: e164 } : {}) },
        { onSuccess: onCreated, onError: fail },
      );
      return;
    }
    const patch: { name?: string; branch_id?: number; phone?: string | null } = {};
    if (name.trim() !== member.name) patch.name = name.trim();
    if (Number(branchId) !== member.branch?.id) patch.branch_id = Number(branchId);
    if (!phoneLocked && phone !== originalPhone) patch.phone = e164 ?? null;
    if (Object.keys(patch).length === 0) {
      onClose();
      return;
    }
    update.mutate({ id: member.id, input: patch }, { onSuccess: onSaved, onError: fail });
  };

  return (
    <Dialog open title={editing ? t('staff_editTitle') : t('staff_addTitle')} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <Field label={t('staff_name')} error={errors.name}>
          {(props) => <input {...props} data-autofocus type="text" autoComplete="off" maxLength={255} value={name} onChange={(event) => setName(event.target.value)} className={fieldClass} />}
        </Field>

        {!editing && (
          <Field label={t('staff_role')} hint={t('staff_roleHint')} error={errors.role}>
            {(props) => (
              <select {...props} value={role} onChange={(event) => setRole(event.target.value as AssignableRole)} className={fieldClass}>
                {ASSIGNABLE_ROLES.map((value) => (
                  <option key={value} value={value}>
                    {roleLabel(t, value)}
                  </option>
                ))}
              </select>
            )}
          </Field>
        )}

        <Field label={t('staff_branch')} error={errors.branch_id}>
          {(props) => (
            <select {...props} value={branchId} onChange={(event) => setBranchId(event.target.value)} className={fieldClass}>
              <option value="">{t('staff_chooseBranch')}</option>
              {branches.map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.name}
                </option>
              ))}
            </select>
          )}
        </Field>

        <PhoneField
          label={t('staff_loginPhone')}
          value={phone}
          onChange={setPhone}
          readOnly={phoneLocked}
          error={errors.phone}
          hint={
            phoneLocked ? (
              <span className="inline-flex items-center gap-1.5">
                <Lock aria-hidden="true" className="size-3.5" />
                {t('staff_phoneLocked')}
              </span>
            ) : (
              t('staff_loginPhoneHint')
            )
          }
        />
        {editing && errors.phone && !phoneLocked && (
          <Button
            variant="ghost"
            onClick={() => {
              setPhone(originalPhone);
              setErrors((previous) => Object.fromEntries(Object.entries(previous).filter(([field]) => field !== 'phone')));
            }}
            className="self-start"
          >
            {t('staff_restorePhone')}
          </Button>
        )}

        <FormError message={formError} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={busy}>
            {editing ? t('mgmt_save') : t('staff_add')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
