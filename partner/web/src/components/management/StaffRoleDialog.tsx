'use client';

import { useState, type FormEvent } from 'react';
import { Button, Dialog, Field, fieldClass, FormError } from '@rizqim/ui';
import { useChangeStaffRole, type AssignableRole, type ManagedBranch, type StaffMember } from '@rizqim/staff';
import { roleLabel } from '@/lib/labels';
import { useI18n } from '../I18nProvider';
import { errorText } from './errors';
import { ASSIGNABLE_ROLES } from './StaffFormDialog';

// PUT /owner/staff/{id}/role (staff.manage, the owner): a new role and, if need be, a new branch. The owner's own role and
// another owner's are refused by the server (422/403) and the message is shown here.
export function StaffRoleDialog({ member, branches, onClose, onSaved }: { member: StaffMember; branches: ManagedBranch[]; onClose: () => void; onSaved: () => void }) {
  const { t } = useI18n();
  const [role, setRole] = useState<string>(member.role);
  const [branchId, setBranchId] = useState<string>(String(member.branch?.id ?? ''));
  const [error, setError] = useState<string | null>(null);
  const change = useChangeStaffRole();

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setError(null);
    change.mutate(
      { id: member.id, input: { role: role as AssignableRole, branch_id: branchId === '' ? null : Number(branchId) } },
      { onSuccess: onSaved, onError: (err) => setError(errorText(err, t)) },
    );
  };

  return (
    <Dialog open title={t('staff_roleTitle', { name: member.name })} busy={change.isPending} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} className="flex flex-col gap-4">
        <Field label={t('staff_role')} hint={t('staff_roleChangeHint')}>
          {(props) => (
            <select {...props} data-autofocus value={role} onChange={(event) => setRole(event.target.value)} className={fieldClass}>
              {!ASSIGNABLE_ROLES.includes(member.role as AssignableRole) && <option value={member.role}>{roleLabel(t, member.role)}</option>}
              {ASSIGNABLE_ROLES.map((value) => (
                <option key={value} value={value}>
                  {roleLabel(t, value)}
                </option>
              ))}
            </select>
          )}
        </Field>
        <Field label={t('staff_branch')}>
          {(props) => (
            <select {...props} value={branchId} onChange={(event) => setBranchId(event.target.value)} className={fieldClass}>
              <option value="">{t('staff_wholeStore')}</option>
              {branches.map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.name}
                </option>
              ))}
            </select>
          )}
        </Field>
        <FormError message={error} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={change.isPending}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={change.isPending}>
            {t('mgmt_save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
